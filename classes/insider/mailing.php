<?
require_once "swiftmailer/lib/swift_required.php";

//class insider_mailing extends insider_action
class insider_mailing extends insider_table
{
    public $fields = array(
        "from" => array("options" => array("mailing@pza.org.pl" => "mailing@pza.org.pl", "Komisja Szkolenia PZA <mailing@pza.org.pl>" => "Komisja Szkolenia PZA <mailing@pza.org.pl>")),
        "members" => array("Kluby", "ref" => "members", "by" => "name"),
        "member_profile"   =>  array("Profil klubu", "type" => "flags", "options" =>
            array("J" => "Profil klubu: Jaskiniowy",
                "W" => "Profil klubu: Wysokogórski",
                "K" => "Profil klubu: Kanioningowy",
                "N" => "Profil klubu: Narciarski",
                "S" => "Profil klubu: Sportowy")),
        "rights" => array("Uprawnienia", "ref" => "rights", "by" => "name"),
        "users" => array("Osoby", "ref" => "users", "by" => "ref"),

        "custom"   =>  array("Własne podpowiedzi", "type" => "flags", "options" =>
            array("grounds" => "Osoby posiadające zdefiniowane przejścia za okres 3 lat",
                )),
    );

    /*
     * Metoda pomocnicza dla route()
     * W zależności od wartości type (sms, phone) zwraca właściwy
     * adres email lub nr. tel dla użytkowników z listy $ids
     */
    protected function get_users($ids, $type)
    {
        $u = vsql::retr($q = "SELECT u.ref, $type as result " .
            " FROM users AS u" .
            " WHERE u.id in (" . implode(",", $ids) . ")" .
            " AND u.deleted = 0", "");

        return $u;
    }

    /*
     * Metoda pomocnicza dla route()
     * W zależności od wartości type (sms, phone) zwraca właściwy
     * adres email dla klubu
     */
    protected function get_members($ids, $type)
    {
        $u = vsql::retr($q = "SELECT m.name as ref, m.$type as result " .
            " FROM members AS m " .
            " WHERE m.id in (" . implode(",", $ids) . ")" .
            " AND m.deleted = 0", "");

        return $u;
    }

    /*
     * Metoda pomocnicza dla route()
     * W zależności od wartości type (sms, phone) zwraca właściwy
     * adres email lub nr. tel dla użytkowników przypisanych do uprawnień
     * z listy $ids
     */
    protected function get_rights($ids, $type)
    {
        $u = vsql::retr($q = "SELECT u.ref, u.$type as result " .
            " FROM rights AS r " .
            " LEFT JOIN entitlements AS e on e.right=r.id" .
            " LEFT JOIN users AS u on u.id=e.user" .
            " WHERE r.id in (" . implode(",", $ids) . ") and e.due >= NOW()" .
            " AND u.deleted = 0", "");

        return $u;
    }

    protected function get_member_profile($ids, $type)
    {
        foreach ($ids as $k=>$v) {
            $ids[$k] = "'" . $v . "'";
        }

        $u = vsql::retr($q = "SELECT m.name as ref, m.$type as result " .
            " FROM members AS m " .
            " WHERE m.profile in (" . implode(",", $ids) . ")" .
            " AND m.deleted = 0", "");

        return $u;
    }

    function get_custom_grounds($ids, $type)
    {
        $list = vsql::retr($q =
            "SELECT u.ref, u.email as result " .
            "FROM achievements AS t " .
            "LEFT JOIN grounds AS g ON g.id = t.ground AND g.deleted = 0 " .
            "LEFT JOIN grounds AS cat ON cat.id = t.categ AND t.deleted = 0 " .
            "JOIN users AS u ON u.id = t.user AND u.deleted = 0 " .
            "WHERE t.deleted = 0 AND (t.`date` BETWEEN \"" . (date("Y") - 2) . "-01-01 00:00:00\" AND NOW()) AND g.type = \"nature:climb\" GROUP BY result", "");

        return $list;
    }

    function get_custom($ids, $type)
    {
        $result = array();

        foreach ($ids as $id) {
            $getter = 'get_custom_' . $id;
            $result = array_merge($this->$getter($ids, $type), $result);
        }

        return $result;
    }

    function recipient()
    {

        $recipients = array();
        foreach (array('users', 'rights', 'members', 'member_profile', 'custom') as $field) {
            $extractor_name = 'get_' . $field;

            if (!method_exists($this, $extractor_name) || !isset($_REQUEST[$field])) {
                continue;
            }

            $field_elements = $_REQUEST[$field];

            $ids = array();
            if (is_array($field_elements)) {
                foreach ($field_elements as $item) {
                    list($id, $ref) = array_map("trim", explode(":", $item, 2));
                    if (ctype_digit($id)) {
                        $ids[] = $id;
                    }
                }
            }

            // TODO: fix
            //member profile? solution for now....
            if (in_array($field, array('member_profile', 'custom')) && is_array($field_elements)) {
                foreach ($field_elements as $field_element) {
                    $ids[] = array_search($field_element, $this->fields[$field]['options']);
                }
            }

            if (count($ids)) {
                $users = $this->$extractor_name($ids, 'email');
                foreach ($users as $user) {
                    if (strlen($user['result']) > 0 && filter_var($user['result'], FILTER_VALIDATE_EMAIL)) {
                        $recipients[$user['result']] = array('ref'=> $user['ref'], 'email' => $user['result']);
                    } else {
                        $errors[] = "Nie można wysłać wiadomości do " . $user['ref'] . " (" . $user['result'] .")";
                    }
                }
            }
        }

        asort($recipients);

        $this->S->assign('recipients', $recipients);
        $this->S->assign('errors', $errors);
        $this->S->display("insider/mailing_recipient.html");
    }

    /*
     * Główna metoda klasy.
     */
    function route()
    {
        if(!access::has("mailing")) {
            $this->S->display('insider/no_access_error.html');
            exit;
        }

        if (isset($_POST['type'])) {
            $errors = array();

            $types = array('email', 'phone');

            $from = $_REQUEST['from'];
            if (!isset($this->fields['from']['options'][$from])) {
                $from = reset($this->fields['from']['options']);
            }

            $type = 'email';
            if (isset($types[$_REQUEST['type']])) {
                $type = $types[$_REQUEST['type']];
            }

            $recipients = array();
            foreach (array('users', 'rights', 'members', 'member_profile') as $field) {
                $extractor_name = 'get_' . $field;

                if (!method_exists($this, $extractor_name) || !isset($_REQUEST[$field])) {
                    continue;
                }

                $field_elements = $_REQUEST[$field];

                $ids = array();
                if (is_array($field_elements)) {
                    foreach ($field_elements as $item) {
                        list($id, $ref) = array_map("trim", explode(":", $item, 2));
                        if (ctype_digit($id)) {
                            $ids[] = $id;
                        }
                    }
                }

                // TODO: fix
                //member profile? solution for now....
                if ($field == 'member_profile') {
                    $ids = str_split($field_elements);
                }

                if (count($ids)) {
                    $users = $this->$extractor_name($ids, $type);
                    foreach ($users as $user) {
                        if (strlen($user['result']) > 0 && filter_var($user['result'], FILTER_VALIDATE_EMAIL)) {
                            $recipients[$user['result']] = $user['result'];
                        } else {
                            $errors[] = "Nie można wysłać wiadomości do " . $user['ref'] . " (" . $user['result'] .")";
                        }
                    }
                }
            }

            $title = $_REQUEST['title'];
            $message = $_REQUEST['message'];

            // Teraz możemy wyslać wiasomości.
            $sender_name = 'send_to_' . $type;

            // wysyłamy kopię maila na skrzynkę pza
            $recipients[vsql::$email_conf['sender_email']] = vsql::$email_conf['sender_email'];

            $errors = array_merge($errors, $this->$sender_name($recipients, $title, $message, $from));

            // TODO: logujemy zdarzenie wysłania mailingu
            $log_line = sprintf("%s\tNadawca: \"%s\"\t Tytuł: \"%s\"\t Odbiorcy: \"%s\"\t Błędy: \"%s\"\n",
                date('c'),
                access::getlogin(),
                $title,
                implode(",", array_values($recipients)),
                implode(",", $errors)
            );
            file_put_contents(vsql::$email_conf['log_file'], $log_line, FILE_APPEND|LOCK_EX);


            $this->S->assign('fields', $this->fields);
            foreach (array('title', 'message', 'errors', 'recipients') as $item) {
                $this->S->assign($item, $$item);
            }

            $this->S->display('insider/mailing_result.html');
            exit;
        }
        $this->S->display("insider/mailing.html");
    }

    /*
     * Metoda wysyłająca smsy do listy osób.
     */
    protected function send_to_phone($recipients, $title, $message, $from = null)
    {
        foreach ($recipients as $recipient) {

            $this->send_sms($a = array(
                'username' => 'pezeta',
                'password' => vsql::$smsapi_pass,
                'to' => $recipient,
                'from' => 'Eco',
                'message' => strip_tags($message),
            ));
        }

        return array();
    }

    /*
     * Metoda wysyłająca emaile do listy osób.
     */
    protected function send_to_email($recipients, $title, $message, $from)
    {
        $errors = array();
        foreach ($recipients as $recipient) {
            $rval = $this->send_email($recipient, $title, $message, $from);
            if ($rval !== true) {
                $errors[] = $rval;
            }
        }

        return $errors;
    }

    /*
     * Rozwiajmy ID obiektów na właściwe nazwy dla formularzy
     */
    protected function expandIdsToFormList($ids, $table)
    {
        $ref = $this->fields[$table]['by'];

        // uzupełnamy listę użytkowników
        $users = array();
        foreach (explode(" ", $ids) as $id) {
            $id = trim($id);
            if (ctype_digit($id)) {
                $users[] = $id;
            }
        }

        $m = vsql::retr($q = "SELECT CONCAT(id, ':', " . $ref . ") AS sugg, u.* " .
            " FROM $table AS u" .
            " WHERE u.id in (" . implode(',', $users) . ")" .
            " AND u.deleted = 0", "");

        $user_list = array();
        foreach ($m as $user) {
            $user_list[] = $user['sugg'];
        }

        $data = array($table => implode(', ', $user_list));

        return $data;
    }

    /*
     * Chęć wysłania maila została wstosowana z listy użytkowników.
     * Przed wywoałaniem route() nalezy rozwiązać dane dla formularzy
     */
    public function users()
    {
        $data = $this->expandIdsToFormList($_REQUEST['id'], __FUNCTION__);

        $this->S->assign("data", $data);
        $this->route();
    }

    /*
     * Chęć wysłania maila została wstosowana z listy klubów.
     * Przed wywoałaniem route() nalezy rozwiązać dane dla formularzy
     */
    public function members()
    {
        $data = $this->expandIdsToFormList($_REQUEST['id'], __FUNCTION__);

        $this->S->assign("data", $data);
        $this->route();
    }

    /*
     * Chęć wysłania maila została wstosowana z listy uprawnień.
     * Przed wywoałaniem route() nalezy rozwiązać dane dla formularzy
     */
    public function entitlements()
    {
        $entitlements = array();
        foreach (explode(" ", $_REQUEST['id']) as $id) {
            $id = trim($id);
            if (ctype_digit($id)) {
                $entitlements[] = $id;
            }
        }

        // rozwińmy wpierw powiazania w konkretnych użytkowników
        $u = vsql::get($q = "SELECT group_concat(user SEPARATOR ' ') as users " .
            " FROM " . __FUNCTION__ . " AS e" .
            " WHERE e.id in (" . implode(",", $entitlements) . ")" .
            " AND e.deleted = 0", "");

        if (isset($u['users'])) {
            $data = $this->expandIdsToFormList($u['users'], 'users');
            $this->S->assign("data", $data);
        }

        $this->route();
    }

    /*
     * Metoda pomocnicza dla send_to_phone - faktyczna wysyłka smsa
     */
    protected function send_sms($params, $backup = false)
    {
        if ($backup == true) {
            $url = 'https://api2.smsapi.pl/sms.do';
        } else {
            $url = 'https://api.smsapi.pl/sms.do';
        }

        $c = curl_init();
        curl_setopt( $c, CURLOPT_URL, $url );
        curl_setopt( $c, CURLOPT_POST, true );
        curl_setopt( $c, CURLOPT_POSTFIELDS, $params );
        curl_setopt( $c, CURLOPT_RETURNTRANSFER, true );

        $content = curl_exec( $c );
        $http_status = curl_getinfo($c, CURLINFO_HTTP_CODE);

        if ($http_status != 200 && $backup == false) {
            $backup = true;
            $this->sms_send($params, $backup);
        }

        curl_close( $c );
        return $content;
    }

    /*
   * Metoda pomocnicza dla send_to_email - faktyczna wysyłka emaila
   */
    protected function send_email($recipient, $messageTitle, $messageBody, $from)
    {
        switch (vsql::$email_conf['transport']) {
            case 'smtp':
            case 'gmail':
                $transport = Swift_SmtpTransport::newInstance(
                    vsql::$email_conf['smtp_host'],
                    vsql::$email_conf['smtp_port'],
                    vsql::$email_conf['smtp_encryption'])
                    ->setUsername(vsql::$email_conf['smtp_username'])
                    ->setPassword(vsql::$email_conf['smtp_password'])
                ;
                break;
            case 'default':
                $transport = Swift_MailTransport::newInstance();
        }

        $mailer = Swift_Mailer::newInstance($transport);

        $message = Swift_Message::newInstance($messageTitle)
            ->setContentType('text/html')
            ->setFrom(array(vsql::$email_conf['sender_email'] => $from))
//            ->setFrom(array($from => 'Test'))
            ->setTo(array($recipient))
            ->setBody(strip_tags($messageBody))
            ->addPart($messageBody, 'text/html');
        try {
            $mailer->send($message);
        } catch (Exception $exc) {
            return $exc->getMessage();
        }

        return true;
    }

    function read_last_lines($fp, $num)
    {
        $idx   = 0;

        $lines = array();
        while(($line = fgets($fp)))
        {
            $lines[$idx] = $line;
            $idx = ($idx + 1) % $num;
        }

        $p1 = array_slice($lines,    $idx);
        $p2 = array_slice($lines, 0, $idx);
        $ordered_lines = array_merge($p1, $p2);

        return $ordered_lines;
    }


    function log()
    {
        $data = array();
        $fp    = fopen(vsql::$email_conf['log_file'], 'r');
        if ($fp) {
            $data = $this->read_last_lines($fp, 10);
            fclose($fp);
        }

        $this->S->assign('data', $data);
        $this->S->display("insider/mailing_log.html");
    }

    function complete_append_type($results, $type)
    {
        foreach ($results as $nr => $result) {
            $results[$nr]["type"] = $type;
        }

        return $results;
    }

    function complete()
    {
        $term = $_REQUEST["term"];

        // klub (members), grupa adresatów (rights), osoby (users),
        $fields = array('members', 'rights', 'users', 'member_profile', 'custom');

        $result = array();
        foreach ($fields as $f) {
            if(!isset($this->fields[$f])) return;

            if(isset($this->fields[$f]["ref"]))
                $result = array_merge($this->complete_append_type($this->complete_ref($f, $term), $f), $result);

            if($this->fields[$f]["type"] == "list")
                $result = array_merge($this->complete_append_type($this->complete_list($f, $term), $f), $result);

            if($this->fields[$f]["consistency"])
                $result = array_merge($this->complete_append_type($this->complete_consistency($f, $term), $f), $result);

            if($this->fields[$f]["type"] == "flags")
                $result = array_merge($this->complete_append_type($this->complete_flags($f, $term), $f), $result);
        }

        echo json_encode($result);
    }

}
