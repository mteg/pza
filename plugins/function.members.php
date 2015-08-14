<?
    function smarty_function_members($params, &$S)
    {
        access::$nologin = true;

        $list = vsql::retr("SELECT m.id, m.designation, m.name, m.town
                    FROM members AS m
                    WHERE m.deleted = 0 AND m.pza = 1 " .
            (isset($params["profile"]) ? (" AND m.profile LIKE " . vsql::quote("%" . $params["profile"] . "%")) : "") .
                    " ORDER BY m.name", "");

        $out = "<table class='sortable kluby-lista'>";
        $out .= "<thead>\n";
        $out .= "<th>#</th><th>Nazwa</th><th>Miasto</th>";
        $out .= "\n</thead><tbody>\n";
        foreach($list as $c => $e)
        {
            $memberlink = '/_' . ($e["designation"] ? $e["designation"] : $e["id"]) .
                '?category=' . $S->getTemplateVars('category_id');


            $out .= "<tr>";
            $out .= "<td>" . $c . "</td>";
            $out .= "<td><a href='$memberlink'>" . htmlspecialchars($e["name"]) . "</a></td>";
            $out .= "<td>" . htmlspecialchars($e["town"]) . "</td>";
            $out .= "</tr>\n";
        }
        $out .= "</tbody>";
        $out .= "</table>\n";

        return $out;
    }
