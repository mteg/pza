<?
require_once "common.php";
require_once "plugins/function.officials.php";
$S = get_Smarty();

if(isset($_REQUEST['op']))
    $op = $_REQUEST["op"];
else
    $op = "officials";



switch($op)
{
    case "profile":
        $u = new content_user($S);
        echo $u->get_page($_REQUEST["id"]);
        break;

    case "jaskinie":
        $o = new content_paperback($S);
        echo $o->category_index(336);
        break;

    case "events":
        vsql::$conf["db"] = "pza_test";
        $type = $_REQUEST["type"]; $year = $_REQUEST["year"];
        $types = array(); if(!$year) $year = date("Y");
        foreach(explode("|", $type) as $mask)
        {
            if(!($mask = trim($mask))) continue;
            $types[] = "g.type LIKE " . vsql::quote(strtr($mask, array("*" => "%")));
        }
        if(count($types))
            $evts = vsql::retr($qry = "SELECT id, options AS link, name, start, finish, city, address, IF(reguntil >= DATE(NOW()), 1, 0) AS open
                        FROM grounds AS g WHERE g.deleted = 0 AND (" . implode(" OR ", $types) . ")
                        AND YEAR(g.start) = " . vsql::quote($year) . " ORDER BY start, name LIMIT 100");
        $S->assign("events", $evts);
        if($_REQUEST["debug"]) echo $qry;
        $S->display("api/cal.html");
        break;

    case "officials":
    default:

        $a = array("right" => "i:w:pza:*|i:s:i:is|t:s:*", "format" => 3, "search" => 1);
        foreach(array("right", "format", "selector", "getto") as $k)
            if(isset($_GET[$k]))
                $a[$k] = $_GET[$k];

        $right_tab = array(
            "cnw" => "c:nw:*",
            "csk" => "c:sk:*",
            "iww" => "i:w:pza:*|i:s:i:is",
        );

        if(isset($right_tab[$a["right"]]))
            $a["right"] = $right_tab[$a["right"]];

        if($a["right"] && $a["selector"] == 1)
            $a["selector"] = $a["right"];

        if($_REQUEST["debug"])
            print_r($a);

        echo "\n<!-- get_officals call: " . $_SERVER["QUERY_STRING"] . "-->\n";

        echo smarty_function_officials($a, $S);
        break;
}
