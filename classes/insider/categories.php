<?
    class insider_categories extends insider_table
    {
        public $fields = array(
            "name" =>       array("Nazwa kategorii", "regexp" => ".+"),
            "short" =>      array("Nazwa krótka", "nolist" => true),
            "parent" =>     array("Kategoria nadrzędna", "ref" => "categories", "by" => "path", "nolist" => true, "empty" => true),
            "path" =>       array("Ścieżka", "noedit" => true),
            "template" =>   array("Wzorzec HTML", "nolist" => true, "nohist" => true, "type" => "area"),
        );

        protected $capt = "<name>";
        protected $order = "path";
        protected $type = "articles";

        function __construct()
        {
            if(isset($_REQUEST["type"]))
                $this->type = $_REQUEST["type"];

            if($this->type == "file")
                unset($this->fields["template"]);

            if($this->type == "paperback")
                $this->fields["name"][0] = "Nazwa serii";

            if($this->type == "photo")
                $this->fields["name"][0] = "Nazwa galerii";

            parent::__construct();

            if($this->type == "articles")
                $this->actions["/insider/categories/content"] = array("name" => "Artykuły", "target" => "_self");

            if($this->type == "photos")
                $this->actions["/insider/categories/content"] = array("name" => "Zdjęcia", "target" => "_self");
        }

        protected function validate($id, &$data)
        {
            $err = parent::validate($id, $data);
            if(count($err)) return($err);

            if(isset($data["short"]))
            {
                if(strlen($data["short"]) == 0 && $data["parent"] == 0)
                {

                }
                else if(strlen($data["short"]) == 0)
                    $err["short"] = "Każda kategoria musi mieć skrót!";
                else if(strlen($data["short"]) > 15)
                    $err["short"] = "Nie więcej niż 15 znaków.";
                else if(!preg_match('/^[a-z][0-9a-z_]*$/', $data["short"]))
                    $err["short"] = "Skrót musi zaczynać się literą i może zawierać tylko znaki: a-z 0-9 _";
                else
                {
                    if($id && !isset($data["parent"]))
                        $data["parent"] = vsql::get("SELECT parent FROM categories WHERE id = " . vsql::quote($id), "parent");

                    if(vsql::get("SELECT id FROM categories WHERE
                            deleted = 0 AND short = " . vsql::quote($data["short"]) .
                            " AND parent = " . vsql::quote($data["parent"]) .
                            " AND id != " . vsql::quote($id)))
                       $err["short"] = "Inna kategoria posiada już taki skrót!";
                }
            }

            if(isset($data["parent"]) && $data["parent"] == $id)
                $err["parent"] = "Kategoria nie może być nadrzędna sama dla siebie";

            return $err;
        }

        protected function retr_extra_filters()
        {
            return " AND type = " . vsql::quote($this->type);
        }

        protected function update($id, $data)
        {
            if(!$id)
                $data["type"] = $this->type;
            parent::update($id, $data);

            /* Napraw ścieżki kategorii */
            while(count($r = vsql::retr("SELECT c.id,
                    CONCAT(IF(p.path = '/', '', p.path), '/', c.short) AS path1,
                    c.path as path2
                    FROM categories AS c
                    JOIN categories AS p ON p.id = c.parent
                    WHERE c.deleted = 0
                    HAVING path1 != path2", "id", "path1")))
                foreach($r as $id => $path)
                    vsql::update("categories", array("path" => $path), $id, "id", false);
        }

        protected function defaults()
        {
            $data["parent"] =
                vsql::get("SELECT p.path FROM categories AS c " .
                            " JOIN categories AS p ON p.id = c.parent " .
                            " WHERE c.deleted = 0 ORDER BY c.creat DESC LIMIT 1", "path", "");
            return $data;
        }

        public function content()
        {
            $path = vsql::get("SELECT path FROM categories WHERE deleted = 0 AND id = " . vsql::quote($_REQUEST["id"]), "path", "");
            header("Location: /insider/content?type=" . $this->type . "#categories=" . htmlspecialchars($path));
        }
    }
