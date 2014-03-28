<?


class insider_register extends insider_table
{
    public $fields = array(
        "creat" =>  "Data",
        "user" =>    array("Użytkownik", "ref" => "users", "by" => "ref", "order" => "u.ref", "search" => "u.ref"),
        "source" =>  "Adres IP",
        "type" => array("Operacja", "type" => "select"),
        "table" =>   "Tabela",
        "record" =>  "Wiersz",
    );

    static $types = array(1 => "Utworzenie", 2 => "Modyfikacja", 3 => "Usunięcie");

    protected $order = "creat DESC";

    function access($perm)
    {
        if(in_array($perm, explode(",", "edit,delete,add,history,export")))
            return false;

        return parent::access($perm);
    }

    function __construct()
    {
        $this->actions["<classpath>/record"] = "Podgląd wiersza";
        $this->actions["<classpath>/revert"] = array("Cofnij transakcję", "target" => "_top", "ask" => "Cofnąć transakcję?");
        $this->fields["type"]["options"] = static::$types;
        parent::__construct();
    }

    function validate($id, &$data)
    {
        return(array("creat" => "Nie można zmieniać historii!"));
    }

    protected function retr_query($filters)
    {
        return "SELECT " .
        " t.id, t.creat, u.ref AS user, t.source, t.type, t.`table`, t.record " .
        " FROM register AS t " .
        " LEFT JOIN users AS u ON u.id = t.user " .
        " WHERE t.deleted = 0 " . $filters . " " .
               $this->retr_extra_filters();
    }

    static function resolve($r)
    {
        $to = array();

        foreach($r as $k => $v)
        {
            /* Resolve user */
            if(isset(static::$types[$v["type"]]))
                $r[$k]["type"] .= ": " . static::$types[$v["type"]];

            /* Resolve object references */
            if(class_exists($classname = "insider_" . $v["table"]))
            {
                if(!isset($to[$v["table"]]))
                    $to[$v["table"]] = new $classname;

                /* Resolve record */
                $o = $to[$v["table"]];
                $r[$k]["record"] .= ": " . $o->capt($v["record"]);

                foreach(array("contents", "previous") as $f)
                    if(isset($v[$f]))
                        $r[$k][$f] = $o->fetch_history($v["field"], $v[$f]);

                if(isset($v["field"]))
                    if(isset($o->fields[$v["field"]]))
                        $r[$k]["field"] = $o->fields[$v["field"]]["name"];
            }
        }

        return $r;
    }

    protected function retr($offset = 0, $limit = 50)
    {
        return $this->resolve(parent::retr($offset, $limit));
    }

    protected function fetch($id, $resolve = false, $nl = false, $extra_sql = "")
    {
        $o = parent::fetch($id, $resolve, $nl, $extra_sql);
        $o["user"] = vsql::get("SELECT ref FROM users WHERE id = " . vsql::quote($o["user"]), "ref", "");
        if(class_exists($classname = "insider_" . $o["table"]))
        {
            $to = new $classname;
            $o["record"] .= ": " . $to->capt($o["record"]);
        }
        return $o;
    }

    function record()
    {
        $i = vsql::get("SELECT `table`, record FROM register WHERE id = " . vsql::quote($_REQUEST["id"]));
        header("Location: /insider/" . $i["table"] . "/view?id=" . $i["record"]);
        exit;
    }

    private function get_xid()
    {
        return vsql::get("SELECT transaction FROM register
               WHERE id = ". vsql::quote($_REQUEST["id"]), "transaction");
    }

    function view()
    {
        foreach(array("field", "table", "type", "record") as $k)
            unset($this->fields[$k]);

        $this->S->assign("xid", $xid = $this->get_xid());
        $this->S->assign("transaction", $this->resolve(
            vsql::retr("SELECT id, `record`, `table`, field, contents, previous, type
                FROM register WHERE transaction = " . vsql::quote($xid) .
            " ORDER BY id")));

        parent::view();

    }


    private function revert_prepare($transaction, $maxid = 0)
    {
        /* backaway_prepare - określ, co trzeba wykonać, żeby cofnąć transakcję
                $transaction - identyfikator transakcji do cofnięcia
                $maxid - jeśli != 0, nie będą brane pod uwagę wpisy w register
                    o identyfikatorze większym >= maxid

                Zwraca tablicę z zestawem operacji do wykonania, którą
                można następnie użyć do backaway(), lub ciąg znaków
                opisujący błąd.
        */

        /* Ustal, jaki id ma pierwsza operacja w ramach tej transakcji zapisana w register */
        $minid = vsql::get("SELECT MIN(id) AS id FROM register WHERE deleted = 0 AND transaction = " .
                    vsql::quote($transaction) . ($maxid == 0 ? "" : " AND id < " . $maxid), "id", 0);
        if(!$minid) return("Brak informacji o wskazanej transakcji.");

        /* Pobierz listę operacji do cofnięcia. Jako tabelę 'a' próbujemy dowiązać wszelkie
           PÓŹNIEJSZE operacje na modyfikowanych rekordach. Jeśli w przypadku któregokolwiek
           wiersza z register do cofnięcia dla transakcji $transaction uda się dowiązać
           choć jeden wiersz operacji, która nastąpiła później, wyrzucamy błąd.
          */
        $to_revert = vsql::retr("SELECT r.id, r.table, r.record, r.type, r.previous, r.field, COUNT(a.id) AS changes, a.transaction AS oxid
                FROM register AS r LEFT JOIN register AS a ON " .
                " a.deleted = 0 AND a.table = r.table AND a.record = r.record AND a.id > r.id AND a.transaction != r.transaction " .
                ($maxid == 0 ? "" : " AND a.id <= " . $maxid) .
                " WHERE r.deleted = 0 AND r.transaction = " . vsql::quote($transaction) . " GROUP BY r.id ORDER BY r.id DESC");

        $commands = array(); $deleting = array();
        foreach($to_revert as $info)
        {
            $recname = $info["record"] . "/" . $info["table"];

            if($info["changes"])
                return("Rekord $recname po tej transakcji ($transaction) został zmodyfikowany przez inną transakcję (" . $info["oxid"] . ")");

            /* W zależności od typu operacji, jaka była wykonywana... */
            if($info["type"] == 3)
                /* Rekord został skasowany - aby cofnąć, trzeba odkasować */
            $commands[] = array("undel", $info["table"], $info["record"]);
            else if($info["type"] == 1)
            {
                /* Rekord został utworzony - aby cofnąć, trzeba go skasować */

                /* Jeśli podczas tworzenia rekordu ustawione zostało więcej niż jedno pole
                   (czyli prawie zawsze), w to miejsce dojdziemy dla tego rekordu
                   dla każdego z pól; żeby uniknąć wydawania polecenia skasowania
                   wiele razy, kontrolujemy zapisanie kasowania rekordu w $deleting[] */
                if(!isset($deleting[$recname]))
                {
                    $commands[] = array("del", $info["table"], $info["record"]);
                    $deleting[$recname] = 1;
                }
            }
            else if($info["type"] == 2)
            {
                /* Rekord został modyfikowany */

                /* Żeby cofnąć operację modyfikacji, trzeba wykonać modyfikację... */
                $commands[] = array("upd", $info["table"], $info["record"], $info["field"], $info["previous"]);
            }
        }
        /* Chyba się udało. Do listy poleceń dołąćzamy jeszcze polecenie usunięcia informacji z register */
        $commands[] = array("inv", "register", $transaction);
        return($commands);
    }


    private function revert_do($commands)
    {
        /* backaway - cofnij operację wg logu zmian
                $commands - lista operacji do wykonania, uzyskana z backaway_prepare() */

        /* Po prostu wykonujemy po kolei operacje zarekomendowane przez backaway_prepare() */
        foreach($commands as $cmd)
        {
            list($type, $table, $record, $field, $contents) = $cmd;

            /* Wszystkie sql_*() mają jako $log podane false - oczywiście tych operacji nie logujemy
               (powstałaby pętla, nie dałoby się cofać więcej niż jednej transakcji do tyłu) */
            switch($type)
            {
                case "undel": vsql::update($table, array("deleted" => 0), $record, "id", false); break;
                case "del": vsql::delete($table, $record, "id", false); break;
                case "upd": vsql::update($table, array($field => $contents), $record, "id", false); break;
                case "inv": vsql::delete($table, $record, "transaction", false); break;
            }
        }
    }

    function revert()
    {
        access::ensure("revert");

        if(is_array($r = $this->revert_prepare($this->get_xid())))
        {
            $this->revert_do($r);
            $r = "Transakcja została cofnięta";
        }
        echo json_encode(array("msg" => $r));
    }
}
