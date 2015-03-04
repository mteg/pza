<?
    function smarty_function_articles($params, &$S)
    {
        access::$nologin = true;

        if(!isset($params["categ"])) return "";
        $categ = $params["categ"];

        if($categ{0} != "/")
            $categ = "/" . $S->getTemplateVars("path") . "/" . $categ;

        /* Wylistuj artykuły */
        $params["active"] = 1;
        $o = new article_browser();
        $arts = $o->set($params)->set("categories", $categ)->filters()->paging()->ls();

        $S->assign("arts", $arts);
        $S->assign("br", $o);
        $S->assign("panel", $o->panel(true));
        return $S->fetch("paperback/articles.html");
    }
