<?

// todo może jednak dozwolic login via e-mail
// todo procedura odzyskiwania
// todo captcha?
// todo logowanie nadawania/odbierania uprawnień użytkownikom
// todo logowanie zmian w klubach użytkownika

class insider_checkin
{
    /* Obiekt Smarty */
    protected $S;


    /* Lekkie powtórzenie z insider_action! */
    function __construct()
    {
        /* Utwórz obiekt Smarty do pracy */
        $this->S = get_Smarty();
        $this->S->assign("request", $_REQUEST);
        $this->S->assign("this", $this);

        if($_SERVER["HTTPS"] != "on")
        {
            header("Location: https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);
            exit;
        }
    }

    function route()
    {
        $this->login();
    }

    private function auth($login, $pw)
    {
        if($udata = vsql::get("SELECT id, login, password FROM users WHERE deleted = 0 AND login = " . vsql::quote($login)))
        {
            $a = explode("|", $udata["password"]);
            if(!isset($a[1])) $a[1] = $udata["login"];

            if($a[0] == md5($a[1] . $pw))
            {
                session_start();
                $_SESSION["user_id"] = $udata["id"];
                $_SESSION["adm_of"] = insider_memberships::adm_of();
                header("Location: /insider/welcome");
                exit;
            }

        }

        return false;
    }

    function login()
    {
        if(isset($_REQUEST["login"]))
        {
            $login = $_REQUEST["login"];
            $pw    = $_REQUEST["password"];

            $err = array();

            if(!strlen($login))
                $err["login"] = "Wprowadź nazwę użytkownika";

            if(!strlen($pw))
                $err["password"] = "Wprowadź hasło";

            if(!count($err))
            {
                $this->auth($login, $pw);
                $err["password"] = "Nieprawidłowa nazwa użytkownika lub hasło.";
            }

            if(strlen($login) || strlen($pw))
                $this->S->assign("err", $err);
        }

        $this->S->display("insider/login.html");
    }

    static function subscribe($to = false, $starting_from = false, $requested_id = 0)
    {
        $id = access::getuid(); $admin_flags = "";
        if(access::has("edit(entitlements)") && $requested_id)
        {
            $id = $requested_id;
            $admin_flags = "R";
        }

        if($starting_from && $starting_from != "0000-00-00")
        {
            /* Terminate all previous memberships */
            // todo trzeba uodpornić na doprowadzenie do start > due
            $yesterday = date("Y-m-d", strtotime($starting_from) - 86400);
            foreach(vsql::retr("SELECT id FROM memberships WHERE deleted = 0 " .
                " AND user = " . vsql::quote($id) .
                " AND due > " . vsql::quote($yesterday), "id", "id") as $ent)
                    vsql::update("memberships", array("due" => $yesterday), $ent);
        }
        else
            $starting_from = date("Y-m-d", time() - 86400);

        if($to)
        {

            // todo to odejmowanie 86400 nie jest do końca kompatybilne ze zmianami stref czasowych...
            // pytanie, czy to duży problem...

            /* Create new membership */
            vsql::insert("memberships", array(
                "member" => $to,
                "user" => $id,
                "starts" => $starting_from,
                "due" => "9999-12-31",
                "flags" => $admin_flags,
            ));
        }
    }

    private function useradd($u)
    {
        $i = $_POST;


        $err = $u->validate(0, $i);

        if(!preg_match('/^.*[a-z]+.*@.+\..+$/', $i["email"]))
            $err["email"] = "Podaj prawidłowy adres e-mail";

        if(!strlen($i["login"]))
            $err["login"] = "Musisz ustawić jakiś login!";

        if($msg = insider_passwd::verify($i["pw1"]))
            $err["pw1"] = $msg;
        else if($i["pw1"] != $i["pw2"])
            $err["pw2"] = "Wprowadzone hasło i jego potwierdzenie różnią się!";

        foreach(array("sex", "district", "country") as $f)
            if(isset($err[$f]) && !strlen($i[$f]))
                unset($err[$f]);

        if($i["member"])
            if(strlen($i["member_from"]))
                if(!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $i["member_from"]))
                    $err["member_from"] = "Niewłaściwa data (RRRR-MM-DD)";

        if(!count($err))
        {
            if($id = $u->update(0, array_intersect_key($i, $u->fields)))
            {
                session_start();

                /* Set password */
                $_SESSION["user_id"] = $id;
                insider_passwd::passwd($id, $i["pw1"]);
                $this->subscribe($i["member"], $i["member_from"]);

                header("Location: /insider/welcome");
            }

        }

        $this->S->assign("err", $err);
    }

    static function member_list()
    {
        return array("" => "-- brak wyboru --") +
            vsql::retr("SELECT id, name FROM members WHERE deleted = 0 ORDER BY name",
                "id", "name");
    }

    function register()
    {
        access::$nologin = true;
        $u = new insider_users(true);

        if(isset($_POST["name"]))
        {
            $this->useradd($u);
            $this->S->assign("data", $_POST);
        }
        else
        {
            $this->S->assign("pw_suggestion", insider_passwd::suggest());
            $this->S->assign("data", array("member_from" => date("Y-m-d", time() - 86400)));
        }

        $this->S->assign("member_list", $this->member_list());

        $this->S->assign("u", $u);
        $this->S->display("insider/register.html");
    }
    function recover()
    {
        access::$nologin = true;
        if(isset($_POST["name"]))
        {
            $code = substr(md5(implode("|",
                array(date("Y-m-d"),
                    $_POST["surname"],
                    $_POST["name"],
                    $_POST["phone"],
                    $_POST["birthdate"],
                    vsql::$smsapi_salt
                    ))), 0, 8);

            $phone = substr(preg_replace('/[^0-9]/', '', $_POST["phone"]), -9);
            $us = vsql::retr("SELECT id, login, phone FROM users WHERE deleted = 0 AND
                    surname = " . vsql::quote($_POST["surname"]) .
                    " AND name = " . vsql::quote($_POST["name"]) .
                    " AND birthdate = " . vsql::quote($_POST["birthdate"]));

            $cnt = count($us);
            foreach($us as $k => $i)
                if(substr(preg_replace('/[^0-9]/', '', $i["phone"]), -9) != $phone)
                    unset($us[$k]);

            $nologin = false;
            reset($us); $uid = key($us);
            if($udata = current($us))
                if(!strlen($udata["login"]))
                    $nologin = true;

            if($nologin) $this->S->assign("nologin", true);

            $err = array();
            if(count($us) > 1)
                $err["phone"] = "Problem: w bazie występuje kilku użytkowników o takich namiarach. Skontaktuj się z administratorem.";
            else if((count($us) == 0) && ($cnt > 0))
                $err["phone"] = "Numer telefonu inny niż w bazie PZA";
            else if($cnt == 0)
                $err["birthdate"] = "Nie znaleziono użytkownika. Niezgodne nazwisko/imię/data urodzenia.";
            else if(isset($_POST["code"]))
            {
                $this->S->assign('showcode', true);
                if(preg_replace('/[^a-f0-9]/', '', strtolower($_POST["code"])) != $code)
                    $err["code"] = "Nieprawidłowy kod.";
                else
                {
                    if($msg = insider_passwd::verify($_POST["pw1"]))
                        $err["pw1"] = $msg;
                    else if($_POST["pw1"] != $_POST["pw2"])
                        $err["pw2"] = "Wprowadzone hasło i jego potwierdzenie różnią się!";

                    if($nologin)
                    {
                        if(!preg_match('/^' . insider_users::$_fields["login"]["regexp"]. '$/', $_POST["login"]))
                            $err["login"] = "Login musi mieć min. 2 znaki, zaczynać się od litery i może składać się tylko ze znaków 0-9, A-Z, a-z. Polskie znaki nie są dozwolone.";
                        if(vsql::get("SELECT id FROM users WHERE deleted = 0 AND login = " . vsql::quote($_POST["login"])))
                            $err["login"] = "Login jest już zajęty";
                    }

                    if(!count($err))
                    {
                        if($nologin)
                            vsql::update("users", array("login" => $_POST["login"]), $uid);
                        insider_passwd::passwd($uid, $_POST["pw1"]);
                        echo "passwd: $uid, " . $_POST["pw1"];
                        $this->S->assign("title", "Zmiana hasła zakończona");
                        $this->S->assign("msg", "Udało się zmienić hasło! Teraz w końcu możesz <a href='/insider/checkin'>zalogować się.</a>");
                        $this->S->display("insider/success.html");
                        return;
                    }


                }
            }
            else if(vsql::retr("SELECT phone FROM recovers WHERE `date` = DATE(NOW()) AND phone = " . vsql::quote($phone) . " LIMIT 2,1"))
                $err["phone"] = "Dzienny limit kodów wysłanych na ten numer (3) został przekroczony.";
            else
            {
                $this->S->assign('showcode', true);
                $this->S->assign("pw_suggestion", insider_passwd::suggest());
                $this->S->assign("number", $phone);
                vsql::query("INSERT INTO recovers SET ts = NOW(), date = NOW(), phone = " . vsql::quote($phone));

//                echo "KODKODKODKODKODKODKODKODKODKOD KOD kod = $code";

                if(!$nologin) $msg = "Login: " . $udata["login"] . " ";
                $msg .= "Kod SMS: " . $code;
                $this->sms_send(array(
                    'username' => 'pezeta',
                    'password' => vsql::$smsapi_pass,
                    'to' => $phone,
                    'from' => 'Eco',
                    'message' => $msg,
                ));
            }
            $this->S->assign("err", $err);
            $this->S->assign("data", $_POST);
        }
        $this->S->display("insider/recover.html");
    }

    function sms_send($params, $backup = false )
    {
        if($backup == true){
            $url = 'https://api2.smsapi.pl/sms.do';
        }else{
            $url = 'https://api.smsapi.pl/sms.do';
        }

        $c = curl_init();
        curl_setopt( $c, CURLOPT_URL, $url );
        curl_setopt( $c, CURLOPT_POST, true );
        curl_setopt( $c, CURLOPT_POSTFIELDS, $params );
        curl_setopt( $c, CURLOPT_RETURNTRANSFER, true );

        $content = curl_exec( $c );
        $http_status = curl_getinfo($c, CURLINFO_HTTP_CODE);

        if($http_status != 200 && $backup == false){
            $backup = true;
            $this->sms_send($params, $backup);
        }

        curl_close( $c );
        return $content;
    }
}
