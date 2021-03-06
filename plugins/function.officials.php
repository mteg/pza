<?
    function entl_condition($right, $col = "r.short")
    {
        if(!is_array($right)) $right = explode("|", $right);
        $rcond = array();
        foreach($right as $ra)
            $rcond[] = "$col LIKE " . vsql::quote(strtr($ra, array("*" => "%", "?" => "_")));
        return "(" . implode(" OR ", $rcond) . ")";
    }

    function smarty_function_officials($params, &$S)
    {
        access::$nologin = true;

        $rcond = entl_condition($params["right"]);

        $out = "";
        if($params["selector"] || $params["search"])
        {
            $self = current(explode("?", $_SERVER["REQUEST_URI"], 2));

            $sname = $_REQUEST["sn"];
            $sassoc = $_REQUEST["sa"];
            $sright = $_REQUEST["sr"];

//            echo "blah";

            if(isset($params["selector"]))
            {
                $srights = array();
                foreach(vsql::retr("SELECT short, name FROM rights AS r WHERE " .
                                entl_condition($params["selector"]) .
                            " AND r.deleted = 0 ORDER BY r.name ", "short", "name") as $rid => $rn)
                    $srights[] = "<option value='" . htmlspecialchars($rid) . "'" .
                        ($rid == $sright ? " selected" : "") . ">" .
                        htmlspecialchars($rn) . "</option>";

                $srights = "<option value=''>-- wszystkie --</option>" . implode("", $srights);

                if(isset($params["getto"]))
                    $self = $params["getto"];

                $out  = "<div class='pza-browsepanel'>";
                $out .= "<form action='" . htmlspecialchars($self) . "' class='onchangesubmit' method='GET'>";
                $out .= "<span>Nazwisko <input type='text' name='sn' value='" . urlencode($sname) . "' size=10></span>";
                $out .= "<span>Klub <input type='text' name='sa' value='" . urlencode($sassoc) . "' size=10></span>";
                $out .= "<select name='active'><option value='1'>Ważne</option><option value=''>Wszystkie</option></select>";
                $out .= "<span><select name='sr'>$srights</select></span>";
                $out .= "<span><input type='submit' value='Szukaj'></span>";
                $out .= "</form>";
                $out .= "</div>";
            }

        }
        else
        {
            $sname = ""; $sassoc = ""; $sright = array();
        }

        if($sright && (!is_array($sright))) $sright = array($sright);
        if(!$sright) $sright = array();
//        IF(u.flags LIKE '%E%', u.surname, CONCAT(SUBSTR(u.surname, 1, 1), '...')) AS surname,
//                    IF(u.flags LIKE '%E%', u.name, CONCAT(SUBSTR(u.name, 1, 1), '...')) AS name,

        $list = vsql::retr($qry = "SELECT u.id,
                    u.surname,
                    u.name,
                    u.login,
                    GROUP_CONCAT(CONCAT(r.name, ' ', e.number) ORDER BY r.name SEPARATOR '|') AS entl,
                    GROUP_CONCAT(DISTINCT c.short ORDER BY c.short SEPARATOR '|') AS society,
                    MAX(e.due) AS due,
                    IF(MAX(e.due) > NOW(), 1, 0) AS status
                    FROM rights AS r
                    JOIN entitlements AS e ON e.right = r.id AND e.deleted = 0 AND e.public = 1 " .
                    ($_REQUEST["active"] ? " AND e.starts <= NOW() AND e.due >= NOW() " : "") .
                    "JOIN users AS u ON u.id = e.user AND u.deleted = 0 AND u.deathdate = 0 " . ($_REQUEST["anon"] ? "" : " AND u.flags LIKE '%E%'") . "
                    LEFT JOIN memberships AS m ON m.deleted = 0 AND u.id = m.user
                            AND m.starts <= NOW() AND m.due >= NOW()
                            AND m.flags LIKE '%R%'
                    LEFT JOIN members AS c ON c.deleted = 0
                            AND c.id = m.member AND c.pza = 1
                    WHERE " . $rcond .
                    ($sname ? (" AND u.surname LIKE " . vsql::quote("%" . $sname . "%")) : "") .
                    ($sassoc ? (" AND c.short LIKE " . vsql::quote("%" . $sassoc . "%")) : "") .
                    (count($sright) ? (" AND " . entl_condition($sright, "r.short")) : "") .
                    " GROUP BY u.id " .
                    " ORDER BY status DESC, u.surname, u.name", "");
        if($_REQUEST['debug']) echo $qry;


        $out .= "<table class='kluby-lista instr'>";
        $out .= "<thead>\n";
        $out .= "<th>#</th><th>Nazwisko</th><th>Imię</th><th>Klub</th>";
        if($params["format"] == 4)
            $out .= "<th>Uprawnienie</th>";
        if($params["format"] == 3 || $params["format"] == 4)
            $out .= "<th>Ważność licencji</th>";
        elseif($params["format"] == 2)
            $out .= "<th>Uprawnienia</th>";
        else
            $out .= "<th>Data ważności</th>";

        $out .= "\n</thead><tbody>\n";
        foreach($list as $c => $e)
        {
            $userlink = '~' . ($e["login"] ? $e["login"] : $e["id"]);

            $out .= "<tr>";
            $out .= "<td>" . $c . "</td>";
            if(isset($_REQUEST["nolink"]))
            {
                $out .= "<td>" . htmlspecialchars($e["surname"]) . "</td>";
                $out .= "<td>" . htmlspecialchars($e["name"]) . "</td>";
            }
            else
            {
                $out .= "<td><a href='$userlink'>" . htmlspecialchars($e["surname"]) . "</a></td>";
                $out .= "<td><a href='$userlink'>" . htmlspecialchars($e["name"]) . "</a></td>";
            }
            $out .= "<td>" . strtr(htmlspecialchars($e["society"]), array("|" => "<BR>")) . "</td>";
            if($params["format"] == 3 || $params["format"] == 4)
            {
//                $due = substr($e["due"], 0, 4);
                if($params["format"] == 4)
                    $out .= "<td>" . strtr(htmlspecialchars($e["entl"]), array("|" => "<BR>")) . "</td>";
                $due = $e["due"];
                if($due == "9999-12-31")
                    $due = "TAK";
                else
                    $due = "do " . $due;
                if(!$e["status"]) $due = "<strike>$due</strike>";
                $out .= "<td>" . $due . "</td>";
            }
            else if($params["format"] == 2)
                $out .= "<td>" . strtr(htmlspecialchars($e["entl"]), array("|" => "<BR>")) . "</td>";
            else
                $out .= "<td>" . htmlspecialchars($e["due"] == "9999-12-31" ? "---" : $e["due"]) . "</td>";
            $out .= "</tr>\n";
        }
        if(!count($list))
            $out .= "<tr><td colspan=5><i>(Brak wyników)</i></td></tr>";

        $out .= "</tbody>";
        $out .= "</table>\n";

        return $out;
    }
