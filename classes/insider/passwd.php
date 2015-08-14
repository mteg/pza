<?

class insider_passwd extends insider_action
{
    static function passwd($userid = 0, $password = "")
    {
        if(!$password) $password = md5(uniqid());

        $login = vsql::get("SELECT login FROM users WHERE id = " . vsql::quote($userid), "login");
        $password = md5($login . $password);

        vsql::update("users", array("password" => "(zmiana hasła)"), $userid);
        vsql::query("UPDATE users SET password = " . vsql::quote($password . "|" . $login) . " WHERE id = " . vsql::quote($userid));
    }

    static function verify($s)
    {
        if(!strlen($s))
            return "Hasło nie może być puste";
        else if(strlen($s) < 8)
            return "Hasło zbyt krótkie (min. 8 znaków!)";
        else if(!preg_match('/[^a-zA-Z]/', $s))
            return "Hasło musi zawierać co najmniej jeden znak specjalny/cyfrę";
        else if(!preg_match('/[a-zA-Z]/', $s))
            return "Hasło musi zawierać co najmniej jedną literę";

        return false;
    }

    static function suggest()
    {
        $f = array_map("trim", file("data/words.txt"));
        $s = array();
        for($i = 0; $i<3; $i++)
        {
            $w1 = array_rand($f);
            $w2 = array_rand($f);
            $s[] = trim($f[$w1] . " " . $f[$w2]);
        }
        return implode(", ", $s);
    }

    function route()
    {
        if(isset($_POST["pw1"]))
        {
            $pw = $_POST["pw1"];
            $err = array();

            if($msg = $this->verify($pw))
                $err["pw1"] = $msg;
            else if($_POST["pw2"] != $pw)
                $err["pw2"] = "Wpisane hasła różnią się";

            if(!count($err))
            {
                $uid = access::getuid();

                if($_REQUEST["id"])
                    if(access::has("god"))
                        $uid = $_REQUEST["id"];

                $this->passwd($uid, $pw);
                $this->success("Zmiana hasła");
            }

            $this->S->assign("data", $_POST);
            $this->S->assign("err", $err);
        }
        if($_REQUEST["id"])
            $this->S->assign("login", vsql::get("SELECT login FROM users WHERE deleted = 0 AND id = " . vsql::quote($_REQUEST["id"]), "login"));
        $this->S->assign("pw_suggestion", $this->suggest());
        $this->S->display("insider/passwd.html");
    }
}
