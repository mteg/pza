<?
    function smarty_function_leads($params, &$S)
    {
        access::$nologin = true;

        if(!isset($params["categ"])) return "";
        $categ = $params["categ"];

        if($categ{0} != "/")
            $categ = "/" . $S->getTemplateVars("path") . "/" . $categ;

        /* Wylistuj artykuły */
        $o = new browser("article"); $out = "";
        $arts = $o->set($params)->set("categories", $categ)->paging()->ls();

        /* Jeśli jest takie życzenie, wyświetl panel do przeglądania wyników */
        if($params["browsepanel"])
            $out .= $o->panel();

        /* Wypisz treść leadów samą w sobie */
        $out .= "<div class='pza-leads'>";
        foreach($arts as $n => $e)
        {
            if($e["thumbnail"])
                $thumb = "<img src='/" . $e["thumbnail"] . "?thumb=1'/>";
            else if($e["legacy_thumbnail"])
                $thumb = "<img src='" . $e["legacy_thumbnail"] . "'/>";
            else
                $thumb = "";

            $art =
                "<div>" .
                "<h2>" . $e["title"] . "</h2>" .
                $thumb .
                "<p>" .
                "<span>" . htmlspecialchars($e["lead"]) . "</span>" .
                "<span class='pza-readon'>" .
                "<a href='" . $e["categories"] . '/' . $e["id"] . "'>" .
                "Czytaj&nbsp;dalej&nbsp;&raquo;" .
                "</a></span><div class='clear'></div></p>" .
                "</div>";
            $arts[$n] = $art;
        }
        $out .= implode("", $arts);
        $out .= "</div>";
        return $out;
    }
