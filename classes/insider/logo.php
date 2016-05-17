<?

class insider_logo extends insider_members
{
    protected $table = "members";

    function __construct()
    {
        parent::__construct(true);
    }

    function save()
    {
        $data = array_intersect_key($_POST, $this->fields);
        $err = $this->validate($id = access::getuid(), $data);
        if(!count($err))
        {
            $this->update($id, $data);
            $this->success("Zapis danych profilu");
        }

        $this->S->assign("data", $data);
        $this->S->assign("err", $err);
    }

    function route()
    {
        $id = $_REQUEST["id"];
        if(!preg_match('/^[0-9]+$/', $id)) die("Nieprawidlowy ID");

        if(access::has("edit(members)")) {
            $this->S->assign("xid", insider_content::gen_xid());
            $this->S->assign("id", $id);
            $this->S->assign("logo", insider_members::member_logo($id));
            $this->S->display("insider/logo.html");
        }
    }

    function commit()
    {
        $xid = $_REQUEST["xid"]; $m = array();
        $id = $_REQUEST["id"];
        if(!preg_match('/^[0-9a-z]+$/', $xid)) die("Nieprawidlowy XID");
        if(!preg_match('/^[0-9]+$/', $id)) die("Nieprawidlowy ID");

        foreach(scandir($xiddir = "upload/" . $xid) as $f)
        {
            if(preg_match('/\.(jpg|jpeg|png|gif)$/i', $f, $m))
            {
                if($current = insider_members::member_logo($id))
                    unlink($current);
                rename($xiddir . "/" . $f, "files/members/" . $id . "." . strtolower($m[1]));
                header("Location: /insider/logo?id=" . $id);
                exit;
            }
        }
        $this->S->display("insider/logo_failure.html");
    }

    function remove()
    {
        $id = $_REQUEST["id"];
        if(!preg_match('/^[0-9]+$/', $id)) die("Nieprawidlowy ID");

        if($current = insider_members::member_logo($id))
            unlink($current);
        header("Location: /insider/logo?id=" . $id);
    }

}
