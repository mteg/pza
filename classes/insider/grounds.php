<?
    class insider_grounds extends insider_table
    {
        public $fields = array(
            "creat" =>   array("Data utworzenia", "no" => "add,edit"),
            "type" =>    array("Typ", "type" => "select", "options" => array(
                               "comp:s:pza" =>  "Zawody PZA (wspinaczka sportowa)",
                               "comp:s:other" =>  "Zawody inne (wspinaczka sportowa)",
                               "comp:tj:pza" =>  "Zawody PZA (alpinizm jaskiniowy)",
                               "comp:tj:other" =>  "Zawody inne (alpinizm jaskiniowy)",
                               "comp:nw:pza" =>  "Zawody PZA (skialpinizm)",
                               "comp:nw:other" =>  "Zawody inne (skialpinizm)",
                               "event:s" =>  "Szkolenia i unifikacje",
                               "nature:climb" => "Droga wspinaczkowa",
                               "nature:cave" => "Jaskinia",
                               "exp:h" => "Wyprawa himalajska",
                               "exp:cave" => "Wyprawa jaskiniowa",
                               "rank:s" => "Ranking (wspinaczka sportowa)",
                               "rank:nw" => "Ranking (narciarstwo wysokogórskie)",
                        )),
            "name" =>       array("Nazwa szranki", "regexp" => ".+"),
            "country" =>    array("Kraj", "type" => "list", "regexp" => ".+", "suppress" => true),
            "region" =>     array("Rejon", "consistency" => true, "suppress" => true),
            "city" =>       array("Miasto", "consistency" => true, "suppress" => true),
            "massif" =>       array("Masyw", "consistency" => true, "suppress" => true),
            "summit" =>       array("Wierzchołek", "consistency" => true, "suppress" => true),
            "difficulty" =>   array("Trudność", "consistency" => true, "suppress" => true),
            "start" =>        array("Data rozpoczęcia", "type" => "date", "suppress" => true),
            "finish" =>       array("Data zakończenia", "type" => "date", "suppress" => true, "empty" => true),
            "categories" =>   array("Kategorie", "type" => "area", "suppress" => true),
            "remarks" =>      array("Uwagi", "type" => "area", "suppress" => true),
            "lat"   =>      array("Szerokość geograficzna", "suppress" => true),
            "lon"   =>      array("Długość geograficzna", "suppress" => true),
        );

        public $columns = array("creat", "name", "country", "region", "summit", "start", "finish");
        protected $capt = "<name>";

        /* Kolejność sortowania */
        protected $order = "start, name, country, region, summit";

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

        function __construct()
        {
            /*
               Dwa tryby pracy
                 - wszystko
                 - rodzina szranek
            */

            $ach_caption = "Osiągnięcia"; $family = "";

            $this->fields["country"]["options"] = placelist::get("countries");

            if($type = $_REQUEST["type"])
            {
                list($family, $fine) = explode(":", $type);

                $this->remove_fields("type");

                switch($family)
                {
                    case "event":
                        $this->fields["categories"][0] = "Typy wpisów";

                    case "comp":
                        $this->fields["name"][0] = "Nazwa imprezy";
                        if(fnmatch("comp:*:pza", $type))
                            $this->fields["region"] = array("Województwo", "type" => "list");

                        $this->fields["region"]["options"] = placelist::get("regions");
                        $this->remove_fields("creat,massif,summit,difficulty");

                        $ach_caption = ($family == "event" ? "" : "Wyniki");
                        break;

                    case "nature":
                        $suffix = "";
                        if($fine == "cave")  $suffix .= " jaskini";
                        if($fine == "climb") $suffix .= " drogi";

                        $ach_caption = "Przejścia" . $suffix;

                        $this->fields["name"][0] = "Nazwa" . $suffix;
                        $this->remove_fields("city,start,finish,categories");
                        $this->order = "creat DESC";


                        if($fine == "cave")
                            $this->remove_fields("summit,difficulty");

                        break;

                    case "exp":
                        $this->fields["name"][0] = "Nazwa wyprawy";
                        $this->remove_fields("creat,city,summit,difficulty,categories");
                        $ach_caption = "Uczestnicy wyprawy";
                        break;

                    case "rank":
                        break;
                }
            }

            parent::__construct();
            if(access::has("search(achievements)") && $ach_caption)
                $this->actions["/insider/grounds/achievements"] = array("name" => $ach_caption, "target" => "_self");

            if($family == "nature")
                $this->actions["/insider/grounds/attain"] = array("name" => "Dodaj przejście");

            if(fnmatch("comp:*:pza", $type) || $family == "event")
            {
                $this->actions["/insider/grounds/competitors"] =
                    array("name" => ($family == "comp" ? "Lista startowa" : "Lista uczestników"), "target" => "_self");
                $this->actions["/insider/grounds/attain"] =
                    array("name" => ($family == "comp" ? "Zapis na zawody" : "Zapis"));
            }

            if(fnmatch("comp:*:other", $type))
                $this->actions["/insider/grounds/attain"] = array("name" => "Dodaj wynik");

            $this->columns["cnt"] = array("name" => "P", "order" => "cnt");
        }

        protected function defaults()
        {
            /* Przy dopisywaniu wielu dróg/jaskiń z tego samego rejonu,
                ma sens coś takiego: */
            if(fnmatch("nature:*", $type = $_REQUEST["type"]))
                if($i = vsql::get("SELECT country, region, massif, summit FROM grounds
                      WHERE creat >= DATE(NOW()) AND creat_by = " .
                      vsql::quote(access::getuid()) .
                      " AND type = " . vsql::quote($type) .
                      " AND deleted = 0 ORDER BY id DESC LIMIT 1"))
                    return $i;

            return array();
        }

        protected function update($id, $data)
        {

            parent::update($id, $data);
        }

        protected function retr_query($filters)
        {
            $uid = access::getuid(); $cols = array();
            foreach(array_keys($this->columns) as $c)
                if($c != "cnt")
                    $cols[] = "t." . $c;

            return "SELECT SQL_CALC_FOUND_ROWS " .
                " t.id, " . implode(",", $cols) . "," .
                " IF(IFNULL(COUNT(a.id), 0) = 0, '', " .
                    " IF(t.type LIKE 'nature:%', IFNULL(COUNT(a.id), 0), '+')) AS cnt " .
                " FROM grounds AS t " .
                " LEFT JOIN achievements AS a ON a.user = " .
                    vsql::quote($uid) . " AND a.ground = t.id AND a.deleted = 0 " .
                " WHERE t.deleted = 0 " . $filters . " " .
                (($type = $_REQUEST["type"]) ? " AND t.type = " . vsql::quote($type) : "") .
                " GROUP BY t.id";
        }

        public function achievements()
        {
            header("Location: /insider/achievements?ground=" . $_REQUEST["id"]);
            exit;
        }

        public function competitors()
        {
            header("Location: /insider/achievements?list=1&ground=" . $_REQUEST["id"]);
            exit;
        }

        public function attain()
        {
            header("Location: /insider/achievements/add?ground=" . $_REQUEST["id"]);
            exit;
        }

        protected function access($perm)
        {
            $type = $_REQUEST["type"];
            if(fnmatch("event:*", $type))
                return access::has($perm . "(events)");

            if(in_array($perm, array("add", "edit", "delete")))
                if($type)
                    if(fnmatch("comp:*:other", $type) || fnmatch("nature:*", $type))
                        return true;

            return parent::access($perm);
        }

        protected function validate($id, &$data)
        {
            $err = parent::validate($id, $data);
            if(count($err)) return $err;

            if($id)
                $data["type"] = vsql::get(
                    "SELECT type FROM grounds WHERE id = " . vsql::quote($id),
                    "type");

            if($type = $_REQUEST["type"])
            {
                if($id && $type != $data["type"])
                    $err["type"] = "Nie można zmieniać typu szranki";
                else
                    $data["type"] = $type;
            }

            if($data["type"] == "nature:cave" || $data["type"] == "nature:climb")
                if(!($data["massif"] || $data["region"]))
                    $err["massif"] = "Należy podać rejon lub masyw";

            if($data["type"] == "nature:climb")
                if(!$data["difficulty"])
                    $err["difficulty"] = "Należy podać trudności drogi";

            if(fnmatch("comp:*", $data["type"]) || fnmatch("event:*", $data["type"]))
            {
                if(!$data["start"])
                    $err["start"] = "Należy podać datę rozpoczęcia";
                if(!$data["finish"])
                    $data["finish"] = $data["start"];
            }

            if($id)
                unset($data["type"]);

            return $err;
        }

        function dupcheck()
        {
            if(fnmatch("nature:*", $type = $_REQUEST["type"]))
                if(count($similar = vsql::retr("SELECT id, name, region FROM grounds WHERE deleted = 0
                            AND country = " . vsql::quote($_REQUEST["country"]) .
                            " AND type = " . vsql::quote($type) .
                            " AND name LIKE " . vsql::quote("%" . strtr(
                                $_REQUEST["name"], array("%" => " ")) . "%") .
                            " AND id != " . vsql::quote($_REQUEST["id"]) .
                            " ORDER BY name DESC LIMIT 5")))
                {
                    $this->S->assign("similar", $similar);
                    $this->w("grounds_dupcheck.html");
                }
        }

        function delete()
        {
            $id = $_REQUEST["id"];
            if(!access::has("delete(grounds)"))
                if(vsql::id_retr($id, "g.id", "SELECT g.id FROM grounds AS g
                      WHERE g.deleted = 0 AND g.creat_by != " . vsql::quote(access::getuid()) .
                      " AND "))
                    $this->json_fail("Nie możesz usunąć: nie posiadasz prawa do usuwania obiektów nie dodanych przez siebie");

            if(vsql::id_retr($id, "g.id", "SELECT a.id FROM grounds AS g
                    JOIN achievements AS a ON g.id = a.ground AND a.deleted = 0
                    WHERE g.deleted = 0  AND "))
                $this->json_fail("Nie można skasować tej szranki (drogi/jaskini/zawodów) przed usunięciem osiągnięć (przejść/wpisów)");

            parent::delete();
        }

        function view()
        {
            $id = $_REQUEST["ground"] = $_REQUEST["id"];

            $o = new insider_achievements;
            $r = $o->retr(0, 15);

            $gt = vsql::get("SELECT type FROM grounds WHERE id = " . vsql::quote($id), "type");
            $at = "Wpisy";

            if(fnmatch("comp:*", $gt) || fnmatch("rank:*", $gt))
                $at = "Zawodnicy";
            else if(fnmatch("nature:*", $gt))
                $at = "Przejścia";
            else if(fnmatch("exp:*", $gt) || fnmatch("event:*", $gt))
                $at = "Uczestnicy";

            $this->S->assign("ach_title", $at);
            $this->S->assign("o", $o);
            $this->S->assign("achievements", $r);


            parent::view();

        }
    }
