<?

// todo dostęp tylko do swoich kategorii - alternatywne opcje

/**
 * Klasa, z której dziedziczą wszelkie skrypty wykonujące operacje w ramach portalu
 * wewnętrznego.
 *
 * Zawiera minimalny zestaw funkcjonalności, który jest potrzebny każdemu innemu
 * skryptowi (np. inicjacja silnika wzorców Smarty).
 *
 */
abstract class insider_action
{
    /**
     * Obiekt silnika wzorców Smarty.
     */
    protected $S;

    /**
     * Funkcja wywoływana przez insider.php w celu obsługi żądania HTTP
     * o ile nie wskazano w żądaniu konkretnej nazwy metody.
     */
    abstract function route();

    /**
     * Definicja menu dostępnego z prawej strony.
     */
    public $_menu = array(
        "Wyloguj" => "/insider/checkout",
        "System" => array(
            "Dziennik zmian" => "/insider/register|search(register)",
        ),
        "Moje konto" => array(
            "Moje dane" => "/insider/profile",
            "Moje zdjęcie" => "/insider/photo",
            "Zmiana hasła" => "/insider/passwd",
            "Przynależność klubowa" => "/insider/profile/membership",
            "Uprawnienia" => "/insider/profile/entitlements",
        ),
        "Treść strony" => array(
            "Moje kategorie" => "/insider/favcats?type=article|search(categories)",
            "Wszystkie kategorie" => "/insider/categories?type=article|search(categories)",
            "Artykuły" =>  "/insider/content?type=article#order=mod+DESC|search(content)",
        ),
        "Zdjęcia" => array(
            "Galerie" => "/insider/categories?type=photo|search(categories)",
            "Zdjęcia" =>  "/insider/content?type=photo|search(content)",
        ),
        "Dokumenty do pobrania" => array(
            "Kategorie" =>  "/insider/categories?type=file|search(content)",
            "Pliki" =>  "/insider/content?type=file|search(content)",
        ),
        "Publikacje" => array(
            "Serie" =>  "/insider/categories?type=paperback|search(categories)",
            "Zeszyty" =>  "/insider/content?type=paperback|search(content)",
            "Artykuły" =>  "/insider/content?type=paperback_article|search(content)",
        ),
        "Kluby" => array(
            "Lista klubów" => "/insider/members|search(members)",
            "Wszystkie członkostwa" => "/insider/memberships#status=1&current=1|search(memberships)",
        ),
        "Osoby" => "/insider/users",
        "Wydarzenia" => array(
            "Szkolenia i unifikacje" => "/insider/grounds?type=event:s",
        ),
        "Uprawnienia" => array(
            "Rodzaje uprawnień" => "/insider/rights|search(rights)",
            "Wszystkie uprawnienia" => "/insider/entitlements|search(entitlements)"
        ),
/*        "Osiągnięcia" => array(
            "Szranki" => "/insider/grounds|delete(grounds)",
            "Osiągnięcia" => "/insider/achievements|search(achievements)"
        ),*/
        "Wspinaczka górska" => array(
            "Instruktorzy" => "/insider/entitlements?family=i:w#current=1|entmgr(i:w)",
/*            "Lista dróg" => "/insider/grounds?type=nature:climb",
            "Baza przejść" => "/insider/achievements?type=nature:climb",
            "Moje przejścia" => "/insider/achievements?type=nature:climb#user=self",
            "Wyprawy himalajskie PZA" => "/insider/grounds?type=exp:h|search(grounds)",*/
        ),
        "Alpinizm jaskiniowy" => array(
            "Instruktorzy" => "/insider/entitlements?family=i:j#current=1|entmgr(i:j)",
            "Kadra narodowa" => "/insider/entitlements?family=r:tj#current=1|entmgr(r:tj)",
            "Badania lekarskie" => "/insider/entitlements?family=med:j#current=1|entmgr(med:j)",
/*            "Lista jaskiń" => "/insider/grounds?type=nature:cave",
            "Przejścia jaskiniowe" => "/insider/achievements?type=nature:cave",
            "Moje przejścia" => "/insider/achievements?type=nature:cave#user=self",
            "Wyprawy jaskiniowe PZA" => "/insider/grounds?type=exp:cave|search(grounds)",*/
        ),
/*        "Wspinaczka sportowa" => array(
            "Zawody PZA" => "/insider/grounds?type=comp:s:pza",
            "Zawody inne" => "/insider/grounds?type=comp:s:other",
            "Trenerzy" => "/insider/entitlements?family=i:s#current=1|entmgr(i:s)",
            "Sędziowie" => "/insider/entitlements?family=s:s#current=1|entmgr(s:s)",
            "Konstruktorzy" => "/insider/entitlements?family=k:j#current=1|entmgr(k:s)",
            "Kadra narodowa" => "/insider/entitlements?family=r:s#current=1|entmgr(r:s)",
            "Badania lekarskie" => "/insider/entitlements?family=med:r#current=1|entmgr(med:r)",
            "Moje wyniki" => "/insider/achievements?type=comp:s#user=self",
            // todo moje wyniki jedne i drugie
        ),
        "Narciarstwo wysokogórskie" => array(
            "Zawody PZA" => "/insider/grounds?type=comp:nw:pza",
            "Zawody inne" => "/insider/grounds?type=comp:nw:other",
            "Instruktorzy" => "/insider/entitlements?family=i:nw#current=1|entmgr(i:nw)",
            "Sędziowie" => "/insider/entitlements?family=s:nw#current=1|entmgr(s:nw)",
            "Kadra narodowa" => "/insider/entitlements?family=r:nw#current=1|entmgr(r:nw)",
            "Badania lekarskie" => "/insider/entitlements?family=med:j#current=1|entmgr(med:nw)",
            "Moje wyniki" => "/insider/achievements?type=comp:nw#user=self",
        ),
*/
    );

    /**
     * Zmienne przechowujące URL do bieżącej klasy (/insider/<nazwa>) oraz
     * parametry GET podane w żądaniu.
     */
    public $source = "", $params = "";

    public $username;

    /**
     * Przefiltruj rekursywnie tablicę z menu, pozostawiając tylko pozycje,
     * do których zalogowany użytkownik posiada prawo dostępu.
     */
    static function filter_menu(&$menu)
    {
        foreach($menu as $k => $a)
        {
            if(!is_array($a))
                $a = explode("|", $a);

            if(isset($a[0]))
            {
                $stays = !isset($a[1]);
                for($i = 1; isset($a[$i]); $i++)
                    $stays = $stays || access::has($a[$i]);

                if($stays)
                    $menu[$k] = $a[0];
                else
                    unset($menu[$k]);
            }
            else
            {
                static::filter_menu($menu[$k]);
                if(!count($menu[$k]))
                    unset($menu[$k]);
            }
        }

    }

    /**
     * Konstruktor przygotowuje środowisko skryptu do pracy.
     */
    function __construct()
    {
        /* Utwórz obiekt Smarty do pracy */
        $this->S = get_Smarty();
        $this->S->assign("request", $_REQUEST);
        $this->S->assign("this", $this);

        list($source, $this->params) = explode("?", $_SERVER["REQUEST_URI"], 2);
        $this->source = $this->classpath();
        if($this->username = access::getlogin())
            if($_SERVER["HTTPS"] != "on")
            {
                header("Location: https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);
                exit;
            }

            // todo can be eliminated, templates may use this->source, this->params
        $this->S->assign(array(
            "source" => $this->source,
            "params" => $this->params,
        ));

        /* Zajmij się menu */

        /* Cache administrowania klubami - i tak
            jest sprawdzane jeszcze raz w memberships */
        session_start();
        if(is_array($_SESSION["adm_of"]))
            foreach($_SESSION["adm_of"] as $org => $name)
                $this->_menu["Kluby"][$name] = "/insider/memberships?org=" . $org . "#status=1&current=1";

        $this->filter_menu($this->_menu);
    }

    /**
     * Podaj URL do wywołanej klasy (/insider/<nazwa klasy>)
     */
    static function classpath()
    {
        return "/" . str_replace("_", "/", get_called_class());
    }

    /**
     * Metoda pomocnicza: wyświetl lakoniczny komunikat o powodzeniu
     * jakieś operacji.
     */
    protected function success($title = "",
                               $msg = "Operacja wykonana prawidłowo.")
    {
        $this->S->assign("title", $title);
        $this->S->assign("msg", $msg);

        $this->S->display("insider/success.html");
        exit;
    }

    // todo implement this shorthand
    /**
     * Wyrenderuj wskazany wzorzec HTML za pomocą silnika Smarty
     */
    protected function w($template = false)
    {
        if(!$template)
            $template = strtr(get_called_class(), array("_" => "/")) . ".html";
        else
            $template = "insider/" . $template;

        $this->S->display($template);
    }
}
