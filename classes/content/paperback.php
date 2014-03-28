<?
class content_paperback extends content_file
{
    function render_object($id, $path)
    {
        if(preg_match('/\.pdf$/', $path))
            return parent::render_object($id, $path);

        $i = vsql::get("SELECT id, categories, title, lead, date, authors, thumbnail, file_version FROM content WHERE id = " . vsql::quote($id));
        if(preg_match('/\.jpg$/', $path))
            return content_file::serve($id, $i["file_version"], "image/jpeg", "thumb");

        $i["index"] = vsql::retr("SELECT id, title, authors, content AS pages FROM content WHERE link = " . vsql::quote($id) .
                            " AND deleted = 0
                             ORDER BY CAST(pages AS SIGNED), pages, title");

        $this->S->assign("title", $i["title"]);
        $i["pdf_thumbnail"] = content_file::exists($id, $i["file_version"], "thumb");

        $this->S->assign("paperback", $i);
        $this->S->assign("content", $this->S->fetch("paperback/paperback.html"));

        header("Content-type: text/html; charset=utf-8");
        $this->S->display("cat:" . preg_replace('/,[0-9,+]$/', '', $i["categories"]));

        return true;
    }

    function render_category($cat_id, $path)
    {
        $br = new browser("paperback");
        $catdata = vsql::get("SELECT name, path FROM categories WHERE id = " . vsql::quote($cat_id));
        $catlist = $br->paging()->set("categories", $catdata["path"])->ls();

        foreach($catlist as $n => $i)
            $catlist[$n]["pdf_thumbnail"] = content_file::exists($i["id"], $i["file_version"], "thumb");

        $this->S->assign("title", $catdata["name"]);
        $this->S->assign("paperindex", $catlist);
        $this->S->assign("br", $br);
        $this->S->assign("panel", $br->panel());
        $this->S->assign("content", $this->S->fetch("paperback/issues.html"));

        header("Content-type: text/html; charset=utf-8");
        $this->S->display("cat:" . $cat_id);
        return true;
    }
}