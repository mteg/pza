<?

session_start();

class access
{
    private static $rights = false;
    public static $nologin = false;

    static function aliases()
    {
        return array(
            " default " => " -default- ref(grounds) ref(members) ref(rights) search(grounds) ",
            " member " => " -member- ",
            " ro " => "-ro- ",
        );
    }

    static function load_rights()
    {
        if(!(static::$rights === false)) return;

        if(!isset($_SESSION["user_id"]))
        {
            if(static::$nologin)
            {
                static::$rights = array();
                return;
            }
            header("Location: /insider/checkin");
            exit;
        }

        $uid = $_SESSION["user_id"];

        $uinfo = vsql::get("SELECT u.id, u.access, m.id AS membership FROM users AS u" .
                " LEFT JOIN memberships AS m ON m.user = u.id " .
                        " AND m.deleted = 0 AND m.starts <= NOW() AND m.due >= NOW() " .
                " WHERE u.deleted = 0 AND u.id = " . vsql::quote($uid) .
                " LIMIT 1");

        $perm = trim($uinfo["access"] . " default");
        if($uinfo["membership"])
            $perm = trim($perm . " member");

        if($entls = vsql::get("SELECT GROUP_CONCAT(r.access SEPARATOR ' ') AS access " .
                " FROM users AS u " .
                " JOIN entitlements AS e ON e.deleted = 0 AND e.user = u.id ".
                        " AND e.starts <= NOW() AND e.due >= NOW() " .
                " JOIN rights AS r ON r.deleted = 0 AND r.id = e.right " .
                " WHERE u.deleted = 0 AND u.login = " . vsql::quote($uid) .
                " GROUP BY u.login", "access"))
            $perm = trim($perm . " " . $entls);


        static::$rights = static::expand_rights($perm);
    }

    static function expand_rights($PERMISSIONS)
    {
        /* expand_permissions - rozwiń aliasy na prawa w ciągu opisującym prawa dostępu

            $PERMISSIONS - ciąg opisujący prawa

            Zwraca ciąg praw elementarnych, po rozwinięciu aliasów na większą ilość praw

            Oryginalne aliasy pozostają w zwracanym ciągu.
            */


        /* Aliasy na prawa - każdy rozwijany alias pozostawiamy po zamianie jako -alias-, żeby się nam nie rozwijał w kółko w samego siebie.
           Na koniec, przed wyjściem z funkcji, usuniemy kreski. */
        $map1 = static::aliases();

        /* Aliasy na prawa na tabelach */
        $map2 = array(
            '/ (read)\(([a-zA-Z0-9_]+)\)/' => ' search(\2) ',
            '/ (write)\(([a-zA-Z0-9_]+)\)/' => ' add(\2) edit(\2) ',
            '/ (delete)\(([a-zA-Z0-9_]+)\)/' => ' \1(\2) add(\2) edit(\2) search(\2) history(\2) ',
            '/ (search|history)\(([a-zA-Z0-9_]+)\)/' => ' \1(\2) view(\2) ',
        );

        /* Na początku i końcu dodajemy po spacji; wszystkie sekwencje białych znaków zamieniamy na jedną spację */
        $PERMISSIONS = " " . preg_replace('/[,\s]+/', ' ', $PERMISSIONS) . " ";

        $old_perm = "";
        while($old_perm != $PERMISSIONS) /* Dopóki coś się zmienia ... */
            /* ... dokonujemy podmiany aliasów z $map1 */
            $PERMISSIONS = strtr($old_perm = $PERMISSIONS, $map1);

        $old_perm = "";
        while($old_perm != $PERMISSIONS) /* Dopóki coś się zmienia ... */
            /* ... dokonujemy podmiany praw zapisanych jako xx(yy,zz,tt...) na xx(yy) xx(zz) xx(tt) */
            $PERMISSIONS = preg_replace('/ ([a-z_]+)\(([a-zA-Z0-9_]+),([a-zA-Z0-9_,]+)\) /', ' \1(\2) \1(\3) ', $old_perm = $PERMISSIONS);

        /* Dla każdego aliasu zaisanego w  $map2 ... */
        foreach($map2 as $reg => $repl)
            /* Podmieniamy! */
        $PERMISSIONS = preg_replace($reg, $repl, $PERMISSIONS);

        /* Zamieniamy "-prawo-" na "prawo" - przywracamy oryginalne nazwy aliasów jako samodzielne prawa */
        $PERMISSIONS = preg_replace('/-([a-z_]+)-/', '\1', $PERMISSIONS);

        /* Likwidujemy powtórki i rozbijamy listę praw na tablicę asocjacyjną, której kluczami są nazwy praw */
        $PERMISSIONS = array_flip(explode(" ", preg_replace('/[,\s]+/', ' ', $PERMISSIONS)));

        return $PERMISSIONS;

    }

    static function has($right)
    {
        static::load_rights();
        if(isset(static::$rights["god"])) return true;
        return isset(static::$rights[$right]);
    }

    static function glob($right)
    {
        static::load_rights();
        if(isset(static::$rights["god"])) return true;
        if(isset(static::$rights[$right])) return true;
        foreach(static::$rights as $r=> $junk)
            if(fnmatch($right, $r)) return true;

        return false;
    }

    static function ensure($right)
    {
        if(!static::has($right))
            die("ERR Brak niezbędnego prawa dostępu: " . $right);
    }

    static function getuid()
    {
        static::load_rights();
        return $_SESSION["user_id"] ? $_SESSION["user_id"] : 0;
    }
}
