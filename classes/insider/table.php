<?

/**
 * Class insider_table
 *
 * Klasa podstawowa dla skryptów zajmujących się ogólnymi manipulacjami na
 * tabelach SQL (lista rekordów, wyszukiwanie, podgląd, edycja, dodawanie,
 * usuwanie; użytkowników, uprawnień, kategorii, artykułów itd.)
 *
 * Kod obsługujący poszczególne tabele SQL dziedziczy z tej klasy.
 *
 */

abstract class insider_table extends insider_action
{
    /**
     *
     * Wykaz kolumn SQL dostępnych w tabeli, wraz z nazwami
     * i typami zapisanych w nich obiektów.
     *
     * Lista pól wykorzystywana jest przede wszystkim do
     * operacji dodawania, edycji i podglądu rekordu.
     *
     * W braku konkretnej definicji $columns oraz $filters, te ostatnie
     * są kopiowane z $fields.
     *
     * Kluczami w tablicy są nazwy kolumn w bazie danych; wartościami
     * są tablice z informacjami o polach. Konstruktor klasy potrafi budować
     * te tablice z paru uproszczonych form (np. jeśli wartością dla
     * klucza jest zwykły ciąg znaków, przyjmuje, że jest to nazwa kolumny,
     * zaś wszelkim opcje jej dotyczącym należy przypisać domyślne wartości).
     *
     * Najważniejsze klucze, przenoszące informacje o polach:
     *  name - Nazwa kolumny (klucz "0" zostanie przemianowany przez konstruktor
     *         na "name")
     *  type - Typ kolumny (patrz niżej)
     *  options - Tablica z opcjami dla typów "select", "list", "flags"
     *  regexp - Wyrażenie regularne PCRE definiujące poprawne wartości
     *           pola /^<regex>$/
     *  consistency - Jeśli true, w dialogu dodawania/edycji rekordu będą
     *                podpowiadane (autouzupełnianie) wartości już
     *                wprowadzone do bazy.
     *  comment - Tekst (HTML) do wypisania jako podpowiedź pod kontrolką
     *            formularza dla tego pola, w dialogu edycji/dodawania wiersza.
     *  empty - Jeśli true, poprawną wartością jest "", nawet jeśli taka
     *          wartość nie jest zgodna z regexp.
     *  suppress - Jeśli pole jest puste lub zawiera ciąg 0000-00-00,
     *             jest zupełnie pomijane w dialogu podglądu rekordu.
     *  search - Fragmenty zapytania SQL służące do filtrowania po tym
     *           polu będą używały wartości klucza 'search' jako nazwy
     *           kolumny (zamiast domyślnego "t.<kolumna>"). Klucz
     *           ściśle powiązany z implementacją retr() i/lub retr_query().
     *  order - Jw. ale dotyczy fragmentów ustalających porządek sortowania.
     *  ref - Jeśli występuje ten klucz, pole zawiera odniesienie (ID rekordu)
     *        do tabeli wskazanej jako jego wartość. Musi wówczas występować
     *        również klucz "by".
     *  by - W połączeniu z "ref" - określa, po jakim polu mają być
     *       wyszukiwane w interfejsie użytkownika obiekty w tabeli
     *       określonej przez klucz 'ref'
     *  noedit - Jeśli true, pola nie można edytować
     *  noview - Pole nie pojawia się w podglądzie
     *  nolist - Pole nie zostanie skopiowane do $this->columns
     *  nohist - Zawartość pola nie jest pokazywana w historii obiektu
     *           (przydatne dla dużych pól np. treść artykułu)
     *  no - skrótowy sposób zapisu kluczy no<...>, np. "no" => "edit,view";
     *       rozwijany przez konstruktor.
     *
     *
     * Typy kolumn:
     *  bez typu - pole tekstowe lub odwołanie do wiersza w innej tabeli
     *  area - pole tekstowe obsługiwane w formularzu kontrolką <textarea>
     *  html - pole tekstowe obslugiwane w formularzu za pomocą edytora
     *         HTML (tinyMCE)
     *  list - pole tekstowe z zamkniętą listą poprawnych wartości;
     *         poprawne wartości muszą znajdować się pod kluczem "options"
     *         (jako tablica, której są wartościami)
     *  select - wybór jednej opcji z zamkniętej listy; klucz options musi
     *           zawierać listę opcji w postaci
     *           <wartość w bazie danych> => <opis>
     *  flags - wybór zera, jednej lub więcej opcji z zamkniętej listy
     *          klucz options musi zawierać listę opcji w postaci
     *          <kod opcji> => <wartość>, gdzie kod opcji jest jedną literą
     *          ASCII.
     *  date - Data YYYY-MM-DD (dozwolone także 0000-00-00 i 9999-12-31)
     *
     */
    public $fields = array();

    /**
     * Definicje kolumn, które są wyświetlane na tabelarycznej
     * liście rekordów (Następne / Poprzednie). Składnia jak w
     * przypadku $fields. Możliwe jest skrótowe zdefiniowanie jako
     * listy kluczy z $fields (rozwijane przez konstruktor klasy do
     * pełnej składni).
     *
     * Jeśli nie podano żadnych definicji, definicje są kopiowane
     * z $this->fields (za wyjątkiem pól z ustawionym kluczem nolist)
     *
     */
    public $columns = array();

    /**
     * Definicje pól, po których można filtrować listę tabelaryczną
     * (wyszukiwać rekordy)
     *
     * Składnia jak w przypadku $columns.
     */
    public $filters = false;

    /**
     *
     * "Akcje" możliwe do wywołania z menu rekordu w liście tabelarycznej
     * oraz za pośrednictwem przycisków w dialogu podglądu rekordu. Wpisy w postaci
     * url => tablica z informacjami. Konstruktor tej klasy
     * zamienia wartości będące zwykłym tekstem na tablice array(<tentekst>).
     *
     */
    public $actions = array(
        "<classpath>/view" => "Podgląd",
        "<classpath>/history" => "Historia",
        "<classpath>/edit" => "Edycja",
        "<classpath>/delete" => array("Usuń", "multiple" => true, "ask" => "Potwierdź: usunąć?", "target" => "_top"),
    );

    /**
     *
     * Lista "przycisków" dostępnych dla tej tabeli.
     *
     * Przyciski są dostępne nad tabelką w liście tabelarycznej.
     *
     */
    public $buttons = array(
        "<classpath>/search" => array("Szukaj", "icon" => "search"),
        "<classpath>/add" => array("Dodaj", "icon" => "plus"),
        "<classpath>/export" => array("Eksport", "icon" => "arrow-1-e", "target" => "_blank")
    );

    /**
     * Łączna ilosć rekordów w tabeli
     */
    public $count = 0;

    /**
     * Nazwa tabeli SQL. Jeśli nie została określona,
     * jest automatycznie uzupełniana przez konstruktor na podstawie
     * nazwy klasy pochodnej.
     */
    protected $table = "";

    /**
     * Kolejność sortowania w formacie "kolumna ASC, kolumna DESC" itd.
     *
     * Identyfikatory kolumn zgodne z kluczami w $columns
     */
    protected $order = "";


    /**
     * Odpowiada na pytanie, czy na tej tablicy wolno wykonać $perm
     * $perm może być nazwą metody, nazwą operacji z $this->actions
     * lub nazwą operacji z $this->buttons
     *
     * Funkcja ta może zostać nadpisana przez szczegółową klasę
     * obsługującą konkretną tabelę np. w celu zagwarantowania
     * pewnych praw wszystkim użytkownikom lub zablokowania możliwości
     * wykonywania pewnych operacji na tabeli w ogóle, każdemu.
     *
     */
    protected function access($perm)
    {
        return access::has($perm . "(" . $this->table . ")");
    }

    /**
     * Wymusza posiadanie przez użytkownika prawa do wykonywania
     * operacji $perm na tabeli (patrz $this->access()).
     *
     * Jeśli użytkownik nie posiada żądanego prawa, nastąpi zatrzymanie
     * skryptu.
     */
    protected function enforce($perm)
    {
        if(!$this->access($perm))
            access::ensure($perm . "(" . $this->table . ")");
    }

    /**
     *
     * Funkcja pomocnicza konstruktora.
     *
     * Rozwija skrótowe definicje $actions, $buttons, $columns,
     * $filters, $fields w jednorodne zestawy klucz => tablica z opcjami.
     *
     */
    protected function process_defs($a, $table = false)
    {
        $b = array();
        foreach($a as $k => $i)
        {
            /* Rozwiń definicje w rodzaju a => b */

            if(!is_array($i)) $i = array($i);
            $k = str_replace("<classpath>", static::classpath(), $k);

            /* Rozwiń klucze w rodzaju no => edit,view,list */
            if(isset($i["no"]))
                foreach(explode(",", $i["no"]) as $no)
                    $i["no" . trim($no)] = true;

            if($table)
            {
                $action = array_pop(explode("/", $k));
                if(!$this->access($action))
                    continue;
            }

            $b[$k] = array_merge($i, array("name" => $i[0]));
        }
        return $b;
    }

    /**
     *
     * par_explode - tak jak explode(), tylko weź pod uwagę nawiasy.
     *
     * Zupełnie użytkowa funkcja
     *
     * Zwraca tablicę powstałą z rozdzielenia ciągu $str
     * na kawałki delimiterem $delim. Jeśli delimiter
     * występuje pod nawiasami okrągłymi () lub klamrowymi {},
     * dzielenie ciągu w tym miejscu nie jest wykonywane.
     *
     * Przykładowo: par_explode(",", "aa,bb,(cc,dd),ee") =
     *          array("aa", "bb", "(cc,dd)", "ee")
     */
    protected static function par_explode($delim, $str)
    {
        $out = array(); $balance = 0; $pos = 0;

        foreach(explode($delim, $str) as $nucl)
        {
            $cc = count_chars($nucl);
            $balance += $cc[ord("(")] - $cc[ord(")")];
            $balance += $cc[ord("{")] - $cc[ord("}")];

            $out[$pos][] = $nucl;
            if($balance == 0) $pos++;
        }
        foreach($out as $k => $v)
            $out[$k] = implode($delim, $v);
        return($out);
    }

    /**
     *
     * Funkcja pomocnicza konstruktora.
     *
     * Rozwija skrótowe definicje $this->filters oraz $this->columns
     * w postaci array(<klucz z fields>, <klucz z fields>, ...)
     *
     */
    protected function process_refs($arr)
    {
        foreach($arr as $n => $id)
            if(is_numeric($n))
            {
                unset($arr[$n]);
                $arr[$id] = $this->fields[$id];
            }

        return $arr;
    }

    /**
     *
     * Zadaniem konstruktora jest przede wszystkim rozwinięcie
     * skrótowych definicji pól/kolumn/filtrów/akcji w pełne
     * tablice opisujące strukturę i pożądane zachowanie operacji
     * na tabeli.
     *
     * Konstruktory konkretnych klas dziedziczących realizują także
     * czasami kontrolę dostępu oraz uzależniają wyświetlane/obsługiwane
     * kolumny od rodzaju wywoływanej akcji (np. przeglądanie tylko
     * przejść jaskiniowych itd.)
     *
     */
    function __construct()
    {
        parent::__construct();

        /* Jeśli tabela nie jest zdefiniowana, weź ostatni element nazwy klasy */
        if(!$this->table)
            $this->table = end(explode("_", get_called_class()));

        /* Przerób definicje kolumn na jednolitą postać */
        $this->fields = $this->process_defs($this->fields);

        if(!$this->columns)
        {
            foreach($this->fields as $k => $d)
                if(!isset($d["nolist"]))
                    $this->columns[$k] = $d;
        }
        else
        {
            /* Kolumny zdefiniowane jako identyfikatory z fields? */
            $this->columns = $this->process_refs($this->columns);
            $this->columns = $this->process_defs($this->columns);
        }

        /* i tak samo z definicjami operacji na rekordach */
        $this->actions = $this->process_defs($this->actions, $this->table);

        /* oraz definicjami przycisków */
        $this->buttons = $this->process_defs($this->buttons, $this->table);

        /* Jeśli nie zdefiniowano filtrów - można filtrować po wszystkich kolumnach! */
        if($this->filters === false)
            $this->filters = $this->columns;
        else
        {
            $this->filters = $this->process_refs($this->filters);
            $this->filters = $this->process_defs($this->filters);
        }
    }

    /**
     *
     * Funkcja pomocnicza dla metody table()
     *
     * Określa, jak zmieniłby się porządek sortowania $order, gdyby kliknąć
     * w kolumnę $col (z $this->columns).
     *
     * Dodatkowo, jeśli $final = true, funkcja generuje docelowy
     * porządek sortowania do użycia w zapytaniu SQL
     *
     */
    protected function alter_order($order, $col = false, $final = false)
    {
        $ord = array();
        if($col)
            $ord[$col] = $col;

        foreach(explode(",", $order) as $i)
        {
            list($col, $dir) = explode(" ", trim($i), 2);

            $dir = strtoupper(trim($dir));
            $col = trim($col);

            if(strlen($dir) == 0) $dir = "ASC";

            if(!in_array($dir, array("ASC", "DESC"))) continue;
            if(!isset($this->columns[$col])) continue;

            if(isset($ord[$col]))
                $dir = ($dir == "ASC") ? "DESC" : "ASC";

            if($final)
            {
                if($this->columns[$col]["order"])
                    $alias = strtr($this->columns[$col]["order"], array("<dir>" => $dir));
                elseif($this->columns[$col]["field"])
                    $alias = $this->columns[$col]["field"];
                else
                    $alias = "t." . "`" . $col . "`";
            }
            else
                $alias = $col;

            $ord[$col] = $alias . " " . $dir;
        }

        return implode(", ", $ord);
    }

    /**
     *
     * Funkcja pomocnicza dla metody table()
     *
     * Importuje porządek sortowania określony w $_REQUEST["order"],
     * sprawdzając przy tym jego poprawność.
     *
     */
    protected function parse_order()
    {
        if(isset($_REQUEST["order"]))
            $this->order = $this->alter_order($_REQUEST["order"], false, false);
    }

    /**
     *
     * Domyślna metoda tej klasy.
     *
     * Wyświetla listę wierszy tabeli do przeglądania (Następne/Poprzednie).
     *
     * Właściwa lista (zawartość tagu <table>) jest wczytywana poprzez
     * AJAX z metody $this->table()
     *
     */
    function route()
    {
        $this->S->display("insider/table.html");
    }

    /**
     *
     * Wyświetla tabelaryczną listę wierszy tej tabeli (Następne/Poprzednie)
     *
     */
    function table()
    {
        $this->parse_order();

        foreach(array_keys($this->columns) as $col)
            $this->columns[$col]["order_onclick"] = $this->alter_order($this->order, $col);

        $r = $_REQUEST["offset"];
        $l = $_REQUEST["limit"];

        if(!is_numeric($r)) $r = 0;
        if(!is_numeric($l)) $l = 50;

        $this->S->assign(array(
            "results" => $this->retr($r, $l),
            "offset" => $r,
        ));

        $this->S->display("insider/table_table.html");
    }

    /**
     *
     * Funkcja pomocnicza dla metody retr()
     *
     * Na podstawie daty wprowadzonej w pole do filtrowania
     * (np. 2013-05) buduje fragment zapytania SQL, za pomocą
     * którego mozna porównywać z wprowadzoną datą (np. 2013-05-01).
     *
     * Jeśli $enddate = true, data jest zaokrąglana
     * do końca okresu (miesiąca, dnia itd.). W przeciwnym wypadku,
     * do początku okresu.
     *
     */
    protected function build_date_atom($date, $enddate = false)
    {
        if(!strlen($date))
            return vsql::quote($enddate ? "2086-01-01" : "0000-00-00");

        if(preg_match('/^([0-9]{4})$/', $date))
            $date .= ($enddate ? "-12-31" : "-01-01");

        if(preg_match('/^([0-9]{4})-([0-9]{2})$/', $date))
        {
            $date = vsql::quote($date . "-01");
            return $enddate ? ("CONCAT(LAST_DAY(" . $date . "), ' 23:59:59')") : "CONCAT($date, ' 00:00:00')";
        }

        if(preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $date))
            return vsql::quote($enddate ? ($date . " 23:59:59") : ($date . " 00:00:00"));

        return vsql::quote($date);
    }

    /**
     *
     * Funkcja pomocnicza dla metody retr()
     *
     * Buduje fragment zapytania SQL filtrujący po polu $f
     * treścią $s (np. dla $f="name" i $s="Kowalski" może to być
     * "u.name LIKE '%Kowalski%'").
     *
     */
    protected function build_filter_atom($f, $s)
    {
        if(isset($this->filters[$f]["search"]))
            $a = $this->filters[$f]["search"];
        elseif(isset($this->filters[$f]["field"]))
            $a = $this->filters[$f]["field"];
        else
            $a = "t.`" . $f . "`";

        if($this->fields[$f]["type"] == "select")
            if(isset($this->fields[$f]["options"][$s]))
                return "$a = " . vsql::quote($s);

        if($this->fields[$f]["type"] != "date")
        {
            if($s[0] == "=")
                $s = substr($s, 1);
            else
                $s = "*" . $s . "*";
        }

        switch($this->fields[$f]["type"])
        {
            case "date":
                $s = preg_replace('/[~:]/', '/', $s);
                $lt = explode("/", $s, 2);

                if(count($lt) == 2)
                {
                    list($from, $to) = $lt;
                    $from = trim($from);
                    $to = trim($to);
                }
                else
                {
                    $from = trim(current($lt));
                    $to = $from;
                }

                return ("($a BETWEEN " .
                    $this->build_date_atom($from) .
                    " AND " .
                    $this->build_date_atom($to, true) . ")");
                break;

            case "select":
                $optlist = array(); $s = strtoupper($s);

                foreach($this->fields[$f]["options"] as $opt => $val)
                    if(fnmatch($s, strtoupper($val)))
                        $optlist[] = vsql::quote($opt);

                if(!count($optlist)) return "0 = 1";
                return "$a IN (" . implode(", ", $optlist) . ")";

                break;

            case "flags":
                $optlist = array(); $s = strtoupper($s);
                foreach($this->fields[$f]["options"] as $opt => $val)
                    if(fnmatch($s, strtoupper($val)))
                        $optlist[] = $a . " LIKE " . vsql::quote("%" . $opt . "%");

                if(!count($optlist)) return "0 = 1";
                return implode(" OR ", $optlist);
                break;

            default:
                $s = strtr($s, array("*" => "%", "?" => "_"));
                return "$a LIKE " . vsql::quote($s);
        }
    }

    /**
     *
     * Funkcja pomocnicza dla metody retr()
     *
     * Buduje warunki SQL dla pola $f, filtrujące po nim
     * zgodnie z zapytaniem $f.
     *
     * Na przykład dla $f="surname" i $s="~Kowalski&~Nowak":
     * "((u.name NOT LIKE '%Kowalski%') AND (u.name NOT LIKE '%Nowak%'))"
     *
     */
    protected function build_filter($f, $s)
    {
        foreach(array("|" => "OR", "&" => "AND") as $op => $sql)
        {
            if(count($a = static::par_explode($op, $s)) > 1)
            {
                foreach($a as $n => $atom)
                    $a[$n] = static::build_filter($f, $atom);

                return "(" . implode(" " . $sql . " ", $a) . ")";
            }
        }
        if($s[0] == "~")
            return "(NOT " . static::build_filter($f, substr($s, 1)) . ")";

        return $this->build_filter_atom($f, $s);
    }

    /**
     *
     * Funkcja pomocnicza dla metody retr()
     *
     * Buduje warunki SQL filtrujące po żądanych przez użytkownika
     * kryteriach.
     *
     * Kryteria są zawarte w tablicy $farr. Kluczem jest nazwa
     * kolumny z $filters, wartością - żądane kryterium,
     * np. "~Kowalski&~Nowak".
     *
     * Jeśli nie podano tablicy z filtrami, treść kryteriów jest
     * pobierana z $_REQUEST.
     *
     */
    protected function get_filters($farr = false)
    {
        if(!is_array($farr))
            $farr = $_REQUEST;

        $filters = "";
        /* Przetwórz ewentualne filtry */
        foreach($this->filters as $f => $i)
            if(isset($farr[$f]))
                if(strlen($s = $farr[$f]))
                    $filters .= " AND " . static::build_filter($f, $s);
        return $filters;
    }

    /**
     *
     * Funkcja pomocnicza dla metody retr()
     *
     * Zwraca dodatkowe warunki SQL, jakie mają zostać nałożone
     * na zapytanie SQL przy wyświetlaniu tabelarycznej listy rekordów.
     *
     * Klasy pochodne mogą umieszczać w tej metodzie ograniczenia
     * dostępu (np. jeśli użytkownik ma prawo do oglądania tylko
     * swoich przejść, klasa odpowiadająca za wyświetlanie wykazu
     * przejść za pomocą tej funkcji może przekazać warunek u.user = ...)
     *
     * Zwracany ciąg musi zaczynać się od " AND".
     *
     */
    protected function retr_extra_filters()
    {
        return "";
    }

    /**
     *
     * Funkcja pomocnicza dla metody retr()
     *
     * Zwraca zapytanie SQL, jakie powinna wykorzystać metoda retr()
     * w celu pobrania listy rekordów do wyświetlenia w tabeli HTML.
     *
     * Zapytanie musi uwzględnić warunki SQL podane w parametrze
     * $filters (zaczynającym się od AND lub pustym).
     *
     * Należy liczyć się z tym, że metoda retr() doda
     * na koniec zapytania klauzule ORDER BY oraz LIMIT
     * (co oznacza, że nie należy ich wstawiać do zwracanego
     * przez tą metodę zapytania!).
     *
     */
    protected function retr_query($filters)
    {
        return "SELECT SQL_CALC_FOUND_ROWS " .
            " id, " . implode(",", array_keys($this->columns)) .
            " FROM " . $this->table . " AS t " .
            " WHERE deleted = 0 " . $filters . " " .
            $this->retr_extra_filters();
    }

    /**
     *
     * Metoda wykorzystywana przez table() oraz export() do
     * pobierania listy rekordów do wyświetlenia/wyeksportowania
     * z bazy.
     *
     * Pobiera z bazy SQL $limit rekordów, począwszy od rekordu
     * $offset.  Rekordy są sortowane wg porządku sortowania
     * zapisanego w $this->order.
     *
     * Podczas pobierania, uwzględniane są wprowadzone przez użytkownika
     * kryteria wyszukiwania, zgodnie z tablicą $filters (w której
     * kluczami są klucze z $this->filters np. surname, zaś wartościami
     * kryteria, np. "~Kowalski&~Nowak").
     *
     * Jeśli nie podano $filters, kryteria zostaną pobrane z $_REQUEST.
     *
     *
     */
    protected function retr($offset = 0, $limit = 50, $filters = false)
    {
        $this->enforce("search");

        $filters = $this->get_filters($filters);

        $query = $this->retr_query($filters);

        if($this->order)
            $query .= " ORDER BY " . $this->alter_order($this->order, false, true);

        if($limit)
            $query .= " LIMIT {$offset}, {$limit}";

        $res = vsql::retr($query, "");
        $this->count = vsql::get("SELECT FOUND_ROWS() AS r", "r");
        return $res;
    }

    /**
     *
     * Eksportuje wyświetlane kolumny do pliku CSV.
     *
     */
    function export()
    {
        $this->enforce("export");

        $table = $this->table;

        header("Content-type: text/plain; charset=utf-8");
        header("Content-disposition: attachment; filename=\"export-{$table}.csv\"");

        $this->parse_order();
        foreach($this->retr() as $row)
        {
            foreach($row as $n => $ent)
                $row[$n] = strtr($ent, array("\t" => " ", "\r" => " ", "\n" => " "));
            echo implode("\t", $row) . "\r\n";
        }
    }

    /**
     *
     * Wyświetla okno dialogowe z kryteriami wyszukiwania.
     *
     */
    function search()
    {
        $this->S->display("insider/table_search.html");
    }

    /**
     *
     * Funkcja pomocnicza dla metody fetch()
     *
     * Tłumaczy zawartość pola SQL $f z zapisu
     * przechowywanego w bazie danych na postać
     * zrozumiałą dla człowieka np. 3389 -> Mateusz Golicz
     * albo U -> Opłacone wpisowe.
     *
     * data_f - treść wydobyta z bazy SQL
     * resolve - jeśli false, rozwiązywane są tylko odniesienia
     *       do innych tabel SQL; wartości dla kolumn typu
     *       select/flags pozostają w oryginalnej postaci.
     *
     */
    protected function fetch_field($f, $data_f, $resolve = false, $nl = false)
    {
        $i = $this->fields[$f];

        /* Przetłumacz wybraną opcję select */
        if($i["type"] == "select" && $resolve)
        {
            if(isset($i["options"][$id = $data_f]))
                return $i["options"][$id];
        }

        /* Przetłumacz wybrane flagi */
        if($i["type"] == "flags" && $resolve)
        {
            $str = $data_f; $a = array();
            for($j = 0; $j<strlen($str); $j++)
                $a[] = $i["options"][$str{$j}];

            return implode($nl ? "\n" : " | ", $a);
        }

        /* Przetłumacz wybrane obiekty */
        if(isset($i["ref"]))
        {
            if(!strlen($id = $data_f)) return $data_f;

            $tab = $i["ref"];
            $col = $i["by"];

            $idlist = explode(",", $id);

            if(!access::has("ref($tab)"))
                return $data_f;

            foreach($idlist as $n => $ent)
                if($ent)
                    $idlist[$n] = $ent . ":" .
                        vsql::get("SELECT `$col` AS c FROM `$tab` WHERE deleted = 0 AND id = " . vsql::quote($ent), "c");

            return implode($nl ? "\n" : ",", $idlist);
        }

        return $data_f;
    }

    /**
     *
     * Funkcja pomocnicza dla metody history()
     *
     * Działa analogicznie do fetch_field(), ale skraca treść
     * pól, których zawartość, ze względu na swoją typową objętość, nie jest
     * wyświetlana na liście zmian rekordu (np. tekst HTML artykułu).
     *
     */
    protected function fetch_history($f, $data_f, $resolve = false, $nl = false)
    {
        if($this->fields[$f]["nohist"])
        {
            if(!$data_f) return "";
            $sz = "";
            foreach(array_reverse(array("B", "kB", "MB"), true) as $p => $ext)
                if(strlen($data_f) >= pow(2, $p * 3))
                {
                    $sz = sprintf($p == 0 ? "%d" : "%.2f %s", strlen($data_f) / pow(2, $p * 3), $ext);
                    break;
                }

            return substr(md5($data_f), 0, 16) . " (" . $sz . ")";
        }
        return $this->fetch_field($f, $data_f, $resolve, $nl);
    }

    /**
     *
     * Funkcja pomocnicza dla metod view() i edit()
     *
     * Pobiera rekord z bazy danych SQL oraz rozwiązuje
     * odniesienia w polach nieczytelnych dla człowieka
     * (np. numery rekordów w innych tabelach, numery opcji typu <select>)
     *
     */
    protected function fetch($id, $resolve = false, $nl = false, $extra_sql = "")
    {
        // todo no cóż, jest pewien problem z deleted...
        if(!($data = vsql::get("SELECT * FROM `" . $this->table . "` WHERE id = " . vsql::quote($id) . $extra_sql)))
            return array();

        /* Rozwiąż referencje i selects */
        foreach(array_keys($this->fields) as $f)
            $data[$f] = $this->fetch_field($f, $data[$f], $resolve, $nl);

        return $data;
    }

    /**
     *
     * Otwiera dialog edycji rekordu.
     *
     */
    function edit()
    {
        $this->enforce("edit");
        $data = $this->fetch($_REQUEST["id"], false, false, " AND deleted = 0");
        if(!$data) die("Obiekt nie istnieje.");

        $this->S->assign("data", $data);
        $this->w_action("edit");
    }

    /**
     * Funkcja pomocnicza dla metody add()
     *
     * Zwraca domyślne wartości dla nowego wiersza w tabeli.
     *
     */
    protected function defaults()
    {
        return array();
    }

    /**
     *
     * Wyświetl dialog dodawania nowego wiersza.
     *
     */
    function add()
    {
        $this->enforce("add");

        foreach($this->fields as $f => $fi)
            if($fi["noadd"])
                $this->fields[$f]["noedit"] = true;

        $this->S->assign("data", $this->defaults());
        $this->w_action("edit");
    }

    /**
     * Funkcja pomocnicza dla validate()
     *
     * case-insensitive in_array()
     */

    static function in_arrayi($needle, $haystack)
    {
        foreach($haystack as $el)
            if(!strcasecmp($el, $needle))
                return $el;

        return false;
    }

    /**
     *
     * Funkcja pomocnicza dla metody save()
     *
     * Sprawdza, czy wartości wprowadzone przez użytkownika
     * są prawidłowe dla rekordu w tej tabeli SQL.
     *
     * Wartości zawarte są w tabeli $data (kluczami są nazwy
     * kolumn SQL, będące także kluczami w $this->fields; wartościami
     * są wartości wprowadzone przez użytkownika).
     *
     * Uwaga! Funkcja zwraca false jeśli nie ma błędów!
     * W przypadku wystapienia błędów, zwracana jest tablica z
     * komunikatami o błędach. Kluczami są nazwy kolumn SQL
     * (tak, jak w przekazanym $data), a wartościami - opisy błędów.
     *
     * Jeśli $id = 0, od funkcji oczekiwane jest sprawdzenie,
     * czy wartości są poprawne dla nowego obiektu. Jeśli $id != 0,
     * funkcja ma zwrócić ewentualne błędy jeśli obiekt o takim
     * $id zostałby zmodyfikowany wskazanymi wartościami. W takim
     * wypadku, $data może zawierać tylko kolumny do modyfikacji.
     *
     * Funkcja może dokonywać poprawek w tablicy $data. Wywołujący
     * jest zobowiązany uwzględnić te poprawki przed wywołaniem
     * $this->update()
     *
     */
    protected function validate($id, &$data)
    {
        $err = array();
        foreach($data as $f => $val)
        {
            if($this->fields[$f]["empty"])
                if(strlen($val) == 0)
                    continue;

            /* Wyrażenie regularne weryfikujące poprawność? */
            if(isset($this->fields[$f]["regexp"]))
                if(!preg_match('/^' . $this->fields[$f]["regexp"] . '$/', $val))
                    $err[$f] = isset($this->fields[$f]["regmsg"]) ? $this->fields[$f]["regmsg"] : "Nieprawidłowa wartość";

            if($this->fields[$f]["type"] == "list")
            {
                $mchar = "";
                if(isset($this->fields[$f]["multiple"]))
                {
                    if(!is_string($mchar = $this->fields[$f]["multiple"]))
                        $mchar = "|";
                    $a = explode($mchar, $val);
                }
                else
                    $a = array($val);

                $a = array_map("trim", $a);
                foreach($a as $q => $ent)
                    if(!($a[$q] = $this->in_arrayi($ent, $this->fields[$f]["options"])))
                        $err[$f] = "Nieprawidłowa wartość: $ent";

                $data[$f] = implode($mchar, $a);
            }

            if($this->fields[$f]["type"] == "select")
                if(!isset($this->fields[$f]["options"][$val]))
                    $err[$f] = "Nieprawidłowa wartość";

            if($this->fields[$f]["type"] == "date")
                if($msg = $this->validate_date($val))
                    $err[$f] = $msg;

            /* Referencja do innej tabeli? */
            if(isset($this->fields[$f]["ref"]))
            {
                $tab = $this->fields[$f]["ref"];
                $col = $this->fields[$f]["by"];

                if($this->fields[$f]["multiple"])
                    $vlist = explode(",", $val);
                else
                    $vlist = strlen($val) ? array($val) : array();

                foreach($vlist as $n => $ent)
                {
                    list($id, $ref) = array_map("trim", explode(":", $ent, 2));

                    if(is_numeric($id) && $id == 0 && isset($this->fields[$f]["empty"]))
                        $data[$f] = 0;
                    elseif(is_numeric($id))
                    {
                        if(!vsql::get("SELECT id FROM `$tab` WHERE deleted = 0 AND id = $id " .
                            (strlen($ref) ? (" AND `$col` = " . vsql::quote($ref)) : "")))
                            $err[$f] = "{$ent}: Nie wiadomo, o który obiekt chodzi!";
                        else
                            $vlist[$n] = $id;
                    }
                    else
                    {
                        $r = vsql::retr("SELECT id FROM `$tab` WHERE deleted = 0 AND " .
                                        " `$col` = " . vsql::quote($ent) . " LIMIT 2");
                        if(count($r) == 0)
                            $err[$f] = "{$ent}: Brak obiektu w bazie";
                        else if(count($r) > 1)
                            $err[$f] = "{$ent}: Niejednoznaczny wybór! Użyj listy wyboru.";
                        else
                            $vlist[$n] = key($r);
                    }
                }
                if((!count($vlist)) && !$this->fields[$f]["empty"])
                    $err[$f] = "Należy wybrać obiekt";

                $data[$f] = implode(",", $vlist);
             }
        }


        return $err;
    }

    /**
     *
     * Funkcja pomocnicza dla metody save().
     *
     * Aktualizuje rekord w tabeli.
     *
     * Rekord musi zostać uprzednio sprawdzony metodą validate().
     *
     * Składnia wywołania jak w przypadku $this->validate().
     *
     */
    protected function update($id, $data)
    {
        return vsql::update($this->table, $data, $id);
    }

    /**
     *
     * Metoda obsługująca POST formularza dodawania/edycji wiersza
     * (wygenerowanego przez $this->edit() lub $this->add())
     *
     */
    function save()
    {
        if(!isset($_REQUEST["id"]))
        {
            $this->enforce("add");
            $idlist = 0;
        }
        else
        {
            $this->enforce("edit");
            $idlist = $_REQUEST["id"];
        }

        foreach(explode(" ", $idlist) as $id)
        {
            $data =  array_intersect_key($_REQUEST, $this->fields);
            $err = $this->validate($id, $data);
            if(!$err)
            {
                $err = array();
                $this->update($id, $data);
            }
            else
                break;
        }
        echo json_encode($err);
    }

    /**
     *
     * Funkcja pomocnicza dla $this->complete_ref() oraz $this->complete_consistency()
     *
     * Zwraca warunki SQL (muszą zaczynać się od AND lub być puste),
     * które muszą zostać wzięte pod uwagę przy generowaniu sugestii
     * do autouzupełniania pola $f
     *
     * Uwaga: warunki te mogą dotyczyć innej tabeli SQL (tej, z której
     * wybierany jest rekord do pola $f) - zależy, czy funkcja jest
     * wywoływana dla pola typu "ref" czy "text" ("consistency").
     *
     */
    protected function complete_constraints($f)
    {
        return "";
    }

    /**
     *
     * Funkcja pomocnicza dla metody complete()
     *
     * Generuje sugestie do autouzupełniania pola $f,
     * będącego odniesieniem do innej tabeli SQL (identyfikatorem
     * wiersza w innej tabeli).
     *
     * $term - wprowadzony przez użytkownika ciąg, na podstawie
     * którego generowane są sugestie.
     *
     */
    protected function complete_ref($f, $term)
    {
        $tab = $this->fields[$f]["ref"];
        $col = $this->fields[$f]["by"];
        $order = isset($this->fields[$f]["ref_order"]) ? $this->fields[$f]["ref_order"] : "`$col`";


        if($this->fields[$f]["multiple"])
        {
            $ta = explode(",", $term);
            $term = array_pop($ta);
            $term_prefix = count($ta) ? (implode(',', $ta) . ",") : "";
        }
        else
            $term_prefix = "";

        $term = preg_replace('/^[0-9]+:/', "", $term);
        if(!$term) return array();

        $r = array();
        foreach(array($term, $term . "%", "%" . $term . "%") as $q)
        {
            if(access::has("search($tab)") || access::has("ref($tab)"))
            {
                $r = array_merge($r,
                    vsql::retr($q = "SELECT CONCAT(id, ':', `{$col}`) AS sugg FROM
                    `$tab` WHERE deleted = 0 AND `$col` LIKE " . vsql::quote($q) .
                        $this->complete_constraints($f) .
                        " ORDER BY $order",
                        "sugg", "sugg"));

            }
            if(count($r) >= 10) break;
        }

        $a = array();
        foreach(array_slice($r, 0, 10) as $ent)
            $a[] = array("label" => $ent, "value" => ($term_prefix . $ent));

        echo json_encode($a);
    }

    /**
     *
     * Funkcja pomocnicza dla $this->complete()
     *
     * Generuje sugestie autouzupełniania dla pola $f,
     * będącego polem tekstowym z zamkniętym katalogiem
     * dozwolonych wartości ("list").
     *
     * $term - wpisany przez użytkownika ciąg, na podstawie
     * którego wygenerować sugestie.
     *
     */
    protected function complete_list($f, $term)
    {
        $term_prefix = ""; $mstr = "";
        if(isset($this->fields[$f]["multiple"]))
            if(!is_string($mstr = $this->fields[$f]["multiple"]))
                $mstr = "|";

        if($mstr)
        {
            $a = explode($mstr, $term);
            $term = trim(array_pop($a));
            $term_prefix = implode($mstr, $a);
            if($term_prefix) $term_prefix .= $mstr;
        }

        $a = array();
        foreach(array($term, $term . "*", "*" . $term . "*") as $q)
        {
            $q = strtoupper($q);
            foreach($this->fields[$f]["options"] as $ent)
                if(fnmatch($q, strtoupper($ent)))
                    $a[$ent] = array("label" => $ent, "value" => ($term_prefix . $ent));
            if(count($a) > 20) break;
        }
        echo json_encode(array_values(array_slice($a, 0, 20)));
    }

    /**
     *
     * Funkcja pomocnicza dla $this->complete()
     *
     * Generuje sugestie autouzupełniania dla pola $f,
     * będącego polem tekstowym, co do którego zależy nam
     * na spójności wartości w bazie danych.
     *
     * Sugestie generowane są z obecnie znajdujących się w bazie
     * wartości tego pola, podobnych do wprowadzonego $term.
     *
     */
    function complete_consistency($f, $term)
    {
        $a = array();
        foreach(array($term, $term . "%", "%" . $term . "%") as $q)
            if(count($a = array_merge($a, vsql::retr("SELECT DISTINCT `$f` AS f FROM `" . $this->table . "`" .
                        " WHERE deleted = 0 AND `$f` LIKE " . vsql::quote($q) .
                          $this->complete_constraints($f), "f", "f"))) > 20)
                break;

        echo json_encode(array_keys(array_slice($a, 0, 20)));
    }

    /**
     *
     * Proponuje listę sugestii do wyboru, dla pól w których
     * możliwe jest autouzupełnianie (autocomplete z jquery-ui).
     *
     */
    function complete()
    {
        $f = $_REQUEST["f"];
        $term = $_REQUEST["term"];

        if(!isset($this->fields[$f])) return;

        if(isset($this->fields[$f]["ref"]))
            return $this->complete_ref($f, $term);

        if($this->fields[$f]["type"] == "list")
            return $this->complete_list($f, $term);

        if($this->fields[$f]["consistency"])
            return $this->complete_consistency($f, $term);
    }

    /**
     *
     * Wyświetl wzorzec HTML <tabela>_<akcja>.html lub table_<akcja>.html,
     * jeśli taki nie istnieje.
     *
     */
    protected function w_action($action)
    {
        if(file_exists($f = ("templates/insider/{$this->table}_{$action}.html")))
            $this->S->display($f);
        else
            $this->S->display("insider/table_{$action}.html");
    }

    /**
     *
     * Wygeneruj dialog podglądu obiektu.
     *
     */
    function view()
    {
        $this->enforce("view");

        $this->S->assign("data", $this->fetch($_REQUEST["id"], true, true, " AND deleted = 0"));
        $this->w_action("view");
    }

    /**
     *
     * Usuń jeden lub więcej wiersz z bazy danych.
     *
     */
    function delete()
    {
        $this->enforce("delete");

        foreach(explode(" ", $_REQUEST["id"]) as $id)
            vsql::delete($this->table, $id);
        echo json_encode(array());
    }

    /**
     *
     * Wygeneruj dialog po kliknięciu w wiersz.
     *
     */
    public function click()
    {
        return $this->view();
    }

    /**
     *
     * Wygeneruj dialog z historią zmian wiersza.
     *
     */
    public function history()
    {
        $this->enforce("history");
        $r = vsql::retr("SELECT r.id, r.creat, u.ref AS user, r.source, r.type, r.field,
                        r.contents, r.previous " .
                    " FROM register AS r " .
                    " LEFT JOIN users AS u ON u.id = r.user WHERE " .
                    " r.`table` = " . vsql::quote($this->table) .
                    " AND r.record = " . vsql::quote($_REQUEST["id"]) .
                    " ORDER BY r.creat DESC", "");

        $r = insider_register::resolve($r);

        $this->S->assign("types", array(1 => "Utworzenie", 2 => "Modyfikacja", 3 => "Usunięcie"));

        $this->S->assign("history", $r);
        $this->S->display("insider/table_history.html");
    }

    /**
     *
     * Funkcja pomocnicza dla $this->validate()
     *
     * Sprawdź poprawność określenia daty $d (format YYYY-MM-DD)
     *
     */
    static function validate_date($d)
    {
        if($d == "9999-12-31")
            return false;

        if(!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $d))
            return "Nieprawidłowy format daty";

        if(strtotime($d) === false)
            return "Nieprawidłowy format daty";

        return false;
    }

    /**
     *
     * Funkcja pomocnicza dla systemowego dziennika zmian.
     *
     * Generuje krótki, tekstowy opis rekordu. Na przykład
     * "Jan Kowalski" albo "Jan Kowalski * Puchar Polski 2013" (rekord
     * opisujący uczestnictwo Jana Kowalskiego w Pucharze Polski).
     *
     */
    protected function capt($id)
    {
        if(!isset($this->capt))
            return $this->table . " / " . $id;

        $sr = array();
        foreach($this->fetch($id) as $k => $v)
            $sr["<" . $k . ">"] = $v;

        return(strtr($this->capt, $sr));
    }

    /**
     * Funkcja pomocnicza dla delete()
     *
     * Wyślij do insider_table.js komunikat o błędzie i zakończ działanie skrytpu.
     *
     */
    function json_fail($msg)
    {
        echo json_encode(array("msg" => $msg));
        exit;
    }


}
