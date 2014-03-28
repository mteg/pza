<?
    function smarty_function_list($params, &$S)
    {
        access::$nologin = true;

        if(!isset($params["categ"])) return "";
        $categ = $params["categ"];

        if($categ{0} != "/")
            $categ = "/" . $S->getTemplateVars("path") . "/" . $categ;

        $o = new browser("article"); $out = "";
        if(!$params["noul"]) $out .= "<ul>";

        foreach($o->set($params)->set("categories", $categ)->paging()->ls() as $e)
            $out .= "<li><a href='" . $e["categories"] . '/' . $e["id"] . "'>" .
                    htmlspecialchars($e["title"]) . "</a></li>";

        if(!$params["noul"]) $out .= "</ul>";

        return $out;
    }
