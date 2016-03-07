<?
require_once "common.php";
require_once "plugins/function.officials.php";
$S = get_Smarty();
echo smarty_function_officials(array("right" => "i:w:pza:*|i:s:i:is", "format" => 3, "search" => 1), $S);
