<?

// todo back jakoś nieładnie działa, chyba mogłoby lepiej!!
    class insider_rights extends insider_table
    {
        public $fields = array(
            "name" =>    array("Nazwa uprawnienia", "regexp" => ".+"),
            "short" =>   array("Identyfikator", "regexp" => "[a-z]+(:[a-z0-9_()]+)+"),
            "access" =>   "Prawa dostępu",
            "active" =>  array("Aktywne?", "type" => "select", "options" => array(1 => "Tak", 0 => "Nie")),
        );

        protected $capt = "<name>";
        protected $order = "name";

        function __construct($profile = false)
        {
            if(!access::has("god"))
                unset($this->fields["access"]);
        }
    }
