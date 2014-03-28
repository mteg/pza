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
            "ground" =>  array("Szranka", "ref" => "grounds", "by" => "name", "search" => "g.name", "order" => "g.name"),
            "user" =>    array("Osoba", "ref" => "users", "by" => "ref"),
            "date" =>    array("Data", "type" => "date", "suppress" => true),
            "position" =>array("Miejsce", "regexp" => "[0-9]+", "empty" => true, "order" => "IF(CAST(t.position AS signed) = 0, 1, 0) <dir>, CAST(t.position AS signed) <dir>, t.position"),
            "points" =>  array("Punkty", "regexp" => "[0-9]+([,.][0-9]*)?", "suppress" => true, "empty" => true),
            "categ" =>   array("Kategoria", "consistency" => true, "suppress" => true),
            "style" =>   array("Styl", "consistency" => true, "suppress" => true),
            "partners" =>array("Partnerzy", "empty" => true, "multiple" => true,
                                "suppress" => true,
                                "comment" => "Wypełniać tylko w przypadku zawodów zespołowych."),
            "duration" =>array("Czas", "suppress" => true, "comment" => "Na przykład: <i>2d 10h</i> albo <i>3.31s</i>"),
            "flags" =>   array("Opcje", "type" => "flags", "options" => array(
                "V" => "Widoczne publicznie",
                "K" => "Kierownik akcji",
                "N" => "Nurek",
                "C" => "Opłacone wpisowe")),
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
            if(in_array($perm, explode(",", "search,add,edit,view,export,delete")))
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

            if(is_numeric($ground = $_REQUEST["ground"]))
            {
                $type = vsql::get("SELECT type FROM grounds WHERE deleted = 0 AND id = " . vsql::quote($ground), "type", "");
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

            switch($family)
            {
                case "event":
                    foreach(array("K", "N", "C") as $f)
                        unset($this->fields["flags"]["options"][$f]);
                    $this->remove_fields("place,points,position,duration,style,date,partners");
                    $ground_name = "Wydarzenie";
                    $this->fields["categ"][0] = "Typ wpisu";
                    $this->order = "surname, name";
                    $this->add_columns("creat");
                    break;

                case "comp":
                    foreach(array("K", "N") as $f)
                        unset($this->fields["flags"]["options"][$f]);

                    $this->remove_fields("place");

                    if($_REQUEST["list"])
                    {
                        $this->remove_fields("points,position,duration,style,date");
                        $this->order = "categ, surname, name";
                        $this->buttons["<classpath>/results"] = array("Wyniki", "target" => "_self", "icon" => "signal");
                    }
                    else
                    {
                        $this->add_columns("duration,position,points");
                        $this->order = "categ, position, points, surname, name";
                        $this->buttons["<classpath>/competitors"] = array("Lista startowa", "target" => "_self", "icon" => "list");
                        if(access::has("import(achievements)") && $ground)
                            $this->buttons["<classpath>/import"] =
                                array("Import wyników", "target" => "_blank",
                                    "icon" => "arrow-1-s");
                    }
                    $this->add_columns("categ");
                    $ground_name = "Impreza";

                    $this->fields["partners"]["ref"] = "users";
                    $this->fields["partners"]["by"] = "ref";

                    break;

                case "nature":
                    unset($this->fields["flags"]["options"]["C"]);
                    $this->remove_fields("position,points,categ");
                    unset($this->fields["partners"]["comment"]);

                    if($fine == "cave")
                    {
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
                        foreach(array("K", "N") as $f)
                            unset($this->fields["flags"]["options"][$f]);

                        $this->remove_fields("place");
                        $ground_name = "Droga";
                    }
                    $this->add_columns("date,style,duration");
                    $this->order = "date DESC, surname, name";

                    break;

                case "exp":
                    foreach(array("K", "N", "C") as $f)
                        unset($this->fields["flags"]["options"][$f]);
                    $this->remove_fields(array("date", "position", "points", "categ", "style", "partners", "duration", "place"));
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
            $query = "SELECT SQL_CALC_FOUND_ROWS t.id, t.creat, g.name AS ground, u.surname AS surname, u.name AS name,
                             t.position, t.points, t.categ, t.duration, t.date, " .
                            ($this->has_signup_restrictions() ? "m.name AS member," : "") .
                            "IF(g.type = 'nature:cave', REPLACE(t.style, ',', '\n'), t.style) AS style
                             FROM achievements AS t
                             LEFT JOIN grounds AS g ON g.id = t.ground AND g.deleted = 0
                             JOIN users AS u ON u.id = t.user AND u.deleted = 0" .
                            ($this->has_signup_restrictions() ?
                                (" LEFT JOIN memberships AS me ON me.user = u.id AND u.deleted = 0 AND me.starts <= NOW() AND me.due >= NOW() " .
                                 " LEFT JOIN members AS m ON m.id = me.member AND m.deleted = 0 ") : ""
                            ) .
                             " WHERE t.deleted = 0 " .  $filters;

            if(is_numeric($ground = $_REQUEST["ground"]))
                $query .= " AND g.id = " . vsql::quote($ground);

            if($type = $_REQUEST["type"])
                $query .= " AND g.type = " . vsql::quote($type);

            $user = $_REQUEST["user"];

            if(!access::has("search(achievements)"))
            {
                $query .= insider_users::pub_conditions("u.");
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

        private function validate_category($p)
        {
            $u = vsql::get("SELECT * FROM users WHERE deleted = 0 AND id = " . vsql::quote(access::getuid()));

            if($p["closed"])
                return $p["closed"];

            if($p["sex"] && $p["sex"] != $u["sex"])
                return "Niewłaściwa płeć!";

            if($p["born"])
            {
                list($from, $to) = array_map("trim", explode("~", $p["born"], 2));

                $born = substr($u["birthdate"], 0, 4);
                if((!is_numeric($born)) || $born == 0)
                    return "Niewłaściwa data urodzenia, uzupełnij profil!";

                if($born < $from || $born > $to)
                    return "Niewłaściwy rocznik: jest $born, ma być $from ~ $to";
            }

            if($p["require"])
            {
                $ents = vsql::retr("SELECT r.short FROM rights AS r
                            JOIN entitlements AS e ON e.right = r.id AND e.deleted = 0 AND
                                e.starts <= NOW() AND e.due >= NOW() AND e.user = " . vsql::quote(access::getuid()) .
                            " WHERE r.deleted = 0", "short", "short");
                foreach(array_map("trim", explode(",", $p["require"])) as $q)
                {
                    $has = false;

                    foreach(($rights = array_map("trim", explode("/", $q))) as $pattern)
                        foreach($ents as $ent)
                            if($has = ($has || fnmatch($pattern, $ent)))
                                break;

                    if(!$has)
                        return "Nie spełniasz warunku zapisu: " .
                            (count($rights) > 1 ? " (jedno z) " : "") .
                            implode(", ", vsql::retr("SELECT r.name FROM rights WHERE deleted = 0 AND " .
                                    vsql::id_condition($rights, "r.short"), "name", "name"));

                }
            }

            return false;
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

                    /* Sprawdzenie prawidłowości kategorii */
                    $catlist = $this->get_categories($ground);
                    if(count($catlist))
                    {
                        if($msg = $this->validate_category($opts = $catlist[$data["categ"]]))
                            $err["categ"] = $msg;
                        else if(isset($opts["limit"]))
                        {
                            $cnt = vsql::get("SELECT COUNT(a.id) AS c FROM grounds AS g
                                        JOIN achievements AS a ON a.ground = g.id AND a.deleted = 0
                                        WHERE g.id = " . vsql::quote($ground), "c", 0);
                            if($cnt >= $opts["limit"])
                                $err["categ"] = "Limit wpisów został już przekroczony.";
                        }
                    }
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

        private function get_categories($ground, $prop = false)
        {
            $r = array();
            foreach(explode("\n", vsql::get("SELECT categories FROM grounds WHERE " .
                        " id = " . vsql::quote($ground) .
                        " AND deleted = 0", "categories")) as $e)
            {
                $e = trim($e);
                if(!strlen($e)) continue;

                $props = array();
                foreach(explode("|", "name=" . $e) as $kv)
                {
                    list($k, $v) = array_map("trim", explode("=", $kv, 2));
                    $props[$k] = $v;
                }

                $r[$props["name"]] = $prop ? $props[$prop] : $props;
            }
            return $r;
        }

        private function has_signup_restrictions()
        {
            return (fnmatch("comp:*:pza", $this->ground_type) ||
                    fnmatch("event:*", $this->ground_type));
        }

        private function restrict_edits()
        {
            if($this->has_signup_restrictions())
            {
                if(!access::has("add(achievements)"))
                    $this->remove_fields("date,position,points,duration,style,flags");

                if($ground = $_REQUEST["ground"])
                {
                    if(count($categs = $this->get_categories($ground, "name")))
                    {
                        $this->fields["categ"]["type"] = "select";
                        $this->fields["categ"]["options"] = $categs;
                    }
                    else
                        unset($this->fields["categ"]);
                }
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
                        "id" => "Identyfikator użytkownika",
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

            if(isset($_FILES["file"]))
            {
                if(!file_exists($fname = $_FILES["file"]["tmp_name"]))
                    $err["file"] = "Problem z przesłaniem pliku (" . $_FILES["file"]["error"] . ")";
                else
                {
                    $this->process_import(file($fname), $cols);
                    $this->w("achievements_import2.html");
                    exit;
                }
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

        public function results()
        {
            header("Location: /insider/achievements?ground=" . $_REQUEST["ground"]);
            exit;
        }

        public function competitors()
        {
            header("Location: /insider/achievements?list=1&ground=" . $_REQUEST["ground"]);
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
    }
