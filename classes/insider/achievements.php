<?
// todo zunifikować import z rozwiązaniem zastosowanym w content dla spisów treści publikacji (table-import)

/**
 * Klasa obsługująca wszelakie listy "osiągnięć"
 *
 * Osiągnięciami, w zależnosci od typu szranki do której są przypisane, mogą być:
 *  - Przejścia dróg wspinaczkowych
 *  - Przejścia jaskiń
 *  - Udział w zawodach (PZA lub innych)
 *  - Udział w evencie
 *  - Udział w wyprawie
 *  - Zajęcie określonej pozycji w rankingu
 *
 */
    class insider_achievements extends insider_table
    {
        /**
         * Lista pól w tabeli SQL "achievements"
         *
         * Lista jest na ogół modyfikowana przez konstruktor klasy,
         * w zależności od tego, na jakiego rodzaju osiągnięciach
         * użytkownik życzy sobie pracować.
         *
         */
        public $fields = array(
            "creat" =>   array("Data wpisu", "no" => "add,edit"),
            "ground" =>  array("Szranka", "ref" => "grounds_view", "by" => "groundref", "search" => "g.name", "order" => "g.name"),
            "user" =>    array("Osoba", "ref" => "users", "by" => "ref"),
            "role" =>    array("Rola", "type" => "select", "options" =>
                    array(0 => "",
                          1 => "Uczestnik",
                          100 => "Sędzia główny",
                          101 => "Sędzia",
                          110 => "Konstruktor główny",
                          120 => "Delegat PZA",
                          130 => "Trener główny",
                          131 => "Trener"), "search" => "role.name",
                "suppress" => true),
            "date" =>    array("Data", "type" => "date", "suppress" => true),
            "position" =>array("Miejsce", "regexp" => "[0-9]+", "empty" => true, "order" => "IF(CAST(t.position AS signed) = 0, 1, 0) <dir>, CAST(t.position AS signed) <dir>, t.position"),
            "points" =>  array("Punkty", "regexp" => "[0-9]+([,.][0-9]*)?", "suppress" => true, "empty" => true),
            "categ" =>   array("Kategoria", "ref" => "grounds_view", "by" => "groundref", "search" => "cat.name", "order" => "cat.name"),
            "style" =>   array("Styl", "consistency" => true, "suppress" => true),
            "partners" =>array("Partnerzy", "empty" => true, "multiple" => true,
                                "suppress" => true,
                                "comment" => "Wypełniać tylko w przypadku zawodów zespołowych."),
            "duration" =>array("Czas", "suppress" => true, "comment" => "Na przykład: <i>2d 10h</i> albo <i>3.31s</i>"),
            "flags" =>   array("Opcje", "type" => "flags", "options" => array(
                "V" => "Widoczne publicznie",
                "K" => "Kierownik akcji",
                "N" => "Nurek",
            /*    "C" => "Opłacone wpisowe"*/
            )),
            "place" =>   array("Osiągnięty punkt", "consistency" => true, "suppress" => true),
            "comments" =>array("Uwagi", "type" => "area", "suppress" => true),
        );
        /**
         * Stałe do konwersji czasów przejść przez metodę sec2dur();
         */
        private static $intervals = array("d" => 86400, "h" => 3600, "m" => 60, "s" => 1);

        /**
         * Rodzaj osiągnięć (patrz rodzaj szranki w grounds.php), którymi aktualnie
         * się zajmujemy.
         */
        private $ground_type = "";

        protected function access($perm)
        {
            /*
                Każdy zalogowany użytkownik posiada bardzo dużo możliwości
                jeśli chodzi o własne osiągnięcia.
            */
            if(in_array($perm, explode(",", "search,add,edit,view,export,delete,signup")))
                return true;


            return parent::access($perm);
        }

        /**
         * Funkcja pomocnicza dla konstruktora klasy
         *
         * Smutna powtórka z grounds.php!!
         */
        // todo może coś się da z tym zrobić? powtórka z grounds.php!
        private function remove_fields($a)
        {
            if(!is_array($a))
                $a = explode(",", $a);
            foreach($a as $ent)
                unset($this->fields[$ent]);

            foreach($this->columns as $n => $name)
                if(in_array($name, $a))
                    unset($this->columns[$n]);
        }

        /**
         * Funkcja pomocnicza dla konstruktora
         */
        private function add_columns($c)
        {
            foreach(explode(",", $c) as $col)
                $this->columns[] = $col;
        }

        /**
         *
         * Klasa posiada trzy tryby pracy:
         *   - Wszystkie osiągnięcia (mało czytelne)
         *   - Osiągnięcia w ramach konkretnego typu osiągnięć (np. przejścia jaskiniowe)
         *   - Osiągnięcia w ramach konkretnej szranki (np. przejścia Jaskini Czarnej)
         *
         * W dwóch ostatnich trybach lista pól, kolumn, filtrów, domyślny porządek
         * są dostosowywane do typu osiągnięć (typu szranki).
         *
         */
        function __construct()
        {
            /*
               Trzy tryby pracy
                 - wszystko
                 - rodzina szranek
                 - konkretna szranka
            */

            $ginfo = array();
            if(is_numeric($ground = $_REQUEST["ground"]))
            {
                $ginfo = vsql::get("SELECT type, start, name, city, start AS date, categories,
                    IF(reguntil >= DATE(NOW()), 1, 0) AS open
                    FROM grounds WHERE deleted = 0 AND id = " . vsql::quote($ground));
                if($ginfo)
                    $type = $ginfo["type"];
                else
                    $type = "";
                $this->columns = array();
            }
            else
            {
                $type = $_REQUEST["type"];
                $this->columns = array("ground");
            }
            $this->ground_type = $type;

            $this->columns["surname"] = array("Nazwisko", "order" => "u.surname", "search" => "u.surname");
            $this->columns["name"] = array("Imię", "order" => "u.name", "search" => "u.name");
            if($this->has_signup_restrictions())
                $this->columns["member"] = array("Klub", "order" => "m.name", "search" => "m.name");

            list($family, $fine) = explode(":", $type);
            if($family != "event")
                $this->remove_fields("creat");

            if($_REQUEST["user"] == "self")
                unset($this->fields["user"]);

            switch($family)
            {
                case "event":
                case "course":
                    foreach(array("K", "N", "C") as $f)
                        unset($this->fields["flags"]["options"][$f]);
                    $this->remove_fields("place,points,position,duration,style,date,partners,categ,role");
                    $ground_name = $family == "course" ? "Szkolenie" : "Zgrupowanie";
                    $this->order = "surname, name";
                    $this->add_columns("creat");
                    $this->columns["email"] = array("E-mail", "order" => "u.email", "search" => "u.email");
                    $this->columns["phone"] = array("Telefon", "order" => "u.phone", "search" => "u.phone");
                    break;

                case "rank":
                    if(isset($ginfo["name"]))
                    {
                        $this->title = "Ranking: " . htmlspecialchars($ginfo["name"]);
                        $this->subtitle = "Aktualizacja z dn. " . $ginfo["start"];
                    }
                    else
                        $this->add_columns("date");

                    $this->add_columns("position,points");
                    break;

                case "comp":
                    foreach(array("K", "N") as $f)
                        unset($this->fields["flags"]["options"][$f]);

                    if(!access::has("compadm"))
                    {
                        unset($this->buttons["<classpath>/add"]);
                        unset($this->actions["<classpath>/delete"]);
                        unset($this->actions["<classpath>/edit"]);
                        unset($this->buttons["<classpath>/export"]);
                        if($ground)
                            if($ginfo["open"])
                                $this->buttons["/insider/signup?id=" . $ground] = array("Zapisz się", "target" => "_self", "icon" => "signal");
                    }


                    if(isset($ginfo["name"]))
                        $this->title = "Zawody: " . htmlspecialchars($ginfo["name"]) . ", " . htmlspecialchars($ginfo["city"]) . " (" . htmlspecialchars($ginfo["date"]) . ")";

                    $this->remove_fields("place");
                    if($categs = $ginfo["categories"])
                    {
                        $cat_list = vsql::retr("SELECT id, name FROM grounds WHERE deleted = 0 AND " . vsql::id_condition($categs, "id"), "id", "name");
                        $this->fields["categ"]["type"] = "select";
                        $this->fields["categ"]["options"] = $cat_list;
                        unset($this->fields["categ"]["ref"]);
                    }

                    if($_REQUEST["list"])
                    {
                        $this->remove_fields("points,position,duration,style,date");
                        $this->order = "surname, name";
                        if(access::has("import(achievements)") && $ground) {
                            $this->buttons["<classpath>/load"] =
                                array("Import", "target" => "_blank",
                                    "icon" => "arrow-1-s");

                        }
                        $this->buttons["<classpath>/results"] = array("Wyniki", "target" => "_self", "icon" => "signal");
                        $this->subtitle = "Lista startowa";
                        $this->columns["member"] = array("Klub", "field" => "lm.member");
                        if(access::has("entlmgr(med:*)"))
                            $this->columns["med_date"] = array("Badania", "field" => "le.due");
                    }
                    else
                    {
                        $this->add_columns("duration,position,points");
                        $this->order = "categ, position, points, surname, name";
                        $this->buttons["<classpath>/competitors"] = array("Lista startowa", "target" => "_self", "icon" => "list");
                        if(access::has("import(achievements)") && $ground) {
                            $this->buttons["<classpath>/import"] =
                                array("Import wyników", "target" => "_blank",
                                    "icon" => "arrow-1-s");
                            $this->buttons["<classpath>/set"] =
                                array("Edycja wyników", "target" => "_blank",
                                    "icon" => "arrow-1-s");

                            $this->actions["<classpath>/score"] =
                                array("Przypisz punkty", "target" => "_top", "multiple" => true);
                        }
                        $this->subtitle = "Wyniki";
                    }

                    if($ground)
                    {
                        $cat_list = vsql::retr("SELECT g.id, CONCAT(g.name, ' (', COUNT(a.id), ')') AS capt
                                     FROM achievements AS a
                                      JOIN grounds AS g ON g.id = a.categ AND g.deleted = 0
                                      WHERE a.role < 100 AND a.ground = " . vsql::quote($ground) .
                        " AND a.deleted = 0 GROUP BY g.id ORDER BY g.name", "id", "capt");
                        if(isset($this->fields["categ"]["options"]))
                            $cat_list += $this->fields["categ"]["options"];

                        if(count($cat_list))
                        {
                            $this->main_selector = "categ";
                            $this->main_selection = $cat_list + array("" => "--- wszystkie ---");
                        }
                        else
                            $this->add_columns("categ");
                    }
                    else
                        $this->add_columns("categ");

                    $ground_name = "Impreza";

                    $this->fields["partners"]["ref"] = "users";
                    $this->fields["partners"]["by"] = "ref";

                    if($role = $_REQUEST["lrole"])
                    {
                        if($role == "nf")
                        {
                            unset($this->fields["role"]);
                            $this->buttons["<classpath>/officials"] = array("Osoby oficjalne", "target" => "_self", "icon" => "signal");
                            if($ground)
                                $this->buttons["/insider/grounds/score?id=" . $ground] = array("Przelicz punkty", "target" => "_top", "icon" => "signal", "ask" => "Przeliczyć punkty?");
                        }
                        else if($role == "f")
                        {
                            foreach($this->fields['role']['options'] as $rid => $rname)
                                if($rid <= 100) unset($this->fields['role']['options'][$rid]);
                            $this->remove_fields("partners,categ,flags");
                            $this->remove_fields("points,position,duration,style,date");
                            $this->add_columns("role");
                            $this->subtitle = "Osoby oficjalne";
                        }
                    }


                    break;

                case "nature":
                    unset($this->fields["flags"]["options"]["C"]);
                    $this->remove_fields("position,points,categ,role");
                    unset($this->fields["partners"]["comment"]);

                    if($fine == "cave")
                    {
                        $this->title = "Przejścia jaskiniowe";
                        $this->fields["style"][0] = "Charakter przejścia";
                        $this->fields["style"]["type"] = "list";
                        $this->fields["style"]["multiple"] = ",";
                        $this->fields["style"]["options"] = $o = placelist::get("cave_styles");
                        $this->fields["style"]["comment"] =
                            "Wpisz jeden z dozwolonych charakterów przejścia: " .
                            "<i>" . implode(", ", $o) . "</i>" .
                            ". Możesz wpisać wiele charakterów przejścia, oddzielając je
                             znakiem przecinka. Wiodący charakter wpisz jako pierwszy.";

                        $ground_name = "Jaskinia";
                    }
                    else
                    {
                        $this->title = "Przejścia wspinaczkowe";
                        foreach(array("K", "N") as $f)
                            unset($this->fields["flags"]["options"][$f]);

                        $this->remove_fields("place");
                        $ground_name = "Droga";
                        
                        if($_REQUEST["extview"])
                        {
                          $this->columns["summit"] = array("Szczyt", "order" => "g.summit", "search" => "g.summit");
                          $this->columns["country"] = array("Kraj", "order" => "g.country", "search" => "g.country");
                          $this->columns["region"] = array("Rejon", "order" => "g.region", "search" => "g.region");
                          $this->columns["difficulty"] = array("Trudności", "order" => "g.difficulty", "search" => "g.difficulty");
                          if($this->columns[0] == "ground")
                          {
                            unset($this->columns[0]);	                       
                            $this->add_columns("ground,comments");
                          } 
                        }
                        else if(!(isset($_REQUEST["user"]) || isset($_REQUEST["ground"]))) 
                          $this->buttons["<classpath>?type=" . urlencode($type) . "&extview=1"] = array("Widok rozszerzony", "target" => "_self");
                    }
                    $this->add_columns("date,style,duration");
                    $this->order = "date DESC, surname, name";



                    break;

                case "exp":
                    foreach(array("K", "N", "C") as $f)
                        unset($this->fields["flags"]["options"][$f]);
                    $this->remove_fields(array("date", "position", "points", "categ", "style", "partners", "duration", "place", "role"));
                    $this->order = "surname, name";
                    $ground_name = "Wyprawa";
                    break;

                default:
                    /* Wszystko! */
                    $this->add_columns("date");
                    $this->order = "date";
                    $ground_name = "Szranka";
                    break;
            }
            if(isset($this->fields["ground"]))
                $this->fields["ground"][0] = $ground_name;


            parent::__construct();
            unset($this->filters["duration"]);
            if((!access::has("search(achievements")) && $family != "exp")
            {
                /*
                unset($this->filters["surname"]);
                unset($this->filters["name"]);
                unset($this->columns["surname"]);
                unset($this->columns["name"]);
                */
                unset($this->fields["user"]);
            }

            if(isset($_REQUEST["ground"]))
                $this->S->assign("disabled", array("ground" => 1));
        }

        protected function retr_query($filters)
        {
            $columns = array();
            $columns[] = "t.date";

            $joins = array();

            if($_REQUEST["lrole"] == "f")
            {
                $joins[] = "LEFT JOIN ground_roles AS role ON role.id = t.role";
                $columns[] = "role.name AS role";
            }

            if($_REQUEST["list"] == 1)
            {
                $med_e = vsql::retr("SELECT id FROM rights WHERE deleted = 0 AND short LIKE 'med:%'", "id", "id");
                $joins[] = "LEFT JOIN memberships AS lme ON lme.user = u.id AND lme.deleted = 0 AND lme.starts <= g.start AND lme.due >= g.finish ";
                $joins[] = "LEFT JOIN members AS lm ON lm.id = lme.member AND lm.deleted = 0 ";
                $joins[] = "LEFT JOIN entitlements AS le ON le.deleted = 0 AND le.user = u.id AND le.starts <= g.start AND le.due >= g.finish AND " . vsql::id_condition($med_e, "le.right");
                $columns[] = "lm.name AS member";
                $columns[] = "IFNULL(MAX(le.due), '---') AS med_date";
            }

            $query = "SELECT SQL_CALC_FOUND_ROWS t.id, t.creat, g.name AS ground, u.surname AS surname, u.name AS name, u.email, u.phone,
                             t.position, t.points, cat.name AS categ, t.duration, t.date," .
                            ($this->has_signup_restrictions() ? "m.name AS member," : "") .
                            "IF(g.type = 'nature:cave', REPLACE(t.style, ',', '\n'), t.style) AS style, g.summit, g.country, g.region, g.difficulty, " .
                            implode(",", $columns) .
                            " FROM achievements AS t
                             LEFT JOIN grounds AS g ON g.id = t.ground AND g.deleted = 0
                             LEFT JOIN grounds AS cat ON cat.id = t.categ AND t.deleted = 0 " .
                            " JOIN users AS u ON u.id = t.user AND u.deleted = 0 " .
                            implode(" ", $joins) .
                            ($this->has_signup_restrictions() ?
                                (" LEFT JOIN memberships AS me ON me.user = u.id AND u.deleted = 0 AND me.starts <= NOW() AND me.due >= NOW() " .
                                 " LEFT JOIN members AS m ON m.id = me.member AND m.deleted = 0 ") : ""
                            ) .
                             " WHERE t.deleted = 0 " .  $filters;

            if(is_numeric($ground = $_REQUEST["ground"]))
                $query .= " AND g.id = " . vsql::quote($ground);

            if($type = $_REQUEST["type"])
                $query .= " AND g.type = " . vsql::quote($type);

            if($role = $_REQUEST["lrole"])
            {
                if($role == "nf")
                    $query .= " AND t.role < 100";
                else if($role == "f")
                    $query .= " AND t.role >= 100";
            }

            if(isset($_REQUEST["selector"]))
                if(strlen($_REQUEST["selector"]))
                    $query .= " AND t.categ = " . vsql::quote($_REQUEST["selector"]);


            $user = $_REQUEST["user"];

            if(!access::has("search(achievements)"))
            {
/*                $query .= insider_users::pub_conditions("u."); */
                $query .= " AND (u.id = " . vsql::quote(access::getuid()) .
                            " OR t.flags LIKE '%V%') ";
            }

            if($user)
            {
                if($user == "self") $user = access::getuid();
                $query .= " AND t.user = " . vsql::quote($user);
            }

            $query .= " GROUP BY t.id ";

            return $query;
        }

        public function retr($offset = 0, $limit = 50, $filters = false)
        {
            $res = parent::retr($offset, $limit, $filters);
            foreach($res as $n => $row)
                $res[$n]["duration"] = $this->sec2dur($row["duration"]);

            return $res;
        }


        protected function complete_constraints($f)
        {
            if($f == "ground")
            {
                if($type = $_REQUEST["type"])
                    return " AND type = " . vsql::quote($type);

                return "";
            }

            if($f == "user")
                return "";

            if($f == "partners")
                if(!access::has("search(users)"))
                    return " AND flags LIKE '%B%'";

            if($f == "place")
                if(is_numeric($ground = $_REQUEST["ground"]))
                    return " AND ground = " . vsql::quote($ground);

            if($f == "categ")
            {
                if($ground = $_REQUEST["ground"])
                    if($categs = vsql::get("SELECT categories FROM grounds WHERE deleted = 0 AND id = " . vsql::quote($ground), "categories"))
                        return " AND " . vsql::id_condition($categs, "id");

                return "";
            }

            return "";
        }

        protected function defaults()
        {
            $data = array();
            // todo a może jeszcze jakieś defaulty?


            if(access::has("add(achievements)"))
                $data["flags"] = "V";
            else
                $data["flags"] = vsql::get("SELECT id FROM users WHERE id = " . access::getuid() .
                                    " AND flags LIKE '%A%'", "id") ? "V" : "";


            if(is_numeric($ground = $_REQUEST["ground"]))
            {
                $data["ground"] = $this->fetch_field("ground", $ground, true);
                if(fnmatch("nature:*", $this->ground_type))
                    $data["user"] = $this->fetch_field("user", access::getuid(), true);
                else if(fnmatch("comp:*:other", $this->ground_type))
                    $data["date"] = vsql::get("SELECT start FROM grounds WHERE id = " . vsql::quote($ground), "start", "");
            }

            if($user = $_REQUEST["user"])
                $data["user"] = $user;

            return $data;
        }

        protected function validate($id, &$data)
        {
            if(!access::has("edit(achievements)"))
            {
                $data["user"] = access::getuid();
                if($id)
                    if(vsql::get("SELECT user FROM achievements WHERE id = " . vsql::quote($id), "user") != $data["user"])
                        return array("user" => "Nie masz prawa do edytowania tego wpisu");

            }
            else if((!$id) && !$data["user"])
                $data["user"] = access::getuid();

            if(is_numeric($ground = $_REQUEST["ground"]))
            {
                $data["ground"] = $ground;
                if($id) unset($data["ground"]);
            }
            else
                $ground = $data["ground"];

            if(preg_match('/^[0-9]*,[0-9]+$/', $data["duration"]))
                $data["duration"] = strtr($data["duration"], array("," => "."));

            $err = parent::validate($id, $data);
            if(isset($data["duration"]))
                if(($data["duration"] = $this->dur2sec($data["duration"])) == -1)
                    $err["duration"] = "Niewłaściwy czas";

            /* Wymagania poszczególnych typów szranek */
            $type = vsql::get("SELECT type FROM grounds WHERE id = " .
                    vsql::quote($ground), "type");

            list($family, $junk) = explode(":", $type, 2);

            if($family == "nature")
            {
                if(!$data["duration"])
                    $err["duration"] = "Należy wskazać czas przejścia";

                if(!$data["style"])
                    $err["style"] = "Należy wskazać styl przejścia";

                if($type == "nature:cave")
                    if(!$data["place"])
                        $err["place"] = "Należy wskazać osiągnięty punkt";
            }

            if($this->has_signup_restrictions() && !count($err))
            {
                /* Restrykcje dotyczące tylko zwykłych użytkowników */
                if(!access::has("add(achievements)"))
                {
                    /* Czy jest PESEL w profilu? */
                    if(vsql::get("SELECT pesel FROM users WHERE deleted = 0 AND pesel != '' AND id = " . vsql::quote(access::getuid())))
                        $err["ground"] = "Uzupełnij PESEL w swoim profilu aby móc zapisywać się na zawody";

                    /* Czy można się jeszcze zapisać? */
                    if(vsql::get("SELECT id FROM grounds WHERE start <= NOW() AND id = " . vsql::quote($ground)))
                        $err["ground"] = "Nie możesz już samodzielnie zapisać się na te zawody (za późno!)";

                }

                /* Czy nie jest już raz zapisany? */
                if($data["user"])
                    if(vsql::get("SELECT id FROM achievements WHERE deleted = 0 AND ground = " . vsql::quote($ground) .
                                    " AND id != " . vsql::quote($id) .
                                    " AND user = " . vsql::quote($data["user"]) . " AND categ = " .
                                    vsql::quote($data["categ"])))
                        $err["ground"] = "Użytkownik jest już zapisany na te zawody w tej kategorii";

                /* Restrykcje dotyczące miejsca */
                if($data["position"])
                    if(!is_numeric($data["position"]))
                        if(!in_array($data["position"], $list = placelist::get("special_positions")))
                            $err["position"] = "Nieprawidłowe miejsce, dozwolona liczba lub " . implode(", ", $list);
            }

            return $err;
        }


        private function dur2sec($d)
        {
            $d = strtr($d, array(
                " " => "",
                "dni" => "d",
                "dzień" => "d",
                "dzien" => "d",
                "godzina" => "h",
                "godzin" => "h",
                "minut" => "m",
                "godziny" => "h",
                "minuty" => "m",
                "min" => "m",
                "sek" => "s",
                "sec" => "s")); $m = array(); $t = 0;

            while(preg_match('/^([0-9]+(\.[0-9]+)?)([dhms])/', $d, $m))
            {
                $t += $m[1] * static::$intervals[$m[3]];
                $d = substr($d, strlen($m[0]));
            }

            if(is_numeric($d))
                $t += $d;
            else if(strlen($d))
                return -1;

            return $t;
        }

        private function sec2dur($d)
        {
            $t = array();
            foreach(static::$intervals as $sh => $m)
            {
                $n = floor($d / $m);
                if(count($t) || $n > 0)
                    $t[$sh] = $n;

                $d -= $n * $m;
            }

            /* Pozostały ułamek sekund */
            $t["s"] += $d;

            foreach(array_reverse(array_keys(static::$intervals)) as $sh)
            {
                if($t[$sh] != 0) break;
                unset($t[$sh]);
            }

            foreach($t as $sh => $amt)
                $t[$sh] = $amt . $sh;

            return implode(" ", $t);
        }


        protected function fetch_field($f, $data_f, $resolve = false)
        {
            if($f == "duration")
                return $this->sec2dur($data_f);
            return parent::fetch_field($f, $data_f, $resolve);
        }

        protected function fetch($id, $resolve = false, $nl = false, $extra = "")
        {
            if(!access::has("view(achievements)"))
                $extra .= " AND (user = " . vsql::quote(access::getuid()) .
                            " OR flags LIKE '%V%') ";

            return parent::fetch($id, $resolve, $nl, $extra);
        }

        private function has_signup_restrictions()
        {
            return false;
            return (fnmatch("comp:*:pza", $this->ground_type) ||
                    fnmatch("event:*", $this->ground_type));
        }

        private function restrict_edits()
        {
            if($this->has_signup_restrictions())
            {
                if(!access::has("add(achievements)"))
                    $this->remove_fields("date,position,points,duration,style,flags");
            }
        }

        public function add()
        {
            $this->restrict_edits();
            parent::add();
        }

        public function edit()
        {
            $this->restrict_edits();
            parent::edit();
        }

        protected function process_import($lines, $cols, $update = false)
        {
            $fields = array_flip(explode(",", "position,points,duration"));
            foreach($lines as $no => $l)
            {
                $a = explode("\t", trim($l));
                if(!strlen($l)) continue;

                if($l{0} == "#")
                {
                    $lines[$no]["status"] = "x";
                    continue;
                }

                $data = array();
                foreach(array_keys($cols) as $k)
                    if(is_numeric($n = $_REQUEST[$k]))
                        $data[$k] = $a[$n];

                $lines[$no] = array("text" => $l);

                foreach($data as $k => $i)
                    if(preg_match('/^".*"$/', $i))
                        $data[$k] = stripslashes(substr($i, 1, strlen($i) - 2));

                if(!is_numeric($id = $data["id"]))
                {
                    $lines[$no]["status"] = "x";
                    continue;
                }

                if(!vsql::get("SELECT a.id FROM achievements AS a
                        JOIN users AS u ON u.deleted = 0 AND a.user = u.id
                          AND u.surname = " . vsql::quote($data["surname"]) .
                        " WHERE a.deleted = 0 AND a.id = " . vsql::quote($id)))
                {
                    $lines[$no]["status"] = "u";
                    continue;
                }

                if(count($lines[$no]["err"] = $this->validate($id, $data)))
                    $lines[$no]["status"] = "e";
                else
                {
                    $lines[$no]["status"] = "g";
                    if($update)
                        $this->update($id, array_intersect_key($data, $fields));
                }
            }
            $this->S->assign("lines", $lines);
        }

        private function import_get_cols()
        {

            $cols = array(); $col_list = array();
            for($i = 0; $i<20; $i++)
                $col_list[$i] = "Kolumna " . ($i + 1) . " (" . chr(ord("A") + $i) . ")";

            foreach(array(
                        "id" => "Identyfikator zapisu",
                        "surname" => "Nazwisko",
                        "duration" => "+Czas",
                        "position" => "+Miejsce",
                        "points" => "+Punkty",
                    ) as $id => $capt)
                $cols[$id] =
                    array("name" => strtr($capt, array("+" => "")),
                        "type" => "select",
                        "options" => array_merge(
                            $capt{0} == "+" ? array("" => "-- brak --") : array(),
                            $col_list));

            return $cols;
        }

        public function import()
        {
            access::ensure("import(achievements)");

            if(!($r = $_REQUEST["ground"]))
                die("ERR Nie wskazano identyfikatora zawodów");
/*
            header("Content-type: text/plain; charset=utf-8");
            print_r($_FILES);
            print_r($_REQUEST);
            exit;
*/
            $cols = $this->import_get_cols(); $colparams = array();

            foreach(array_keys($cols) as $k)
                if(isset($_REQUEST[$k]))
                    $colparams[] = $k . "=" . urlencode($_REQUEST[$k]);

            $this->S->assign("colparams", implode("&", $colparams));

            $res_text = array();
            if(isset($_FILES["file"]))
            {
                if (!file_exists($fname = $_FILES["file"]["tmp_name"]))
                    $err["file"] = "Problem z przesłaniem pliku (" . $_FILES["file"]["error"] . ")";
                else
                    $res_text = file($fname);
            }

            if(!count($res_text)) $res_text = explode("\n", $_REQUEST["results"]);

            if(strlen(trim(implode("", $res_text))))
            {
                $this->process_import($res_text, $cols);
                $this->w("achievements_import2.html");
                exit;
            }

            if(isset($_REQUEST["lines"]))
            {
                $this->process_import($_REQUEST["lines"], $cols, true);
                $this->w("achievements_import3.html");
                exit;
            }

            $this->S->assign("cols", $cols);
            if(isset($_REQUEST["id"]))
                $this->S->assign("data", $_REQUEST);
            else
                $this->S->assign("data", array("id" => 0, "surname" => 2, "position" => 4));
            $this->w("achievements_import.html");
        }

        private function process_load($res, $gnd, $cat)
        {
            $res = strtr($res, array("\r" => "\n", "   " => "\t", ";" => "\t", "/" => "\t", "," => "\t",  ":" => "\t"));
            $lines = array();
            foreach(explode("\n", $res) as $ent)
            {
                $ent = trim($ent); if(!strlen($ent)) continue;
                $a = array_map("trim", explode("\t", $ent));
                if(count($a) != 4)
                {
                    $lines[$ent] = "$ent: Niewłaściwa liczba kolumn (jest: " . count($a) . ", ma być: 4)";
                    continue;
                }
                list($surname, $name, $date, $phone) = $a;
                $date = date("Y-m-d", strtotime($date));
                if(!count($os = vsql::retr("SELECT id, CONCAT(surname, ' ', name, ' (', birthdate, ')') AS descr FROM users WHERE deleted = 0 AND surname = " . vsql::quote($surname) . " AND name = " . vsql::quote($name) . " AND birthdate = " . vsql::quote($date), "id", "descr")))
                    $os = vsql::retr("SELECT id, CONCAT(surname, ' ', name, ' (', birthdate, ')') AS descr FROM users WHERE deleted = 0 AND birthdate = " . vsql::quote($date) . " AND phone = " . vsql::quote($phone), "id", "descr");

                $othopts= array("" => "--- Nie dodawaj",
                            "n" . $surname . "/" . $name . "/" . $date . "/" . $phone => "--- Dodaj nową osobę"
                );

                if(count($os) == 1)
                    $os = $os + $othopts;
                else
                    $os = $othopts + $os;
                $lines[$ent] = $os;
            }
            return $lines;
        }

        public function load()
        {
            access::ensure("import(achievements)");

            if(!($gnd = $_REQUEST["ground"]))
                die("ERR Nie wskazano identyfikatora zawodów");
            if(!($cat = $_REQUEST["selector"]))
                die("ERR Nie wskazano kategorii");

            $this->S->assign(array(
                "cinfo" => vsql::get("SELECT * FROM grounds WHERE deleted = 0 AND id = " . vsql::quote($cat)),
                "ginfo" => vsql::get("SELECT * FROM grounds WHERE deleted = 0 AND id = " . vsql::quote($gnd)),
            ));


            if(isset($_REQUEST['results']))
                $res = $_REQUEST["results"];
            else
                $res = "";

            if(strlen(trim($res)))
            {
                $lines = $this->process_load($res, $gnd, $cat);

                /*

                print_r($lines);
                print_r($err);

                */

                $this->S->assign("lines", $lines);

                $this->w("achievements_load2.html");
                exit;
            }
            else if(isset($_REQUEST["lines"]))
            {
                foreach($_REQUEST["lines"] as $ent)
                {
                    if(is_numeric($ent))
                        vsql::insert("achievements", array("ground" => $gnd, "categ" => $cat, "user" => $ent));
                    else if($ent{0} == "n")
                    {
                        list($surname, $name, $dob, $phone) = explode("/", substr($ent, 1));
                        $u = new insider_users;
                        $ent = $u->update(0, array("surname" => $surname, "name" => $name, "birthdate" => $dob, "phone" => $phone));
                        vsql::insert("achievements", array("ground" => $gnd, "categ" => $cat, "user" => $ent));
                    }
                }
                header("Location: /insider/achievements?ground=$gnd&selector=$cat");
                exit;
            }


            $this->w("achievements_load.html");
        }




        public function set()
        {
            access::ensure("import(achievements)");

            if (!($r = $_REQUEST["ground"]))
                die("ERR Nie wskazano identyfikatora zawodów");

            $form = array();
            $orig_data = vsql::retr("SELECT u.surname, u.name, u.birthdate, a.id, a.position, a.points, a.duration, c.name AS category
                            FROM achievements AS a 
                             JOIN users AS u ON u.id = a.user  AND u.deleted = 0
                             JOIN grounds AS c ON c.id = a.categ AND c.deleted = 0
                              WHERE a.ground = " . vsql::quote($r) . " ORDER BY c.name, a.position, u.surname, u.name");
            foreach($orig_data as $id => $i) {
                $form[$i["category"]][$id] = $i;
                $orig_data[$id]["duration"] = $this->sec2dur($i["duration"]);
            }

            $this->S->assign("form", $form);
            if($_SERVER["REQUEST_METHOD"] == "POST")
            {
                $data = $_REQUEST["data"]; $err = array(); $updates = array();
                $data = array_intersect_key($data, $orig_data);
                vsql::query("START TRANSACTION");
                foreach($data as $osid => $info)
                {
                    $updata = array(); $od = $orig_data[$osid];
                    foreach(array("position", "duration", "points") as $k)
                        if($info[$k] != $od[$k])
                        {
                            if($k == "duration") {
                                if (($info[$k] = $this->dur2sec($info[$k])) == -1) {
                                    $err[$osid] = "Nieprawidłowy wpis dla '" . $orig_data[$osid]['surname'] . "', " . $data[$osid][$k];
                                }
                            }
                            else if(!is_numeric($info[$k]))
                                $err[$osid] = "Nieprawidłowy wpis dla '" . $orig_data[$osid]['surname'] . "', " . $data[$osid][$k];

                            if(!isset($err[$osid]))
                                $updata[$k] = $info[$k];
                        }

                    if(count($updata))
                        $updates[$osid] = $updata;
                }

                if(count($err))
                {
                    vsql::query("ROLLBACK");
                    $this->S->assign("data", $data);
                    $this->S->assign("err", $err);
                }
                else
                {
                    foreach($updates as $osid => $updata)
                        $this->update($osid, $updata);

                    vsql::query("COMMIT");
                    header("Location: /insider/achievements?lrole=nf&ground=" . $_REQUEST["ground"]);
                    exit;
                }
            }
            else
                $this->S->assign("data", $orig_data);
            /*
            if(1) {
                header("Content-type: text/plain; charset=utf-8");
                print_r($form);
                exit;
            }
            */

            $this->w("achievements_set.html");
        }
        public function results()
        {
            header("Location: /insider/achievements?lrole=nf&ground=" . $_REQUEST["ground"]);
            exit;
        }

        public function officials()
        {
            header("Location: /insider/achievements?lrole=f&ground=" . $_REQUEST["ground"]);
            exit;
        }

        public function competitors()
        {
            header("Location: /insider/achievements?lrole=nf&list=1&ground=" . $_REQUEST["ground"]);
            exit;
        }

        protected function capt($id)
        {
            return vsql::get("SELECT CONCAT(u.ref, ' * ', g.name) AS capt FROM
                  achievements AS a
                  LEFT JOIN users AS u ON u.id = a.user
                  LEFT JOIN grounds AS g ON g.id = a.ground
                  WHERE a.id = " . vsql::quote($id), "capt", "");
        }

        public function delete()
        {
            $id = $_REQUEST["id"];
            if(!access::has("delete(achievements)"))
            {
                if(vsql::id_retr($id, "a.id", "SELECT a.id FROM achievements AS a
                      WHERE a.deleted = 0 AND a.creat_by != " . vsql::quote(access::getuid()) .
                    " AND "))
                    $this->json_fail("Nie możesz usunąć: nie posiadasz prawa do usuwania obiektów nie dodanych przez siebie");

                if(vsql::id_retr($id, "a.id", "SELECT a.id FROM achievements AS a
                      JOIN grounds AS g ON g.id = a.ground AND g.type LIKE 'comp:%:pza'
                      WHERE a.deleted = 0 AND "))
                    $this->json_fail("Nie możesz usunąć: tylko uprawnieni użytkownicy mogą wypisywać z zawodów PZA.");
            }

            parent::delete();
        }

        static function score($ids = false)
        {
            access::ensure("scoring");
            $score_map = array(
                0, 100, 80, 65, 55, 51, 47, 43, 40, 37, 34,
                31, 28, 26, 24, 22, 20, 18, 16, 14, 12,
                10, 9, 8, 7, 6, 5, 4, 3, 2, 1
            );

            if(!is_array($ids)) $ids = $_REQUEST["id"];

            $cnt = 0;
            $results = vsql::id_retr($ids, "id", "SELECT id, position, points, ground, categ FROM achievements WHERE deleted = 0 AND ");

            if($_REQUEST["debug"])
                header("Content-type: text/plain; charset=utf-8");

            /* Grounds/categories */
            $gcats = array();
            foreach($results as $i)
                $gcats[$i["ground"]] = array();

            /* Per-category, per-ground, per-position counts */
            foreach(vsql::retr($qry = "SELECT COUNT(DISTINCT a.id) AS n, a.ground, a.categ, a.position
                    FROM achievements AS a
                    JOIN users AS u ON u.id = a.user AND u.deleted = 0 WHERE a.deleted = 0 AND " .
                    vsql::id_condition(array_keys($gcats), "ground") . " GROUP BY a.ground, a.categ, a.position", "") as $e)
                $gcats[$e["ground"]][$e["categ"]][$e["position"]] = $e["n"];
/*
            print_r($gcats);
            echo $qry;
*/

            foreach($results as $i)
            {
                $pos = $i["position"];
                if(!is_numeric($pos))
                    $points = 0;
                else if($pos <= 0)
                    $points = 0;
                else if(!isset($score_map[$pos]))
                    $points = 0;
                else
                {
                    $n = $gcats[$i["ground"]][$i["categ"]][$pos];
                    if(!is_numeric($n)) $n = 1;
                    $points = 0;
                    for($j = 0; $j < $n; $j++)
                        $points += $score_map[$pos + $j];
                    $points = floor($points / $n);
                }

                if($_REQUEST["debug"])
                    echo "Assign $pos = $points ($n x)\n";
                else if($points != $i["points"])
                {
                    vsql::update("achievements", array("points" => $points), $i["id"]);
                    $cnt++;
                }
            }
            echo json_encode(array("msg" => "Przeliczono wyników: $cnt"));
        }

    }
