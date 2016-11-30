<?

class insider_welcome extends insider_action
{
    function route()
    {
        $this->S->display("insider/welcome.html");
    }
}
