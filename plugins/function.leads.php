<?
    function smarty_function_leads($params, &$S)
    {
        access::$nologin = true;

        if(!isset($params["categ"])) return "";
        $categ = $params["categ"];

        if($categ{0} != "/")
            $categ = "/" . $S->getTemplateVars("path") . "/" . $categ;

        /* Wylistuj artykuły */
        $params["active"] = 1;
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

            if($thumb)
                $thumb = "<a href='" . $e["art_path"] . "'>" . $thumb . "</a>";

            if($params["syntax"] == "box")
                $art =
                    "<div class='box'>" .
                    "<a href='" . $e["art_path"] . "' class='ngl'>" .
                    "<span>" . htmlspecialchars($e["main_category"]) . "</span>" .
                    "</a>" .
                    "<h2>" . $e["title"] . "</h2>" .
                    $thumb .
                    "<a href='" . $e["art_path"] . "' class='bg'>" .
                    htmlspecialchars($e["lead"]) . "</a>" .
                    "</div>";
            else
                $art =
                    "<div class='ll'>" .
                    $thumb .
                    "<div>" .
                    "<h2><a href='" . $e["art_path"] . "'>" . $e["title"] . "</a></h2>" .
                    "<p>" .
                    "<span>" . htmlspecialchars($e["lead"]) . "</span>" .
                    "<span class='pza-readon'>" .
                    "<a href='" . $e["art_path"] . "'>" .
                    "Czytaj&nbsp;dalej&nbsp;&raquo;" .
                    "</a></span></p>" .
                    "</div></div>";
            $arts[$n] = $art;
        }
        $out .= implode("", $arts);
        $out .= "</div>";
        return $out;
    }
