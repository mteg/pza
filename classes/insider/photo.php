<?

class insider_photo extends insider_users
{
    protected $table = "users";

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
        $this->S->assign("xid", insider_content::gen_xid());
        $this->S->assign("photo", content_user::user_photo());
        $this->S->display("insider/photo.html");
    }

    function commit()
    {
        $xid = $_REQUEST["xid"]; $m = array();
        if(!preg_match('/^[0-9a-z]+$/', $xid)) die("Nieprawidlowy XID");
        foreach(scandir($xiddir = "upload/" . $xid) as $f)
        {
            if(preg_match('/\.(jpg|jpeg|png|gif)$/i', $f, $m))
            {
                if($current = content_user::user_photo())
                    unlink($current);
                rename($xiddir . "/" . $f, "files/users/" . access::getuid() . "." . strtolower($m[1]));
                header("Location: /insider/photo");
                exit;
            }
        }
        $this->S->display("insider/photo_failure.html");
    }

    function remove()
    {
        if($current = content_user::user_photo())
            unlink($current);
        header("Location: /insider/photo");
    }

}
