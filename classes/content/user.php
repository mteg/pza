<?
class content_user extends content
{
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

        if(!$udata["member"])
            die("Osoba bez potwierdzonego czlonkowstwa - brak informacji");


        foreach(insider_users::$_fields as $f => $info)
        {
            if(!isset($info["pub"])) continue;
            if($info["pub"] != "*")
                if(!strstr($udata["flags"], $info["pub"]))
                    continue;
            $i[$f] = $udata[$f];
        }

        $this->S->assign("fields", insider_users::$_fields);
        $this->S->assign("data", $i);

        if(strstr($udata['flags'], "E"))
            $this->S->assign("entitlements", insider_users::list_entitlements($id));
        if(strstr($udata['flags'], "B"))
            $this->S->assign("memberships", insider_users::list_memberships($id));

        $this->S->assign("content", $this->S->fetch("user_page.html"));

        if(isset($_REQUEST["category"]))
            $catid = $_REQUEST["category"];
        else
            $catid = "/";

        $this->S->display("cat:" . $catid);
    }
}