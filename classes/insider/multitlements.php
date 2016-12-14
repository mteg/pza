<?php
    class insider_multitlements extends insider_entitlements
    {
        public $table = "entitlements";
        public $oths = array(
            "med:*" => "Badania",
            "d:pza:*" => "Zgoda",
            "p:*" => "Zaświadczenie"
        );

        function __construct()
        {
            foreach(array_values($this->oths) as $n => $capt)
                $this->columns["othdate{$n}"] = array($capt, "order" => "othdate{$n}");

            parent::__construct();
        }

        function view()
        {
            $user = vsql::get("SELECT user FROM entitlements WHERE id = " . vsql::quote($_REQUEST["id"]), "user");
            header("Location: /insider/users/view?id=" . $user);
        }

        protected function retr_query($filters)
        {
            $othcols = ""; $othjoins = ""; $n = 0;
            foreach($this->oths as $family => $capt)
            {
                $othcols .= ", IFNULL(MAX(othe{$n}.due), '---') AS othdate{$n}";
                $othjoins .= " LEFT JOIN entitlements AS othe{$n} ON othe{$n}.user = t.user AND othe{$n}.deleted = 0 AND " .
                    vsql::id_condition(
                        vsql::retr("SELECT id FROM rights WHERE deleted = 0 AND short LIKE " . vsql::quote(strtr($family, array("*" => "%"))),
                            "id", "id"), "othe{$n}.right");
                $n++;
            }

            $family = $_REQUEST["family"];
            $selector = $_REQUEST["selector"];
            $query = "SELECT SQL_CALC_FOUND_ROWS " .
                " t.id, r.name AS `right`, u.surname, u.name, IF(t.due = '9999-12-31', '-- bezterminowo --', t.due) AS due {$othcols} " .
                " FROM entitlements AS t
                  LEFT JOIN users AS u ON t.user = u.id
                  LEFT JOIN rights AS r ON t.`right` = r.id
                  {$othjoins}
                  WHERE t.deleted = 0 " . $filters .
                ($selector ? (" AND r.id = " . vsql::quote($selector)) : "") .
                ($family ? (" AND r.short REGEXP " . vsql::quote('^' . $family . "($|:.*$)")) : "") . " GROUP BY t.id";

            return $query;
        }
    }
