<?

class insider_newusers extends insider_table
{
    public $fields = array(
        "creat" =>  "Data utworzenia",

        "surname" =>    array("Nazwisko", "regexp" => ".+", "pub" => "B"),
        "name" =>       array("Imię", "pub" => "B"),
        "login" =>      array("Login", "regexp" => "[a-z][a-z0-9_.@]+", "suppress" => true, "empty" => true, "no" => "add", "pub" => "*"),
        "birthdate" =>  array("Data urodzenia", "type" => "date", "suppress" => true, "empty" => true),
        "phone" =>      array("Numer telefonu", "suppress" => true, "pub" => "T"),
    );

    protected $order = "creat DESC";

    function access($perm)
    {
        if(in_array($perm, explode(",", "edit,delete,add,history,export")))
            return false;

        return parent::access($perm);
    }

    function validate($id, &$data)
    {
        return(array("creat" => "Nie można zmieniać historii!"));
    }

    function view()
    {
        header("Location: /insider/users/view?id=" . $_REQUEST["id"]);
    }
}
