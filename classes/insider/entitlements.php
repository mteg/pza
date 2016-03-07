<?
    class insider_entitlements extends insider_table
    {
        public $fields = array(
            "right" =>   array("Uprawnienie", "ref" => "rights", "by" => "name", "search" => "r.name", "order" => "r.name"),
            "user" =>    array("Osoba", "ref" => "users", "by" => "ref"),
            "number" =>  array("Numer uprawnienia"),
            "starts" =>  array("Data uzyskania", "type" => "date"),
            "due" =>     array("Data wygaśnięcia", "type" => "date"),
            "public" =>     array("Uprawnienie publiczne", "type" => "select", "options" => array(1 => "Tak", 0 => "Nie")),
        );

        public $columns = array(
            "right" => "Uprawnienie",
            "surname" => array("Nazwisko", "order" => "u.surname"),
            "name" => array("Imię", "order" => "u.name"),
            "due"
        );

        public $filters = array(
            "current" => array("Status", "type" => "select_filter", "options" => array("" => "-- Wszystkie --", 1 => "Aktywne uprawnienia", 2 => "Historyczne uprawnienia")),
            "right",
            "surname" => array("Nazwisko", "search" => "u.surname"),
            "name" => array("Imię", "search" => "u.name"),
            "short" => array("Skrót uprawnienia", "search" => "r.short"),
            "starts", "due");

        public $order = "right, surname, name";

        protected function access($perm)
        {
            if(in_array($perm, array("search", "view", "add", "edit", "delete")))
            {
                if($family = $_REQUEST["family"])
                    if(access::has("entmgr($family)"))
                        return true;
            }

            if(in_array($perm, array("search", "view")))
                if(access::glob("entmgr(*)"))
                    return true;

            return parent::access($perm);
        }

        function family_access($fname)
        {
            if(access::has("god")) return "";
            if(access::has("delete(entitlements)")) return "";
            if(!count($args = access::args("entmgr"))) return " AND 0 = 1";
            foreach($args as $k => $arg)
                $args[$k] = $fname . " LIKE " . vsql::quote($arg . ":%");
            return " AND (" . implode(" OR ", $args) . ")";
        }

        protected function validate($id, $data)
        {
            foreach(array("starts" => "0000-00-00", "due" => "9999-12-31") as $f => $def)
                if(isset($data[$f]) && !strlen($data[$f]))
                    $data[$f] = $def;

            if(isset($data["due"]))
                if((!strlen($data["due"])) || $data["due"] == "0000-00-00")
                    $date["due"] = "9999-12-31";

            return parent::validate($id, $data);
        }

        function __construct()
        {
            $family = $_REQUEST["family"];

            $opts = vsql::retr("SELECT id, CONCAT('[',  short, '] ', name) AS name
                        FROM rights
                        WHERE deleted = 0 " .
                            $this->family_access("short") .
                            ($family ? (" AND short LIKE " . vsql::quote($family . ":%")) : "") .
                        " ORDER BY name, id", "id", "name");

            $this->fields["right"] = array("Uprawnienie", "type" => "select",
                "search" => "r.name", "order" => "r.name", "options" => $opts);

            parent::__construct();
            if(access::has("edit(entitlements)"))
                $this->actions["/insider/entitlements/prolong?&"] = array("name" => "Przedłuż", "multiple" => true);
        }

        protected function retr_query($filters)
        {
            $family = $_REQUEST["family"];
            $query = "SELECT SQL_CALC_FOUND_ROWS " .
                " t.id, r.name AS `right`, u.surname, u.name, IF(t.due = '9999-12-31', '', t.due) AS due " .
                " FROM entitlements AS t " .
                " LEFT JOIN users AS u ON t.user = u.id " .
                " LEFT JOIN rights AS r ON t.`right` = r.id " .
                " WHERE t.deleted = 0 " . $filters .
                ($family ? (" AND r.short LIKE " . vsql::quote($family . ":%")) : "");

            return $query;
        }

        protected function build_filter_atom($f, $s)
        {
            if($f == "current")
                return ($s == 1) ? "(t.due >= NOW())" : "(t.due < NOW())";
            else if($f == "right")
            {
                if($s[0] == "=")
                    $s = substr($s, 1);
                else
                    $s = "*" . $s . "*";

                $s = strtr($s, array("*" => "%", "?" => "_"));
                return "r.name LIKE " . vsql::quote($s);
            }
            else
                return parent::build_filter_atom($f, $s);
        }

        protected function defaults()
        {
            $data = array();
//            $data["right"] = vsql::get("SELECT `right` FROM entitlements WHERE deleted = 0 ORDER BY creat DESC LIMIT 1", "right", 0);
            $data["due"] = "9999-12-31";
            $data["starts"] = date("Y-m-d");
            if(isset($_REQUEST["user"]))
                $data["user"] = ($id = $_REQUEST["user"]) . ": " . vsql::get("SELECT ref FROM users WHERE id = " . vsql::quote($_REQUEST["user"]), "ref");
            return($data);
        }

        protected function capt($id)
        {
            return vsql::get("SELECT CONCAT('[', r.short, '] * ', u.ref) AS capt FROM
                  entitlements AS e
                  LEFT JOIN users AS u ON u.id = e.user
                  LEFT JOIN rights AS r ON r.id = e.right
                  WHERE e.id = " . vsql::quote($id), "capt", "");
        }

        function enforce($perm)
        {
            if($perm == "add" || $perm == "edit")
                if(isset($_REQUEST["right"]))
                {
                    if(vsql::get("SELECT id FROM rights WHERE deleted = 0 AND id = " .
                        vsql::quote($_REQUEST["right"]) . $this->family_access("short")))
                        return true;
                }
                else
                    if(access::glob("entmgr(*)"))
                        return true;
            if($perm == "delete")
            {
                if(isset($_REQUEST["id"]))
                {
                    if(vsql::get("SELECT e.id FROM entitlements AS e
                            JOIN rights AS r ON r.id = e.right
                            WHERE e.deleted = 0 AND e.id = " .
                    vsql::quote($_REQUEST["right"]) . $this->family_access("r.short")))
                        return true;
                }
            }

            return parent::enforce($perm);
        }

        function prolong()
        {
            access::ensure("edit(entitlements)");
            $entids = $_REQUEST["id"];
            $entids = explode(" ", $entids);
            $this->S->assign("entl_list",
                vsql::id_retr($entids, "e.id",
                    "SELECT e.id, CONCAT(u.surname, ' ', u.name, ' ** ', r.name) AS ref
                        FROM entitlements AS e
                             JOIN rights AS r ON r.id = e.right
                             JOIN users AS u ON u.id = e.user
                             WHERE e.deleted = 0 AND ", "id", "ORDER BY ref", "ref"));

            if(isset($_REQUEST["date"]))
            {
                $date = $_REQUEST["date"];
                if(!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $date))
                    $err["date"] = "Nieprawidłowa data wygaśnięcia uprawnienia";
                else
                {
                    foreach($entids as $id)
                        if($id)
                             vsql::update("entitlements", array("due" => $date), $id);

                    die("Przedłużono uprawnień: " . count($entids));
                }
                $this->S->assign("err", $err);
            }
            $this->w_action("prolong");
        }
    }
