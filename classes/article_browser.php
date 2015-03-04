<?
    class article_browser extends browser
    {
        static $browser_filters = array("title" => "t.title", "authors" => "t.authors", "lead" => "t.lead");

        function __construct()
        {
            return parent::__construct("paperback_article");
        }

        function retr_query($filters, $extra_cols = "")
        {
            foreach(static::$browser_filters as $fn => $cn)
                if(isset($this->browser_params[$fn]))
                {
                    $q = $this->browser_params[$fn];
                    if($q{0} == "=")
                        $q = substr($q, 1);
                    else
                        $q = "%" . $q . "%";

                    $filters .= " AND {$cn} LIKE " . vsql::quote($q);
                }
            return parent::retr_query($filters, $extra_cols . ", issue.title AS issue_title, issue.id AS issue_id, t.authors");
        }

        function filters()
        {
            foreach(array_keys(static::$browser_filters) as $f)
                if($_REQUEST[$f]) $this->browser_params[$f] =
                    strtr($_REQUEST[$f], array("*" => "%", "?" => "_"));

            return $this;
        }

        function extra_self()
        {
            $out = "";
            foreach(array_keys(static::$browser_filters) as $f)
                if($_REQUEST[$f])
                    $out .= "&{$f}=" . urlencode($_REQUEST[$f]);

            return $out;
        }

        function extra_panel()
        {
            $out  = "</div>";
            $out .= "<div class='pza-browsepanel'>";
            foreach(array("authors" => "Autor", "title" => "Tytuł", "lead" => "Streszczenie")
                    as $f => $capt)
                $out .= "<span>{$capt}: <input type='text' name='{$f}' value='" .
                        htmlspecialchars(isset($this->browser_params[$f]) ? $this->browser_params[$f] : "") .
                        "' size=10></span>";

            return $out;
        }
    }