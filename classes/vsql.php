<?

class vsql
{
    static $db = false;
    static $conf = array(
        "server" => "localhost",
        "username" => "pza",
        "password" => "PizetEj",
        "db" => "pza",
    );

    static function init()
    {
        /* Głównie konfiguracja bazy SQL */
        $conf = static::$conf;

        /* Połączenie z bazą danych */
        $m = static::$db =
            new mysqli($conf["server"], $conf["username"], $conf["password"], $conf["db"]);

        if($m->connect_errno)
            die("Cannot connect to MySQL (1): " . $m->connect_error . "\n");

        vsql::query("SET NAMES 'utf8'");
    }


    static function quote($string)
    {
        if(!static::$db) static::init();
        /* static::quote - przygotuj ciąg znaków do wstawienia w zapytanie SQL (mysql_real_escape_string + cudzysłowy wokół ciągu) */
        return('"' . static::$db->real_escape_string($string) . '"' );
    }

    static function query($query)
    {
        if(!static::$db) static::init();

        $t = microtime(TRUE);
        /* vsql::query - wykonaj zapytanie SQL i obsłuż błąd, jeśli wystąpi (zakończ wykonywanie skryptu) */

        if(php_sapi_name() == "cli")
            echo $query . "\n";

        $q = static::$db->query($query);
        if(!$q)
        {
            $err = static::$db->error;
            die("Błąd podczas wykonywania zapytania SQL '$query': " . $err);
        }
        return($q);
    }

    static function get($query, $col = false, $default = 0)
    {
        /* vsql::get - Wykonaj zapytanie SQL, które zwraca tylko jeden wiersz wyniku i
                 wyciągnij ten wiersz jako tablicę asocjacyjną

                     Jeśli podano pole, które ma zostać zwrócone ($col), zamiast
                     tablicy asocjacyjnej zostanie zwrócona wartość wskazanej
                     kolumny

                     Jeśli zapytanie nie zwróci ani jednego wiersza, funkcja zwróci
                     wartość $default.
        */
        $q = static::query($query);
        if($r = $q->fetch_assoc())
            return($col ? $r[$col] : $r);
        return($default);
    }

    static function retr($query, $index = "id", $column = false)
    {
        /* vsql::retr - Wykonaj zapytanie SQL, które zwraca wiele wierszy. Wydobądź
                    wszystkie wiersze wyniku i zapisz je w tablicy asocjacyjnej.

                    Zwracana tablica wyników jest indeksowana po kolumnie $index
                    (każdy wiersz wyniku jest wpisywany pod klucz wynikający
                    z zawartości kolumny $index).

                    O ile nie podano $column, każdy element zwracanej tablicy
                    wyników jest tablicą asocjacyjną, zawierającą zawartości
                    poszczególnych kolumn.

                    Jeśli podano $column, tablica wyników jest konstruowana
                    jako mapowanie z kolumny $index do kolumny $column
                    (jej wartościami są zawartości kolumny $column kolejnych
                    wierszy).
        */
        $q = static::query($query); $arr = array(); $n = 0;
        if($index == "")
            while($r = $q->fetch_assoc())
                $arr[++$n] = $column ? $r[$column] : $r;
        else
            while($r = $q->fetch_assoc())
                $arr[$r[$index]] = $column ? $r[$column] : $r;
        return($arr);
    }

    static function log($type, $table, $data, $id, $old_data = array())
    {
        /* vsql::log - Wstaw informację o operacji wykonanej na bazie do logu zmian

                 $type - typ operacji (1 - wstawianie, 2 - aktualizacja, 3 - kasowanie)
                 $table - tablica SQL, na której dokonana została operacja
                 $data - aktualna zawartość poszczególnych pól rekordu
                         (tablica asocjacyjna nazwa_kolumny => wartość)
                 $id - identyfikator rekordu, który uległ zmianie
                 $old_data - poprzednia zawartość poszczególnych pól rekordu
        */
        static $transaction = false;

        /* Wszystkie wpisy do logu w ramach pojedynczego wywołania
          skryptu będą miały taki sam identyfikator w polu 'transaction'. */
        if(!$transaction) $transaction = uniqid("");


        foreach($data as $name => $value)
        {
            if($old_data[$name] == $value && $type < 3) continue;
            $query  = "INSERT INTO `register` SET `creat` = NOW(), ";
            $query .=  	" source = " . static::quote($_SERVER["REMOTE_ADDR"]) . ", user = " . access::getuid() . ", ";
            $query .=  	" `table` = " . static::quote($table) . ", record = " . static::quote($id) . ", ";
            $query .=  	" field = " . static::quote($name) . ", type = " . static::quote($type) . ", ";
            $query .=       " contents = " . static::quote($value) . ", previous = " . static::quote($old_data[$name]) . ", ";
            $query .= "transaction = " . static::quote($transaction);
            static::query($query);
        }
    }

    static function update($table, $data, $clause, $pk = "id", $log = true, $delayed = "")
    {
        /* vsql::update - zaktualizuj rekord w tablicy SQL, lub dodaj nowy rekord
                $table - tablica SQL
                $data - nowe wartości kolumn do ustawienia, w postaci
                            tablicy asocjacyjnej nazwa_kolumny => wartość
                $clause - identyfikator lub inny wyróżnik rekordu do zaktualizowania;
                          jeśli 0 - tryb dodawania nowych rekordów
                $pk - wyróżnik (nazwa kolumny), na podstawie którego rekordy do
                      zaktualizowania zostaną odnalezione; uwaga: ze względu
                      na dobro mechanizmu logowania, nie powinno się używać
                      innych wyróżników, niż takich jednoznacznie identyfikujących
                      jeden rekord
                $log - jeśli $log = false, operacja nie zostanie zalogowana
                       (powinno być używane tylko do cofania operacji)
        */

        if($log && $pk != "id" && $clause)	/* Aktualizacja po polu innym niż ID */
        {
            foreach(static::retr("SELECT id FROM `$table` WHERE `$pk` = " . static::quote($clause), "id", "id") as $id)
                static::update($table, $data, $id);

            return $clause;
        }

        /* Wyciągnij poprzednie wartości pól rekordu (na potrzeby logowania) */
        if($clause && $log)
            $prevdata = static::get("SELECT * FROM `$table` WHERE `$pk` = " . static::quote($clause));

        /* Lista pól typu id_m występujących w tablicy $table */
        /* Potrzebna, bo dane wpisywane do wszystkich pól tego typu musimy uporządkować (wyeliminować powtórki, posortować) */

        /* Budujemy zapytanie ... */
        $query = $clause ? "UPDATE {$delayed} `$table` SET" : "INSERT {$delayed} INTO `$table` SET";
        foreach($data as $name => $value)
            $query .= ' `' . $name . '` = ' . static::quote($value) . ', ';

        /* .. i wykonujemy je */
        if($clause)
        {
            $query .= " `mod` = NOW(), `mod_by` = " . static::quote(access::getuid());
            $query .= " WHERE `$pk` = " . static::quote($clause);
        }
        else
            $query .= " `creat` = NOW(), `creat_by` = " . static::quote(access::getuid());

        static::query($query);

        /* Wpis do register */
        if($log)
        {
            if($clause)
                static::log(2, $table, $data, $clause, $prevdata);
            else
                static::log(1, $table, $data, $clause = static::$db->insert_id);
        }
        else if(!$clause)
            $clause = static::$db->insert_id;

        return $clause;
    }

    static function insert($table, $data, $log = true, $delayed = "")
    {
        /* vsql::insert - wstaw rekord do tablicy SQL
            $table - tablica SQL
            $data - dane rekordu do wstawienia w postaci nazwa_kolumny => dane
            $log - jeśli false, transakcja nie zostanie zalogowana w tabeli register
        */
        return static::update($table, $data, 0, "id", $log, $delayed);
    }

    static function delete($table, $clause, $pk = "id", $log = true)
    {
        /* vsql::delete - oznacz rekord w tablicy SQL jako skasowany
            $clause - identyfikator lub inny wyróżnik rekordu do zaktualizowania;
            $pk - wyróżnik (nazwa kolumny), na podstawie którego rekordy do
                  usunięcia zostaną odnalezione
             */
        if($log && $pk != "id" && $clause)	/* Aktualizacja po polu innym niż ID */
        {
            foreach(static::retr("SELECT id FROM `$table` WHERE `$pk` = " . static::quote($clause), "id", "id") as $id)
                static::delete($table, $id);
            return;
        }

        static::query("UPDATE `$table` SET deleted = NOW(), deleted_by = " . static::quote(access::getuid()) . " WHERE `$pk` = " . static::quote($clause));
        if($log)
            static::log(3, $table, array("" => ""), $clause);
    }
    static function order($id_string)
    {
        /* vsql::order - Funkcja eliminuje powtórki i sortuje ciąg liczb oddzielnych przecinkami. */
        $new_s = array();
        foreach(array_unique(explode(",", $id_string)) as $v)
            if(is_numeric($v) && (intval($v) > 0))
                $new_s[] = $v;

        sort($new_s, SORT_NUMERIC);
        return(implode(",", $new_s));
    }

    static function id_condition($a, $field = "id")
    {
        /* vsql::id_condition - Zbuduj fragment zapytania SQL typu <pole> IN (.., .., ...)
            $a - tablica identyfikatorów, lub oddzielona przecinkami ich lista
            $field - nazwa pola, na którym budować warunek (domyślnie "id")

            Zwraca fragment zapytania SQL do wykorzystania.
        */
        if(!is_array($a)) $a = explode(",", strtr($a, array(" " => ",", "|" => ",")));
        $b = array();
        foreach($a as $k => $v)
            $b[] = vsql::quote($v);

        if(!count($b)) return ("0 = 1");

        return "$field IN (" . implode(", ", $b) . ")";
    }

    static function id_retr($ucopy, $field, $query, $idfield = "id", $sorting = "", $column = false)
    {
        /* vsql::id_retr - wydobądź dane o wielu obiektach jednocześnie
            $ucopy - lista identyfikatorów, lub innych jednoznacznych kluczy identyfikujących obiekty
                 może być w postaci tablicy lub ciągu wartości oddzielonych przecinkami/spacjami/rurkami
            $field - nazwa pola w zapytaniu SQL, do którego odnoszą się klucze z $ucopy
            $query - zapytanie SQL sformułowane w ten sposób, że można bezpośrednio na jego koniec
                 dokleić warunek na $field (np. kończące się na WHERE lub AND)
            $idfield - pole z wyników zapytania SQL $query, po którym należy indeksować tablicę wyników
            $sorting - ciąg znaków do dodania na koniec zapytania SQL, po doklejeniu warunku na $field
            $column - jeśli inaczej niż false, zmienia sposób podawania wyniku - zamiast
                     tablicy asocjacyjnej z wartościami poszczególnych kolumn,
                     dla każdego identyfikatora zostanie zwrócona jedynie
                     sama wartość jednej, wskazanej przez $column kolumny.

            Zwraca tablicę asocjacyjną, indeksowaną po polu $idfield, której wartościami
            są tablice asocjacyjne z wynikami zapytania $query dla poszczególnych
            obiektów lub (jeśli podano $column), wartość kolumny $column z wyniku
            zapytania $query.

        */
        if(!is_array($ucopy)) $ucopy = explode(",", strtr($ucopy, array(" " => ",", "|" => ",")));
        $info = array(); /* Tablica wynikowa */

        /* Ta funkcja jest przewidziana do radzenia sobie z tysiącami identyfikatorów w $ucopy jednocześnie */
        while(count($ucopy) > 0)	/* Dopóki mamy jeszcze co wyciągać ... */
        {
            /* Wydobywamy dane po kawałku - wyciągamy pierwsze 100 identyfikatorów z $ucopy do $ucopy_chop */
            if(count($ucopy) > 100)
            {
                $ucopy_chop = array_slice($ucopy, 0, 100);
                $ucopy      = array_slice($ucopy, 100);
            }
            else
            {
                $ucopy_chop = $ucopy;
                $ucopy = array();
            }

            /* Pobieramy partię wyników ... */
            $info += vsql::retr($query . static::id_condition($ucopy_chop, $field) . " " . $sorting, $idfield, $column);
        }
        return($info);
    }

}

