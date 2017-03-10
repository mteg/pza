<?

class insider_signup extends insider_action
{
    static function user_cats($cats, $event_date)
    {
        $user_info = vsql::get("SELECT sex, birthdate FROM users WHERE id = " . access::getuid());
        $all_cats = vsql::retr("SELECT g.id, g.options, g.name FROM grounds AS g WHERE g.deleted = 0
                                AND " . vsql::id_condition($cats, "g.id") . " ORDER BY g.options");
        $user_cats = array();
        $age = substr($event_date, 0, 4) - substr($user_info["birthdate"], 0, 4);
//        if(substr($user_info["birthdate"], 5) < substr($event_date, 5))
//            $age -= 1;
        foreach($all_cats as $id => $cat)
        {
            foreach(explode("\n", $cat["options"]) as $l)
            {
                $l = trim($l);
                list($ra1, $ra2, $sex) = array_map("trim", explode("|", $l));

                if(strlen($sex))
                    if($sex != $user_info["sex"]) continue;

                if(strlen($ra1) == 4)
                {
                    if(substr($user_info["birthdate"], 0, 4) < $ra1) continue;
                }
                else if(strlen($ra1) == 10)
                {
                    if($user_info["birthdate"] < $ra1) continue;
                }
                else if(is_numeric($ra1))
                {
                    if($age < $ra1) continue;
                }


                if(strlen($ra2) == 4)
                {
                    if(substr($user_info["birthdate"], 0, 4) > $ra2) continue;
                }
                else if(strlen($ra2) == 10)
                {
                    if($user_info["birthdate"] > $ra1) continue;
                }
                else if(is_numeric($ra2))
                {
                    if($age > $ra2) continue;
                }

                $user_cats[$id] = $cat["name"];
            }
        }
        return $user_cats;
    }
    function route()
    {
        $id = $_REQUEST["id"];
        $info = vsql::get("SELECT type, reguntil, remarks, categories, start, `name` FROM
                grounds AS g
                WHERE g.deleted = 0 AND g.id = " . vsql::quote($id));
        @list($family, $details) = explode(":", $info["type"], 2);
        $isreg = vsql::get("SELECT a.id FROM achievements AS a WHERE a.ground = " .
                vsql::quote($id) . " AND a.deleted = 0 AND a.user = " . vsql::quote(access::getuid()), "id", 0);

        $this->S->assign("gi", $info);
        if(!$info)
            $this->S->assign("err", "Nieznana impreza");
        else if($isreg)
            $this->S->assign("err", "Jesteś już zapisany/a na tę imprezę");
        else if($info["reguntil"] == "0000-00-00")
            $this->S->assign("err", "Impreza zamknięta - zapis tylko poprzez administratora.");
        else if($info["reguntil"] < date("Y-m-d"))
            $this->S->assign("err", "Nie można się już zapisać, termin minął dnia " . $info["reguntil"]);
        else
        {
            if($info["categories"])
            {
                $cats = $this->user_cats($info["categories"], $info["start"]);
                $this->S->assign("cats", $cats);
                if(!count($cats))
                    $this->S->assign("err", "Nie ma dostępnych dla Ciebie żadnych kategorii na tej imprezie");
            }
            if($_REQUEST["confirmed"])
            {
                if(!$_REQUEST["consent"])
                    $this->S->assign("err",
                        "Zgoda na przetwarzanie danych jest wymagana, <a href='/insider/signup?id=$id'>ponów zgłoszenie</a>");
                else
                {
                    $cat_list = array($_REQUEST["categ"]);
                    if($_REQUEST["categ"])
                        foreach(explode(",", vsql::get("SELECT categories FROM grounds WHERE id = " . vsql::quote($_REQUEST["categ"]), "categories")) as $x_cat)
                            if(is_numeric($x_cat))
                                $cat_list[] = $x_cat;

                    foreach(array_reverse($cat_list) as $cat)
                        $ach_id = vsql::insert("achievements", array(
                            "user" => access::getuid(),
                            "categ" => $cat,
                            "ground" => $id,
                            "date" => date("Y-m-d"),
                            "flags" => "V",
                        ));
                    $this->S->assign("success", $ach_id);
                }
            }
        }
        $this->S->display("insider/signup.html");
    }
}
