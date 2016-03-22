<?
    class insider_members extends insider_table
    {
        public $fields;
        static $_fields = array(
            "name" =>       array("Nazwa klubu", "regexp" => ".+"),
            "short" =>      array("Nazwa krótka", "regexp" => ".+", "suppress" => true),
            "designation" =>      array("Skrót", "regexp" => "[A-Za-z]+", "suppress" => true, "empty" => true),
            "email" =>      "E-mail",
            "country" =>    array("Kraj", "type" => "list", "options" => array()),
            "district" =>   array("Województwo", "type" => "list", "options" => array()),
            "zip"   =>      "Kod pocztowy",
            "town"  =>      array("Miasto", "consistency" => true),
            "street"    =>  "Ulica / adres",
            "profile"   =>  array("Profil klubu", "type" => "flags", "options" =>
                array("J" => "Jaskiniowy",
                      "W" => "Wysokogórski",
                      "K" => "Kanioningowy",
                      "N" => "Narciarski",
                      "S" => "Sportowy")),
            "settlement"=>  array("Następna płatność", "type" => "date"),
            "www" =>        "Strona WWW",
            "pza" =>    array("Zrzeszony w PZA?", "type" => "select", "options" => array(1 => "Tak", 0 => "Nie"))
        );

        protected $capt = "<name>";

        public $columns = array("name", "town");
        public $filters = array("name", "town", "profile", "settlement", "pza");
        protected $order = "name";

        function __construct()
        {
            $this->fields = static::$_fields;
            parent::__construct();
            $this->fields["country"]["options"] = placelist::get("countries");
            $this->fields["district"]["options"] = placelist::get("regions");
            $this->actions["/insider/members/memberships"] = array("target" => "_self", "name" => "Członkowie klubu");

            if(access::has("mailing"))
                $this->actions["/insider/mailing/members&"] = array("name" => "Wyślij email/sms", "multiple" => true, "target" => "_self");
        }

        public function memberships()
        {
            $id = $_REQUEST["id"];
            header("Location: /insider/memberships?restrict=1#selector={$id}&current=1");
        }

        public static function get_members()
        {
            return vsql::retr("SELECT id, short FROM members WHERE deleted = 0 ORDER BY short", "id", "short");
        }
    }
