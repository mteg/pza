<?
    class insider_memberships extends insider_table
    {
        public $fields = array(
            "member" =>  array("Klub PZA", "ref" => "members", "by" => "short", "search" => "m.short"),
            "user" =>    array("Osoba", "ref" => "users", "by" => "ref"),
            "starts" =>  array("Data przystąpienia", "type" => "date"),
            "due" =>     array("Data opuszczenia", "type" => "date", "no" => "add"),
            "flags" =>   array("Opcje", "type" => "flags", "options" => array(
                        "Z" => "Zarząd klubu",
                        "A" => "Administrator klubowy",
                        "C" => "Osoba kontaktowa do klubu",
                        "R" => "Członkowstwo potwierdzone"))
        );

        public $columns = array(
            "member" => "Klub PZA",
            "surname" => array("Nazwisko", "order" => "u.surname"),
            "name" => array("Imię", "order" => "u.name"),
            "starts" => array("Od", "type" => "date"), "due" => array("Do", "type" => "date"),
        );

        public $filters = array(
            "member",
            "surname" => array("Nazwisko", "search" => "u.surname"),
            "name" => array("Imię", "search" => "u.name"),
            "status" => array("Potwierdzenie", "type" => "select_filter", "options" => array("" => "-- Wszystkie --", 1 => "Potwierdzone", 2 => "Niepotwierdzone")),
            "current" => array("Status", "type" => "select_filter", "options" => array("" => "-- Wszyscy --", 1 => "Obecni członkowie", 2 => "Byli członkowie")),
            "starts", "due", "flags");

        protected $order = "member, surname, name";
        private static $adm_of_cache = false;

        protected function build_filter_atom($f, $s)
        {
            if($f == "status")
                return ($s == 1) ? "(t.flags LIKE '%R%')" : "(t.flags NOT LIKE '%R%')";
            else if($f == "current")
                return ($s == 1) ? "(t.due >= NOW())" : "(t.due < NOW())";
            else
                return parent::build_filter_atom($f, $s);
        }

        protected function retr_query($filters)
        {
            $org = $_REQUEST["selector"];

            $query = "SELECT SQL_CALC_FOUND_ROWS " .
                " t.id, m.short AS member, u.surname, u.name,
                 IF(t.starts = '0000-00-00', '-- zawsze --', t.starts) AS starts,
                 IF(t.due = '9999-12-31', '---', t.due) AS due " .
                " FROM memberships AS t " .
                " LEFT JOIN users AS u ON t.user = u.id " .
                " LEFT JOIN members AS m ON t.member = m.id " .
                " WHERE t.deleted = 0 " . $filters .
                ($org ? (" AND t.member = " . vsql::quote($org)) : "");

            return $query;
        }

        protected function filter_view($data)
        {
            if($data["due"] == "9999-12-31")
                $data["due"] = "-- bezterminowo --";
            if($data["starts"] == "0000-00-00")
                $data["starts"] = "-- nieznana --";
            return $data;
        }

        function access($perm)
        {
            if(parent::access($perm)) return true;

            if(isset($_REQUEST["selector"]) &&
                    in_array($perm, array("export", "edit", "search", "view", "delete", "confirm")
                ))
                return(in_array($_REQUEST["selector"], array_keys(static::adm_of())));

            return false;
        }

        function __construct()
        {
            $this->actions["/insider/memberships/confirm"] =
                array("Potwierdź", "multiple" => true,
                    "ask" => "Potwierdzić członkostwo?", "target" => "_top");
            parent::__construct();


            if(isset($_REQUEST["restrict"]))
            {
                unset($this->filters["member"]);
                unset($this->columns["member"]);
                if(!in_array($_REQUEST["method"], array("click", "view")))
                    unset($this->fields["member"]);
                $this->order = "surname, name";
                $this->main_selector = "member";
                $this->main_selection = insider_members::get_members();
                unset($this->actions["<classpath>/delete"]);
            }
            if(access::has("edit(memberships)"))
            {
                $this->actions["/insider/memberships/prolong?&"] = array("name" => "Przedłuż", "multiple" => true);
                $this->actions["/insider/memberships/prolong?fin=1&"] = array("name" => "Wypisz", "multiple" => true);
            }
        }

        protected function defaults()
        {
            $data["flags"] = "R";

            $data["starts"] = date("Y-m-d");
            $data["due"] = "9999-12-31";
            return $data;
        }

        protected function update($id, $data)
        {
            $selector = $_REQUEST["selector"];
            if(is_numeric($selector))
            {
                if((!$id) || isset($data["member"]))
                    $data["member"] = $selector;

                if(!access::has("edit(memberships)"))
                {
                    if($id)
                        unset($data["user"]);
                    else
                        die("ERR Brakujące prawa dostępu");
                }
            }
            if(!$data["flags"]) $data["flags"] = "R";

            parent::update($id, $data);
        }

        protected function validate($id, &$data)
        {
            if(!$this->access("edit"))
                $data["user"] = access::getuid();

            if($err = parent::validate($id, $data))
                return $err;

            if(isset($data["member"]))
                $org = $data["member"];
            else if($id)
                $org = vsql::get("SELECT member FROM memberships WHERE id = " . vsql::quote($id), "member", 0);
            else
                $org = $_REQUEST["selector"];

            if((!$id) && (!$data["due"]))
                $data["due"] = "9999-12-31";

            if(vsql::get("SELECT id FROM memberships WHERE deleted = 0
                          AND  user = " . vsql::quote($data["user"]) .
                        " AND member = " . vsql::quote($org) .
                        " AND id != " . vsql::quote($id) .
                        " AND ((starts <= " . vsql::quote($data["starts"]) .
                        " AND  due >= " . vsql::quote($data["starts"]) . ")" .
                        " OR  (starts <= " . vsql::quote($data["due"]) .
                        " AND  due >= " . vsql::quote($data["due"]) . "))"))
                $err["starts"] = "Podwójne członkostwo, osoba jest już zapisana do innego klubu w tym okresie";

            return $err;
        }

        static function adm_of()
        {
            if(!(static::$adm_of_cache === false))
                return static::$adm_of_cache;

            $uid = access::getuid();
            return static::$adm_of_cache = vsql::retr(
                "SELECT m.id, m.short FROM memberships AS me " .
                " JOIN members AS m ON m.id = me.member " .
                " WHERE me.deleted = 0" .
                " AND me.starts <= NOW() AND me.due >= NOW() " .
                " AND me.user = " . vsql::quote($uid) .
                " AND me.flags LIKE '%A%'", "id", "short");
        }

        protected function fetch($id, $resolve = false, $nl = false, $extra = "")
        {
            if(!access::has("view(memberships)"))
                $extra .= " AND " .
                    vsql::id_condition(array_keys($this->adm_of()), "member");

            return parent::fetch($id, $resolve, $nl, $extra);
        }

        function confirm()
        {
            if($this->access("confirm"))
            {
                if($org = $_REQUEST["selector"])
                    $xcond = " AND member = " . vsql::quote($org);
                else
                    $xcond = "";
                foreach(vsql::id_retr($_REQUEST["id"], "id",
                    "SELECT id, flags FROM memberships WHERE deleted = 0 {$xcond} " .
                    " AND flags NOT LIKE '%R%' AND ", "id", "", "flags") as $id => $flags)
                    vsql::update("memberships", array("flags" => $flags . "R"), $id);
            }
            echo json_encode(array());
        }

        function add()
        {
            if($member = $_REQUEST["selector"])
                $this->S->assign("membername", vsql::get("SELECT name FROM members WHERE id = " . vsql::quote($member), "name"));
            parent::add();
        }

        protected function capt($id)
        {
            return vsql::get("SELECT CONCAT(u.id, ' * ', m.name) AS capt FROM
                  memberships AS me
                  LEFT JOIN users AS u ON u.id = me.user
                  LEFT JOIN members AS m ON m.id = me.member
                  WHERE me.id = " . vsql::quote($id), "capt", "");
        }

        function prolong_get_dates($entids)
        {
            return vsql::id_retr($entids,
                "e.id",
                "SELECT e.id, e.due, e.starts FROM memberships AS e WHERE e.deleted = 0 AND ");
        }

        function prolong_get_list($entids)
        {
            return vsql::id_retr($entids, "e.id",
                "SELECT e.id, CONCAT(u.surname, ' ', u.name, ' ** ', m.short) AS ref
                    FROM memberships AS e
                         JOIN members AS m ON m.id = e.member
                         JOIN users AS u ON u.id = e.user
                         WHERE e.deleted = 0 AND ", "id", "ORDER BY ref", "ref");
        }
    }
