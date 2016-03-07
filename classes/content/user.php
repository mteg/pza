<?
class content_user extends content
{
    static function user_photo($id = false)
    {
        if(!$id) $id = access::getuid();
        foreach(array("png", "gif", "jpg", "jpeg") as $ext)
            if(file_exists($fp = "files/users/" . $id . "." . $ext))
                return($fp);
        return false;
    }

    function render_object($id, $path)
    {
        /* Check for active PZA membership */
        $udata = vsql::get("SELECT u.*, c.id AS member
            FROM users AS u
            LEFT JOIN memberships AS m ON m.deleted = 0 AND u.id = m.user
                    AND m.starts <= NOW() AND m.due >= NOW()
                    AND m.flags LIKE '%R%'
            LEFT JOIN members AS c ON c.deleted = 0
                    AND c.id = m.member AND c.pza = 1
            WHERE u.deleted = 0 AND " .
            (is_numeric($id) ? "u.id" : "u.login") . " = " . vsql::quote($id) .
            " GROUP BY u.id LIMIT 1");
        $i = array(); unset($udata["access"]);

        if(!($id = $udata["id"]))
            die("Brak takiej osoby");


        foreach(insider_users::$_fields as $f => $info)
        {
            if(!isset($info["pub"])) continue;
            if($info["pub"] != "*")
                if(!strstr($udata["flags"], $info["pub"]))
                    continue;
            $i[$f] = $udata[$f];
        }
        $i["id"] = $udata["id"];

        $ss = str_split($udata["flags"]);
        $this->S->assign("data", $i);
        $this->S->assign("pub", array_combine($ss, $ss));
        $this->S->assign("photo", $this->user_photo($id));

        $entls = insider_users::list_entitlements($id, " AND e.public = 1");
        if(strstr($udata['flags'], "E"))
            $this->S->assign("entitlements", $entls);

        if(strstr($udata['flags'], "B"))
            $this->S->assign("memberships", insider_users::list_memberships($id));

        if((!$udata["member"]) && !count($entls))
            die("Osoba bez potwierdzonego członkowstwa - brak informacji");

        $this->S->assign("content", $this->S->fetch("user_page.html"));

        if(isset($_REQUEST["category"]))
            $catid = $_REQUEST["category"];
        else
            $catid = "/";

        $this->S->display("cat:" . $catid);
    }
}
