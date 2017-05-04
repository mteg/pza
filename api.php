<?
require_once "common.php";
require_once "plugins/function.officials.php";

function evdates($info)
{
    if($info["start"] == $info["finish"])
        $info["date"] = $info["start"];
    else if(substr($info["start"], 0, 7) == substr($info["finish"], 0, 7))
        $info["date"] = $info["start"] . "/" . substr($info["finish"], 8);
    else
        $info["date"] = $info["start"] . "/" . $info["finish"];

    return $info;

}

$S = get_Smarty();
$S->assign("request", $_REQUEST);

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
//        vsql::$conf["db"] = "pza_test";
        $type = $_REQUEST["type"]; $year = $_REQUEST["year"];
        $types = array(); if(!$year) $year = date("Y");
        $typeopts = explode("|", $type);
        foreach($typeopts as $mask)
        {
            if(!($mask = trim($mask))) continue;
            if(isset($_REQUEST["typef"]))
                if(strlen($_REQUEST["typef"]))
                    if($_REQUEST["typef"] != $mask)
                        continue;
            $types[] = "g.type LIKE " . vsql::quote(strtr($mask, array("*" => "%")));
        }
        if(count($typeopts) > 1)
            $S->assign("filter", array("" => "--- wszystkie ---") + array_intersect_key(insider_grounds::$fields_template["type"]["options"], array_flip(explode("|", $type))));

        if(count($types))
            $evts = vsql::retr($qry = "SELECT id, options AS link, name, start, finish, city, address, IF(reguntil >= DATE(NOW()), 1, 0) AS open
                        FROM grounds AS g WHERE g.deleted = 0 AND (" . implode(" OR ", $types) . ")
                        AND YEAR(g.start) = " . vsql::quote($year) . " ORDER BY start, name LIMIT 100");
        else
            $evts = array();

        foreach($evts as $evid => $evdata)
            $evts[$evid]["links"] = explode("|", $evdata["link"]);

        $have_results = vsql::id_retr(array_keys($evts), "a.ground", "SELECT a.ground, a.id FROM achievements AS a WHERE
              a.deleted = 0 AND a.position = 1 AND ", "ground", "", "id");


        foreach($evts as $id => $info)
            $evts[$id] = evdates($info);

        $S->assign("events", $evts);
        $S->assign("results", $have_results);
        if($_REQUEST["debug"]) echo $qry;
        $S->display("api/cal.html");
        break;

    case "results":
        $event = $_REQUEST["event"]; $res = array();
        foreach(vsql::retr("SELECT a.id, cat.name AS cat_name, a.position, u.surname, u.name
                            FROM grounds AS ev
                                JOIN achievements AS a ON a.ground = ev.id AND a.deleted = 0
                                LEFT JOIN grounds AS cat ON a.categ = cat.id
                                JOIN users AS u ON u.id = a.user AND u.deleted = 0
                                WHERE ev.type LIKE '%' AND ev.id = " . vsql::quote($event) .
                            " ORDER BY cat_name, CAST(a.position AS signed), u.surname, u.name") as $i)
        {
            if(!isset($res[$i["cat_name"]])) $res[$i["cat_name"]] = array();
            $res[$i["cat_name"]][] = $i;
        }

        $S->assign("event", evdates(vsql::get("SELECT name, start, finish, city FROM grounds WHERE (type LIKE 'comp:%' OR type LIKE 'event:%' OR type LIKE 'course:%') AND id = ". vsql::quote($event))));
        $S->assign("results", $res);
        $S->display("api/results.html");
        break;

    case "members":
        access::$nologin = true;
        
        $m = new insider_members();
        $S->assign("members", $m->get_list());
        $S->assign("profiles", insider_members::$_fields['profile']['options']);
        $S->display("api/members.html");
        break;

    case "ranking":

        $S->assign(insider_rank::get_rank($_REQUEST["id"]));
        $S->display("api/ranking.html");
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
            "kwss" => "s:s:*",
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
