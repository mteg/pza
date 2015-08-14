<?
require "smarty/Smarty.class.php";

spl_autoload_register(function ($class) {

    /* Workaround for cwd changing on script shutdown */
    static $cwd = false;
    if(!$cwd) $cwd = getcwd();

    /* Try including classes/<class>.php */
    $file = $cwd . '/classes/' . $class . '.php';
    if(file_exists($file))
        include($file);
    else
    {
        /* Try including classes/<family>/<class>.php */
        $m = array();
        if(preg_match('/^([a-z]+)_([^_]+)(_.*)?$/', $class, $m))
        {
            $file = $cwd . '/classes/' . $m[1] . '/' . $m[2] . '.php';
            if(!file_exists($file))
            {
                /* Cannot include this class. Fail. */
                header("Content-type: text/plain; charset=utf-8");
                print_r(debug_backtrace());
            }
            else
                include($file);

            /* Call initialization function */
            $fname = "__" . $m[1] . "_init";

            if(is_callable("{$class}::{$fname}"))
                $class::$fname();
        }
    }
});

function get_Smarty()
{
    $S = new Smarty;

    $S->setTemplateDir("templates");
    $S->setCompileDir("../templates_c");
    $S->addPluginsDir("plugins");

    $S->left_delimiter = "{{";
    $S->right_delimiter = "}}";
    
    $S->compile_check = true;
    $S->force_compile = true;
    
    return $S;
}

function fail($status, $message)
{
    header("Content-type: text/plain; charset=utf-8");
    die($status . " " . $message);
}

/* NCC6 samodzielnie escape-uje ciągi wstawiane do zapytań SQL, jeśli zatem w PHP
   jest włączona opcja magic_quotes, escape-owanie wykonane automatycznie przez PHP
   musi zostać cofnięte */
if(get_magic_quotes_gpc()) {
    function stripslashes_deep($value)
    {
        $value = is_array($value) ? array_map('stripslashes_deep', $value) : stripslashes($value);
        return $value;
    }

    $_POST = array_map('stripslashes_deep', $_POST);
    $_GET = array_map('stripslashes_deep', $_GET);
    $_COOKIE = array_map('stripslashes_deep', $_COOKIE);
    $_REQUEST = array_map('stripslashes_deep', $_REQUEST);
}

// todo pamiętać, żeby usunąć ten środek tymczasowy
/* Środek tymczasowy */
/*
ob_start(function($content) {
    return str_replace("http://pza.org.pl/", "http://test.jaszczur.org/", $content);
});
*/

