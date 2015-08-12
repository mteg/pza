<?
    class insider_favcats extends insider_categories
    {
        public $table = "categories";
        function __construct()
        {
            unset($this->buttons["<classpath>/add"]);
            parent::__construct();

            if(isset($this->actions[$k = "/insider/categories/content"]))
            {
                $this->actions["/insider/categories/click"] = $this->actions[$k];
                unset($this->actions[$k]);
            }
        }

        protected function retr_extra_filters()
        {
            return parent::retr_extra_filters() . " AND " .
                vsql::id_condition(vsql::get(
                        "SELECT fav_categories FROM users
                            WHERE id = " .
                        vsql::quote(access::getuid()), "fav_categories", ""), "id");
        }
        public function click()
        {
            return $this->content();
        }

    }
