<?

class insider_checkout
{
    function route()
    {
        session_start();
        unset($_SESSION["user_id"]);
        session_destroy();

        header("Location: http://pza.org.pl/");
        exit;
    }
}
