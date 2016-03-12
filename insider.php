<?

/*
 * Przez skrypt insider.php przechodzi każde żądanie HTTP skierowane do portalu
 * wewnętrznego. Jego jedynym zadaniem jest skierowanie żądania do odpowiedniej
 * klasy w celu jego obsłużenia.
 *
 * Żądania w ramach portalu wewnętrznego mają zawsze postać
 * /insider/<klasa>[/<metoda>][?<parametry GET>]
 *
 * Takie żądanie kierowane jest do obsługi przez metodę "metoda()" klasy
 * "insider_klasa".
 *
 * Jeśli nie podano nazwy metody, wywoływana jest metoda route()
 *
 */

// todo łatwiejsze wstawianie (już istniejących) zdjęć
// todo komunikacja z zapisanymi na event (SMS / e-mail)


require_once "common.php";
require_once "classes/access.php";

if(!($action = $_REQUEST["action"]))
{
    header("Location: /insider/profile");
    exit;
}

/* Środek bezpieczeństwa */
if(!preg_match('/^[0-9a-zA-Z_]+$/', $action))
    fail(403, "Nieprawidłowa nazwa akcji: $action");

/* Nazwa klasy do wywołania */
$class_name = "insider_" . $action;

/* Załaduj klasę i utwórz jej instancję  */
$n = new $class_name;

/* Pobierz opcjonalną metodę klasy do wywołania - otrzymujemy nazwę metody z
   mod_rewrite w parametrze "method" */
$m = $_REQUEST["method"];
if(!$m) $m = "route";

/* Wykonaj akcję */
$n->$m();
