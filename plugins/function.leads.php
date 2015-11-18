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
        if($params["syntax"] == "box")
            $out .= "<div class='pza-leads'>";
        else
            $out .= "<ul class='news'>";
        foreach($arts as $n => $e)
        {
            if($e["thumbnail"])
                $thumb = "<img src='/" . $e["thumbnail"] . "?thumb=1'/>";
            else if($e["legacy_thumbnail"])
                $thumb = "<img src='" . $e["legacy_thumbnail"] . "'/>";
            else if($params["syntax"] != "box")
                $thumb = "<img src='/img/zaslepka.gif' alt='Brak zdjęcia'/>";
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
                    "<li>
                     <a href='" . $e["art_path"] . "'><h2>" . $e["title"] . "</h2></a>
                     <div class='foto-news'>$thumb</div>
                     <p>" . htmlspecialchars($e["lead"]) . "</p>
                     </li>";

            $arts[$n] = $art;
        }
        $out .= implode("", $arts);
        if($params["syntax"] == "box")
            $out .= "</div>";
        else
            $out .= "</ul>";
        return $out;
    }
