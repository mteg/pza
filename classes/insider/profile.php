<?

class insider_profile extends insider_users
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
        if(isset($_POST["surname"]))
            $this->save();
        else
        {
            $data = $this->fetch(access::getuid());
            if(!$data) die("Obiekt nie istnieje.");
            $this->S->assign("data", $data);
        }

        $this->S->display("insider/profile.html");
    }

    protected function do_transfer($mindue = "0000-00-00")
    {
        $data = $_POST; $err = array();

        $mindue = max(vsql::get("SELECT MAX(starts) AS due FROM memberships WHERE " .
            " deleted = 0 AND deleted = 0 " .
            " AND user = " . vsql::quote(access::getuid()), "due",
            "0000-00-00"), $mindue);

        if(!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $data["member_from"]))
            $err["member_from"] = "Nieprawidłowa data zmiany przynależności";
        else if($mindue && $mindue != "0000-00-00")
            if($data["member_from"] <= $mindue)
                $err["member_from"] = "Data zmiany przynależności musi być późniejsza niż {$mindue}";

        if(!count($err))
        {
            insider_checkin::subscribe($data["member"], $data["member_from"]);
            $this->success("Zmiana klubu");
        }

        $this->S->assign("data", $data);
        $this->S->assign("err", $err);
    }

    function membership()
    {
        $id = access::getuid();

        $this->S->assign("memberships", $this->list_memberships($id));

        $due = vsql::get("SELECT MAX(due) AS due FROM memberships WHERE " .
            " deleted = 0 AND deleted = 0 " .
            " AND flags LIKE '%R%' AND user = " . vsql::quote($id), "due",
            "0000-00-00");

//        if($due < "9999-12-31")
        {
            /* Może zapisać się do innego klubu */
            $this->S->assign("member_list", insider_checkin::member_list());

            if(isset($_POST["member"]))
                $this->do_transfer();
            else
                $this->S->assign("data", array("member_from" => date("Y-m-d", time() - 86400)));
        }

        $this->w("profile_memberships.html");
    }

    function entitlements()
    {
        $this->S->assign("entitlements", $this->list_entitlements_groups(access::getuid()));
        $this->w("profile_entitlements.html");
    }
}
