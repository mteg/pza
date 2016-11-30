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
        "Menu główne" => "/insider/welcome",
        "www.pza.org.pl" => "http://pza.org.pl/",
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
            "Galerie" => "/insider/categories?type=photo|search(categories)",
            "Zdjęcia" =>  "/insider/content?type=photo|search(content)",
            "Kategorie" =>  "/insider/categories?type=file|search(content)",
            "Pliki" =>  "/insider/content?type=file|search(content)",
            "Serie" =>  "/insider/categories?type=paperback|search(categories)",
            "Zeszyty" =>  "/insider/content?type=paperback|search(content)",
            "Teksty" =>  "/insider/content?type=paperback_article|search(content)",
        ),
/*        "Zdjęcia" => array(
        ),
        "Dokumenty do pobrania" => array(
        ),
        "Publikacje" => array(
        ),*/
        "Kluby" => array(
            "Lista klubów" => "/insider/members|search(members)",
            "Członkowie klubów" => "/insider/memberships?restrict=1#&current=1|search(memberships)",
        ),
        "Osoby" => "/insider/users",
/*       "Wydarzenia" => array(
           "Szkolenia i unifikacje" => "/insider/grounds?type=event:s",
        ),*/
/*        "Osiągnięcia" => array(
            "Szranki" => "/insider/grounds|delete(grounds)",
            "Osiągnięcia" => "/insider/achievements|search(achievements)"
        ),*/
        "Wspinaczka górska" => array(
            "Instruktorzy" => "/insider/entitlements?family=i:w|entmgr(i:w)",
            "Licencje zawodników" => "/insider/entitlements?family=l:w|entmgr(l:w)",
            "Kadra narodowa" => "/insider/entitlements?family=ka:kn:ww#due=eoy|entmgr(ka:kn:ww)",
            "Lista dróg" => "/insider/grounds?type=nature:climb",
            "Moje przejścia" => "/insider/achievements?user=self&type=nature:climb",
            "Wszystkie przejścia" => "/insider/achievements?type=nature:climb|search(grounds)",
        ),
        "Wspinaczka skalna" => array(
            "Ekiperzy" => "/insider/entitlements?family=e:sk|entmgr(e:sk)",
            "Kadra narodowa" => "/insider/entitlements?family=ka:kn:sk#due=eoy|entmgr(ka:kn:sk)",
            "Klasy sportowe" => "/insider/entitlements?family=c:sk|entmgr(c:sk)",
        ),
        "Wszystkie dyscypliny" => array(
            "Kadra narodowa - bieżąca" => "/insider/entitlements?family=ka:kn&open=1#due=eoy|entmgr(ka:kn)",
            "Kadra narodowa - archiwum" => "/insider/entitlements?family=ka:kn&open=1|entmgr(ka:kn)",
            "Badania lekarskie - aktualne" => "/insider/entitlements?family=med&open=1#due=eoy|entmgr(med)",
            "Badania lekarskie - wszystkie" => "/insider/entitlements?family=med&open=1|entmgr(med)",
            "Zgody - bieżące" => "/insider/entitlements?family=d:pza#due=eoy|entmgr(d:pza)",
            "Zgody - archiwum" => "/insider/entitlements?family=d:pza|entmgr(d:pza)",
        ),
        "Alpinizm jaskiniowy" => array(
            "Instruktorzy" => "/insider/entitlements?family=i:j|entmgr(i:j)",
            "Kadra narodowa" => "/insider/entitlements?family=ka:kn:aj#due=eoy|entmgr(ka:kn:aj)",
            "Badania lekarskie" => "/insider/entitlements?family=med:j|entmgr(med:j)",
            "Lista jaskiń" => "/insider/grounds?type=nature:cave",
            "Moje przejścia" => "/insider/achievements?type=nature:cave&user=self|search(grounds)",
            "Wszystkie przejścia" => "/insider/achievements?type=nature:cave",
        ),
        "Wspinaczka sportowa" => array(
            "Nadchodzące zawody" => "/insider/upcoming?type=comp:s:pza",
            "Zawody krajowe" => "/insider/grounds?type=comp:s:pza",
            "Zawody międzynarodowe" => "/insider/grounds?type=comp:s:other",
            "Badania lekarskie" => "/insider/entitlements?family=med:s|entmgr(med:s)",
            "Licencje" => "/insider/entitlements?family=l:s|entmgr(l:s)",
            "Kadra narodowa" => "/insider/entitlements?family=ka:kn:s#due=eoy|entmgr(ka:kn:s)",
            "Sędziowie" => "/insider/entitlements?family=s:s|entmgr(s:s)",
            "Trenerzy" => "/insider/entitlements?family=t:s|entmgr(t:s)",
            "Klasy sportowe" => "/insider/entitlements?family=c:s|entmgr(c:s)",
            "Konstruktorzy" => "/insider/entitlements?family=k:s|entmgr(k:s)",
/*            "Badania lekarskie" => "/insider/entitlements?family=med:r#current=1|entmgr(med:r)", */
            "Moje wyniki" => "/insider/achievements?type=comp:s#user=self",
            "Kategorie rywalizacji" => "/insider/grounds?type=cat:s|gndmgr(cat:s)",
            "Rankingi PZA" => "/insider/rank",
            "Definicje rankingów" => "/insider/grounds?type=rank:s|gndmgr(rank:s)",
            "Szkolenia" => "/insider/grounds?type=course:s|gndmgr(course:s)",
            "Zgrupowania" => "/insider/grounds?type=event:s|gndmgr(event:s)",
            // todo moje wyniki jedne i drugie
        ),
        "Narciarstwo wysokogórskie" => array(
            "Nadchodzące zawody" => "/insider/grounds?type=comp:nw:pza&current=1",
            "Wszystkie zawody" => "/insider/grounds?type=comp:nw:pza",
            "Zawody międzynarodowe" => "/insider/grounds?type=comp:nw:other",
            "Moje wyniki" => "/insider/achievements?user=self&type=comp:nw:pza",
            "Moje wyniki - międzynarodowe" => "/insider/achievements?user=self&type=comp:nw:other",
            "Wszystkie wyniki" => "/insider/achievements?type=comp:nw",
            "Kadra narodowa" => "/insider/entitlements?family=ka:kn:nw#due=eoy|entmgr(ka:kn:nw)",
            "Instruktorzy" => "/insider/entitlements?family=i:nw|entmgr(i:nw)",
            "Sędziowie" => "/insider/entitlements?family=s:nw|entmgr(s:nw)",
            "Klasy sportowe" => "/insider/entitlements?family=c:nw|entmgr(c:s)",
            "Badania lekarskie" => "/insider/entitlements?family=med:j|entmgr(med:nw)",
        ),
        "System" => array(
            "Rodzaje uprawnień" => "/insider/rights|search(rights)",
            "Wszystkie uprawnienia" => "/insider/entitlements|search(entitlements)",
            "Wszystkie członkostwa" => "/insider/memberships|search(memberships)",
            "Dziennik zmian" => "/insider/register|search(register)",
        ),

    );

    /**
     * Zmienne przechowujące URL do bieżącej klasy (/insider/<nazwa>) oraz
     * parametry GET podane w żądaniu.
     */
    public $source = "", $params = "";

    public $username;

    public $title = "", $subtitle = "";

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

        if(access::has("mailing"))
            $this->_menu["Wyślij email"] = "/insider/mailing";


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
