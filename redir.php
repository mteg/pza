<?
    $catname = "";
    if(isset($_REQUEST["catname"]))
        if(preg_match('/^[a-z_-]+$/', $_REQUEST['catname']))
            $catname = $_REQUEST['catname'];

    header("Location: http://pza.org.pl/$catname");