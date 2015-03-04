<?
    function smarty_function_officials($params, &$S)
    {
        access::$nologin = true;

        $right = strtr($params["right"], array("*" => "%", "?" => "_"));
        $list = vsql::retr("SELECT u.id, u.surname, u.name, u.login,
                    GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ',') AS society,
                    IF(e.due = '9999-12-31', '---', e.due) AS due
                    FROM rights AS r
                    JOIN entitlements AS e ON e.right = r.id AND e.deleted = 0
                            AND e.starts <= NOW() AND e.due >= NOW()
                    JOIN users AS u ON u.id = e.user AND u.deleted = 0
                    LEFT JOIN memberships AS m ON m.deleted = 0 AND u.id = m.user
                            AND m.starts <= NOW() AND m.due >= NOW()
                            AND m.flags LIKE '%R%'
                    LEFT JOIN members AS c ON c.deleted = 0
                            AND c.id = m.member AND c.pza = 1
                    WHERE r.short LIKE " . vsql::quote($right) .
                    " GROUP BY u.id ORDER BY u.surname, u.name", "");

        $out = "<table class='sortable personnel'>";
        $out .= "<thead>\n";
        $out .= "<th>#</th><th>Nazwisko</th><th>Imię</th><th>Klub</th><th>Data ważności</th>";
        $out .= "\n</thead><tbody>\n";
        foreach($list as $c => $e)
        {
            $userlink = '/~' . ($e["login"] ? $e["login"] : $e["id"]) .
                    '?category=' . $S->getTemplateVars('category_id');

            $out .= "<tr>";
            $out .= "<td>" . $c . "</td>";
            $out .= "<td><a href='$userlink'>" . htmlspecialchars($e["surname"]) . "</a></td>";
            $out .= "<td><a href='$userlink'>" . htmlspecialchars($e["name"]) . "</a></td>";
            $out .= "<td>" . htmlspecialchars($e["society"]) . "</td>";
            $out .= "<td>" . htmlspecialchars($e["due"]) . "</td>";
            $out .= "</tr>\n";
        }
        $out .= "</tbody>";
        $out .= "</table>\n";

        return $out;
    }
