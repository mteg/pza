<?

class browser extends insider_content
{
    public $type, $browser_params = array(), $count, $order = "weight DESC";
    protected $table = "content";

    function __construct($ctype)
    {
        access::$nologin = true;
        parent::__construct($ctype);
    }

    function set($k, $v = null)
    {
        if($v === null && is_array($k))
        {
            foreach($k as $kk => $kv)
                $this->set($kk, $kv);
        }
        else
            $this->browser_params[$k] = $v;

        return $this;
    }

    function ls()
    {
        $p = $this->browser_params;
        if(!is_numeric($offset = $p["offset"]))
            $offset = 0;
        if(!is_numeric($limit = $p["limit"]))
            $limit = 20;

        if($limit > 100) $limit = 100;

        return $this->retr($offset, $limit, $p);

    }

    function retr_query($filters, $extra_cols = "")
    {
        return parent::retr_query($filters, $extra_cols . ", t.lead, t.thumbnail, t.legacy_thumbnail, t.file_version, cat_main.name AS main_category, CONCAT(cat_main.path, '/', IF(t.short = '', t.id, t.short)) AS art_path");
    }

    function paging()
    {
        foreach(array("offset" => 0, "limit" => 20, "date" => "") as $p => $default)
            if(is_numeric($_REQUEST[$p]))
                $this->browser_params[$p] = $_REQUEST[$p];
            else if(!isset($this->browser_params[$p]))
                $this->browser_params[$p] = $default;

        if(is_numeric($_REQUEST['page']))
            $this->browser_params["offset"] = ($_REQUEST["page"] - 1) * $this->browser_params["limit"];

        return $this;
    }

    function extra_panel()
    {
        return "";
    }

    function extra_self()
    {
        return "";
    }

    function panel($force = false)
    {
        $params = $this->browser_params;

        $offset = $params["offset"];
        $limit = $params["limit"];

        $page = floor($offset / $limit) + 1;
        $count = ceil($this->count / $limit);
        if($count <= 1 && (!$params["date"]) && !$offset && !$force) return "";

        /* Zbuduj listę opcji do przycisku "Na stronie" */
        $limits = array_flip(array(10, 50, 100));
        $limits[$limit] = true;
        foreach($limits as $n => $junk)
            $limits[$n] = "<option value='{$n}'" . ($n == $limit ? " selected" : "") .
                ">" . $n;
        $limits = implode("", $limits);

        /* Pobierz nazwę skryptu */
        $self = current(explode("?", $_SERVER["REQUEST_URI"], 2));

        /* Przygotuj linki poprzedni/następny */
        $date = urlencode($_REQUEST["date"]);
        $prev = ($page > 1 ?      ("{$self}?date={$date}&limit={$limit}&page=" . ($page - 1) . $this->extra_self()) : "javascript:void(0);");
        $next = ($page < $count ? ("{$self}?date={$date}&limit={$limit}&page=" . ($page + 1) . $this->extra_self()) : "javascript:void(0);");

        /* Wyświetl panel */
        $date = htmlspecialchars($_REQUEST["date"]);



        $out  = "<div id='filtr-box'>";
        $out .= "<form id='filtr-form' action='" . htmlspecialchars($self) . "' method='GET'>";
        $out .= "<a class='pozniej' href='{$prev}'>Późniejsze</a>";
        $out .= "<span class='rok'>Rok:</span> <input class='input inp-rok' type='text' name='date' value='{$date}' size=2 onchange='$(this).parent().parent().find(\"input[name=page]\").val(1);'>";
        $out .= "<span>Strona:</span> <input class='input' type='text' name='page' value='{$page}' size=2> <span>z {$count}</span>";
        $out .= "<span class='str-2'>Na stronie:</span> <select id='na-stronie' name='limit'>{$limits}</select>";
        $out .= "<span><input type='submit' value='Zmień' class='j-lista'></span>";
        $out .= "<a href='{$next}' class='wczesniej'>Wcześniejsze</a>";
        $out .= $this->extra_panel();
        $out .= "</form>";
        $out .= "</div>";
        return $out;
   }

    function access($perm)
    {
        return ($perm == "search");
    }
}