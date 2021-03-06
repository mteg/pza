<?
class content_article extends content
{
    function render_object($id, $path, $redir = false)
    {
        $d = vsql::get("SELECT a.id, a.content, a.type, a.lead, a.title, cat.id AS category_id, cat.path
                        FROM content AS a
                        JOIN category_map AS cm ON cm.article = a.id AND cm.main = 1
                        JOIN categories AS cat ON cat.id = cm.category
                        WHERE a.id = " . vsql::quote($id) . " AND a.active = 1 AND a.deleted = 0");

        /* Brak artykułu w bazie danych */
        if(!$d)
            fail(404, "Nie odnaleziono artykułu {$id}");

        /* Dokonaj podstawień */
        session_start();
        session_write_close();
        if($_SESSION["user_id"])
            $this->S->assign("insider", true);

        $this->S->assign($d);
        $text = $this->S->fetch("art:" . $id);
        $this->S->assign("content", $text);

        /* Wyświetl co trzeba */
        header("Content-type: text/html; charset=utf-8");
        if($redir)
            header("Location: http://pza.org.pl");

        if(preg_match('/^\s*\{\{extends /', $d["content"]))
            echo $text;
        else
            $this->S->display("cat:" . $d["category_id"]);

        return true;
    }

    function render_category($cat_id, $path)
    {
        for($id = $cat_id;
            $v = vsql::get($qry = "SELECT IFNULL(a.id, 0) AS id, IFNULL(cat.parent, 0) AS parent
                        FROM categories AS cat
                        LEFT JOIN category_map AS cm ON cm.category = cat.id AND cm.main = 1
                        LEFT JOIN content AS a ON a.id = cm.article AND a.deleted = 0 AND a.short = 'index'
                        WHERE cat.id = " . vsql::quote($id) . " ORDER BY a.id DESC LIMIT 1");
            $id = $v["parent"])
                if($v["id"])
                    return $this->render_object($v["id"], $path, $v["id"] != 54013 ? true : false);

        fail(404, "Nie odnaleziono indeksu kategorii {$cat_id}");
        return true;
    }
}
