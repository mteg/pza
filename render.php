<?
    /*
        Wyświetl zwykłą stronę
    */

    require_once "common.php";

    /* Get content class */
    function co($id, $table = "content", $type = false)
    {
        global $S;
        if(!$type) $type = vsql::get("SELECT type FROM {$table} WHERE deleted = 0 AND id = " . vsql::quote($id), "type");
        if(!preg_match('/^[a-z_]+$/', $type)) die("ERR Invalid content type: " . $type);
        if(!file_exists("classes/content/{$type}.php")) die("ERR Unknown content type: " . $type);
        $classname = "content_" . $type;
        return new $classname($S);
    }

    function dispatch_object($id, $type = false)
    {
        co($id, "content", $type)->render_object($id, $_REQUEST["q"]);
        exit;
    }

    function dispatch_category($id, $type = false)
    {
        co($id, "categories", $type)->render_category($id, $_REQUEST["q"]);
        exit;
    }

    $S = get_Smarty();
    $S->registerResource('cat', new tpl_cat_loader());
    $S->registerResource('art', new tpl_art_loader());

    $q = $_REQUEST["q"];

    $m = array();

    /* Obsługa różnych składni */

    /* Odnośniki do starego systemu */
    if(preg_match('/.*(news|article)\.acs$/', $q, $m))
        if(!($q = vsql::get("SELECT id FROM content WHERE deleted = 0 AND legacy_id = " . vsql::quote($_REQUEST["id"]), "id", 0)))
            fail(404, "Nieznany artykuł z poprzedniego systemu: ". $_REQUEST["id"]);


    /* Cyfry oznaczające identyfikator obiektu */
    if(is_numeric($q))
        dispatch_object($q);
    /* Ścieżka kategorii zakończona numerem plus ew. rozszerzeniem/rozszerzeniami */
    else if(preg_match('#^([a-z][a-z0-9_]*/)*([0-9]+)(\.[a-z0-9.]+)?$#i', $q, $m))
        dispatch_object($m[2]);
    /* Ścieżka kategorii zakończona skrótem */
    else if(preg_match('#^(([a-z][a-z0-9_]*/)*)([a-z][0-9a-z_.~-]*)/?$#', $q, $m) || $q == "/" || $q == "")
    {
        if($q == "" || $q == "/")
            $m = array(1 => "/", 3 => "/");
        if(substr($q, -1) == "?")
            $q = substr($q, 0, strlen($q) - 1);

        $S->assign("urlpath", $catpath = trim($m[1], "/"));

        if($o = vsql::get($query = "SELECT a.id, a.type
                        FROM content AS a
                        JOIN category_map AS cm ON cm.article = a.id AND cm.main = 1
                        JOIN categories AS cat ON cat.id = cm.category
                        WHERE cat.path = " . vsql::quote("/" . $catpath) .
                        " AND a.short = " . vsql::quote($m[3]) .
                        " AND a.deleted = 0 LIMIT 1"))
            dispatch_object($o["id"], $o["type"]);

        $S->assign("urlpath", $catpath = "/" . $q);
        if($o = vsql::get($qry = "SELECT cat.id, cat.type
                        FROM categories AS cat
                        WHERE cat.path = " . vsql::quote($catpath) .
                        " ORDER BY cat.id DESC LIMIT 1"))
            dispatch_category($o["id"], $o["type"]);
    }
    /* Username */
    else if(preg_match('#^~([a-zA-Z0-9_.]+)$#', $q, $m))
    {
        co($m[1], "users", "user")->render_object($m[1], $q);
        exit;

    }
    /* Club name */
    else if(preg_match('#^_([a-zA-Z0-9_.]+)$#', $q, $m))
    {
        co($m[1], "members", "member")->render_object($m[1], $q);
        exit;

    }

    fail(404, "Nieznana składnia zasobu: $q");
