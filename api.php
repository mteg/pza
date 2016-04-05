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


    case "officials":
    default:

        $a = array("right" => "i:w:pza:*|i:s:i:is", "format" => 3, "search" => 1);
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
