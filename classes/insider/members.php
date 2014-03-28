<?
    class insider_members extends insider_table
    {
        public $fields = array(
            "name" =>       array("Nazwa klubu", "regexp" => ".+"),
            "short" =>      array("Nazwa krótka", "regexp" => ".+", "suppress" => true),
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
            parent::__construct();
            $this->fields["country"]["options"] = placelist::get("countries");
            $this->fields["district"]["options"] = placelist::get("regions");
            $this->actions["/insider/members/memberships"] = array("target" => "_self", "name" => "Członkowie klubu");
        }

        public function memberships()
        {
            $id = $_REQUEST["id"];
            header("Location: /insider/memberships?org={$id}#status=1&current=1");
        }
    }
