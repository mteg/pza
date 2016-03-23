<?
require_once "swiftmailer/lib/swift_required.php";

//class insider_mailing extends insider_action
class insider_mailing extends insider_table
{
    public $fields = array(
        "members" => array("Kluby", "ref" => "members", "by" => "name"),
        "rights" => array("Uprawnienia", "ref" => "rights", "by" => "name"),
        "users" => array("Osoby", "ref" => "users", "by" => "ref"),
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
        $u = vsql::retr($q = "SELECT m.name, m.$type as result " .
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
            " WHERE r.id in (" . implode(",", $ids) . ")" .
            " AND u.deleted = 0", "");

        return $u;
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

            $type = 'email';
            if (isset($types[$_REQUEST['type']])) {
                $type = $types[$_REQUEST['type']];
            }

            $recipients = array();
            foreach (array('users', 'rights', 'members') as $field) {
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

                if (count($ids)) {
                    $users = $this->$extractor_name($ids, $type);
                    foreach ($users as $user) {
                        if (strlen($user['result']) > 0 && filter_var($user['result'], FILTER_VALIDATE_EMAIL)) {
                            $recipients[] = $user['result'];
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

            $errors = array_merge($errors, $this->$sender_name($recipients, $title, $message));

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
    protected function send_to_phone($recipients, $title, $message)
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
    protected function send_to_email($recipients, $title, $message)
    {
        $errors = array();
        foreach ($recipients as $recipient) {
            $rval = $this->send_email($recipient, $title, $message);
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
            print_r($u);
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
    protected function send_email($recipient, $messageTitle, $messageBody)
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
            ->setFrom(array(vsql::$email_conf['sender_email']))
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

}
