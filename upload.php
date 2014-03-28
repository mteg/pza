<?
require_once "common.php";

$xid = $_REQUEST["xid"];
if(!preg_match('/^[0-9a-z]+$/', $xid))
    die("ERR Invalid XID {$xid}");

if(!file_exists("upload/{$xid}"))
    mkdir("upload/{$xid}");

$upload_handler = new UploadHandler(array(
    'script_url' => "/upload.php?xid={$xid}",
    'upload_dir' => 'upload/' . $xid . "/",
    'upload_url' => '/upload/' . $xid . '/',
    'delete_type' => "POST",
));
