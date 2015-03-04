<?
    function smarty_function_list($params, &$S)
    {
        access::$nologin = true;

        if(!isset($params["categ"])) return "";
        $categ = $params["categ"];

        if($categ{0} != "/")
            $categ = "/" . $S->getTemplateVars("path") . "/" . $categ;

        if(isset($params["skip"]))
            $skip = array_map("trim", explode(",", $params["skip"]));
        else
            $skip = array();

        $ctype = isset($params["ctype"]) ? $params["ctype"] : "article";
        $o = new browser($ctype); $out = "";
        if(!$params["noul"]) $out .= "<ul>";

        $params["active"] = 1;

        foreach($o->set($params)->set("categories", $categ)->paging()->ls() as $e)
        {
            if($e["short"])
                if(in_array($e["short"], $skip))
                    continue;
            $link = $e["categories"] . '/' . ($e["short"] ? $e["short"] : $e["id"]);
            $out .= "<li><a href='" . $link . "'>" .
                    htmlspecialchars($e["title"]) . "</a></li>";
        }
        if(!$params["noul"]) $out .= "</ul>";

        return $out;
    }
