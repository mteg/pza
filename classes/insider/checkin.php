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

    static function subscribe($to = false, $starting_from = false)
    {
        $id = access::getuid();

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
            ));
        }
    }

    private function useradd($u)
    {
        $i = $_POST;


        $err = $u->validate(0, $i);

        if(!preg_match('/^.*[a-z]+.*@.+\..+$/', $i["email"]))
            $err["email"] = "Podaj prawidłowy adres e-mail";

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
}
