<?
    class insider_entitlements extends insider_table
    {
        public $fields = array(
            "right" =>   array("Uprawnienie", "ref" => "rights", "by" => "name", "search" => "r.name", "order" => "r.name"),
            "user" =>    array("Osoba", "ref" => "users", "by" => "ref"),
            "starts" =>  array("Data uzyskania", "type" => "date"),
            "due" =>     array("Data wygaśnięcia", "type" => "date"),
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
            "short" => array("Skrót uprawnienia", "search" => "e.short"),
            "starts", "due");

        public $order = "right, surname, name";

        protected function access($perm)
        {
            if($family = $_REQUEST["family"])
                if(in_array($perm, array("search", "add", "edit", "view", "delete")))
                    if(access::has("entmgr($family)"))
                        return true;

            return parent::access($perm);
        }

        function __construct()
        {
            $family = $_REQUEST["family"];
            if($family)
            {
                $opts = vsql::retr("SELECT id, CONCAT('[',  short, '] ', name) AS name
                            FROM rights
                            WHERE deleted = 0 AND
                                  short LIKE " . vsql::quote($family . ":%") .
                            " ORDER BY name, id", "id", "name");

                $this->fields["right"] = array("Uprawnienie", "type" => "select",
                    "search" => "r.name", "order" => "r.name", "options" => $opts);
            }
            parent::__construct();
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

    }
