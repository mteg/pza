<?
class content_member extends content
{
    function render_object($id, $path)
    {
        /* Check for active PZA membership */
        $udata = vsql::get("SELECT m.*
            FROM members AS m
            WHERE m.deleted = 0 AND " .
            (is_numeric($id) ? "m.id" : "m.designation") . " = " . vsql::quote($id) .
            " GROUP BY m.id LIMIT 1");

        if(!($id = $udata["id"]))
            die("Brak takiego klubu");

        $this->S->assign("fields", insider_members::$_fields);
        $this->S->assign("data", $udata);
        $this->S->assign("content", $this->S->fetch("member_page.html"));

        if(isset($_REQUEST["category"]))
            $catid = $_REQUEST["category"];
        else
            $catid = "/";

        $this->S->display("cat:" . $catid);
    }
}
