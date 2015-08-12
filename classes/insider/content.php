<?
// todo różne funkcje do templejtów
// todo legacy links
// todo styling różnych stron
// todo menu

// todo problem cache'owania kontentu plikowego (bezpośredni link) vs restricted content (zakupione numery...)
// todo zdjęcia i galerie
// todo sprzątanie po uploadzie
// todo user pages


    class insider_content extends insider_table
    {
        public $fields = array(
            "link" =>       array("Zeszyt", "ref" => "content", "by" => "title", "field" => "issue.title", "search" => "issue.title"),
            "title" =>      array("Tytuł", "regexp" => ".+"),
            "authors" =>    "Autorzy",
            "lead" =>       array("Wprowadzenie", "nolist" => true, "nohist" => true, "type" => "area"),
            "content" =>    array("Treść"),
            "categories" => array("Kategoria", "ref" => "categories", "by" => "path", "field" => "cat_main.path", "search" => "cat_any.path", "multiple" => true),
            "short" =>      array("Nazwa krótka", "suppress" => true),
            "date" =>       array("Data pub.", "type" => "date"),
            "creat" =>      array("Data utw.", "noedit" => true, "type" => "date"),
            "mod" =>        array("Data mod.", "no" => "add,edit,view,search,col", "order" => "IF(t.mod = '0000-00-00', t.creat, t.`mod`)"),
            "weight" =>     array("Waga", "regexp" => "-?[0-9]+", "empty" => true, "order" => "DATE_ADD(t.date, INTERVAL t.weight DAY) ", "no" => "col"),
            "active" =>     array("Treść aktywna", "type" => "select", "options" => array(1 => "Tak", 0 => "Nie"), "no" => "col")
        );

        protected $capt = "<title>";
        protected $order = "weight DESC, date DESC, title";

        public $type = "article";
        protected $files = array();

        function __construct($type = false)
        {
            if($type)
                $this->type = $type;
            else if($type = $_REQUEST["type"])
                $this->type = $type;

            if($this->type == "article")
            {
                $this->actions["<classpath>/html"] = "Kod HTML";
                $this->actions["<classpath>/mce"] = array("Test MCE", "target" => "_mce");
                $this->actions["<classpath>/preview"] = array("Podgląd", "target" => "_blank");

                foreach(array("view", "hist", "list") as $p)
                    $this->fields["content"]["no" . $p] = true;

                $this->fields["content"]["type"] = "html";
                $this->fields["thumbnail"] = array("Zdjęcie", "ref" => "content", "by" => "title", "ref_order" => "`creat` DESC", "no" => "add,col", "empty" => true);
            }

            if($this->type == "file" || $this->type == "paperback")
            {
                $this->actions["<classpath>/versions"] = "Wersje";
                $this->actions["<classpath>/preview"] = array("Pobierz", "target" => "_blank");
            }

            if($this->type == "file" || $this->type == "photo")
            {
                $this->fields["content"][0] = "Typ";
                $this->fields["lead"][0] = "Komentarz";
                $this->fields["title"][0] = "Opis pliku";

                unset($this->fields["title"]["regexp"]);

                foreach(array("title", "short") as $f)
                    $this->fields[$f]["noadd"] = true;

                $this->fields["content"]["noedit"] = true;
                unset($this->fields["date"]);
            }

            if($this->type == "paperback")
            {
                $this->fields["lead"][0] = "Streszczenie";
                unset($this->fields["content"]);
                unset($this->fields["link"]);
                unset($this->fields["creat"]);
            }
            else if($this->type == "paperback_article")
            {
                $this->fields["lead"][0] = "Streszczenie";
                $this->fields["content"][0] = "Strony";
                $this->fields["content"]["regexp"] = "[0-9]+(-[0-9]+)?(,[0-9]+(-[0-9]+)?)*";
                $this->fields["content"]["regmsg"] = "Dopuszczalne formaty: '3', '3-5', '3-5,8'";
                $this->fields["content"]["empty"] = true;
                $this->fields["content"]["order"] = "CAST(t.content AS signed) <dir>, t.content ";

                $this->fields["categories"]["noedit"] = true;

                unset($this->fields["date"]);
                unset($this->fields["creat"]);

                $this->buttons["<classpath>/import"] = array("Wczytaj hurtowo", "icon" => "suitcase");
            }
            else
            {
                unset($this->fields["link"]);
                if($this->type != "photo" && $this->type != "file")
                    unset($this->fields["authors"]);
            }

            $this->actions["<classpath>/category"] = array("Zmień kategorie", "multiple" => true);

            parent::__construct();

            unset($this->actions["/insider/content/view"]);
        }

        protected function build_filter_atom($f, $s)
        {
            if($f == "categories" && $s{0} == "/")
            {
                $s = preg_replace('#[^a-z0-9_*?/]#', '', $s);
                $s = strtr($s, array("*" => "[^/]*", "?" => "[^/]"));
                $s = '^' . $s . '$';

                return "cat_any.path REGEXP " . vsql::quote($s);
            }
            else
                return parent::build_filter_atom($f, $s);
        }

        private function list_files()
        {
            $a = array();
            if($xid = $_REQUEST["xid"])
                if(preg_match('/^[0-9a-z]+/', $xid))
                    if(is_dir($dirpath = "upload/{$xid}"))
                        foreach(scandir($dirpath) as $f)
                        {
                            if($f{0} == ".") continue;
                            if(!is_file($path = $dirpath . "/" . $f)) continue;
                            $a[$f] = array("path" => $path,
                                           "mime" => trim(shell_exec("file -bi " . escapeshellarg($path))));

                            if(is_file($thumbpath = $dirpath . "/thumbnail/" . $f))
                                $a[$f]["thumbnail"] = $thumbpath;
                        }

            return $a;
        }

        private function is_free_short($id, $categories, $short)
        {
            if($id && !$categories)
                $categories = vsql::get("SELECT categories FROM content WHERE id = " . vsql::quote($id), "categories");

            $main_category = current(explode(",", $categories));
            return !vsql::get("SELECT id FROM content WHERE
                                deleted = 0 AND short = " . vsql::quote($short) .
            " AND (categories = " . vsql::quote($main_category) .
            " OR categories LIKE " . vsql::quote($main_category . ",%") . ") " .
            " AND id != " . vsql::quote($id));

        }

        protected function validate($id, &$data)
        {
            $err = parent::validate($id, $data);
            if(count($err)) return($err);

            $this->files = $files = $this->list_files();

            if((!$id) && ($this->type == "file" || $this->type == "photo") && (!count($files)))
                $err["file"] = "Wczytaj choć jeden plik";
            else if(in_array($this->type, array("file", "photo", "paperback")))
            {
                if($id && count($files) > 1)
                    $err["file"] = "Edytując rekord, nie można wczytać więcej niż jednego pliku";

                if(count($files) > 1 && $data["short"])
                    $err["short"] = "Nie można ustawiać skrótów przy wczytywaniu więcej niż jednego pliku";

                if($this->type == "paperback")
                    foreach($files as $finfo)
                        if(!preg_match('#^application/pdf#', $finfo["mime"]))
                            $err["file"] = "Zeszyty muszą być plikami PDF!";
            }

            if(isset($data["short"]))
            {
                if(strlen($data["short"]))
                {
                    if(strlen($data["short"]) > 40)
                        $err["short"] = "Nie więcej niż 40 znaków.";
                    else if(!preg_match('/^[a-z][0-9a-z_.~-]*$/', $data["short"]))
                        $err["short"] = "Skrót musi zaczynać się literą i może zawierać tylko znaki: a-z 0-9 _ - . ~";
                    else
                        if(!$this->is_free_short($id, $data["categories"], $data["short"]))
                           $err["short"] = "Inny obiekt w tej kategorii posiada już taki skrót!";
                }
            }

            return $err;
        }

        private function update_categories($id, $cats)
        {
            vsql::query("DELETE FROM category_map WHERE article = " . vsql::quote($id));

            foreach(explode(",", $cats) as $n => $cat)
                vsql::query("INSERT INTO category_map SET " .
                " article = " . vsql::quote($id) . ", " .
                " category = " . vsql::quote($cat) . ", " .
                " main =" . vsql::quote($n ? 0 : 1));
        }

        protected function update($id, $data)
        {
            if(!$id)
            {
                $data["type"] = $this->type;
                if($this->type == "paperback_article")
                    /* Copy categories! */
                    $data["categories"] =
                        vsql::get("SELECT categories FROM content WHERE id = " . vsql::quote($data["link"]), "categories");
            }

            /* Foreach file... */
            if($this->type == "article")
            {
                $loop = array("" => "");

                /* Import all photos that were uploaded and used... */
                $m = array(); $trans = array();
                preg_match_all("#['\"](\\.\\./|/)upload/[a-f0-9]+/([^'\"/.]+(\\.[^'\"/]*)?)['\"]#", $data["content"], $m, PREG_SET_ORDER);

                /* For every photo being in fact used... */
                foreach($m as $p)
                {
                    /* Execute only once for every photo... */
                    if(isset($trans[$p[0]])) continue;

                    /* If this photo was really uploaded... */
                    if(isset($this->files[$pname = urldecode($p[2])]))
                    {
                        $ext = $p[3];

                        $pdata = array(
                            "categories" => $data["categories"],
                            "content" => $this->files[$pname]["mime"],
                            "type" => "photo",
                            "file_version" => 1,
                            "title" => $pname
                        );

                        $photoid = vsql::update($this->table, $pdata, 0);
                        $this->update_categories($photoid, $data["categories"]);

                        rename($this->files[$pname]["path"], "files/" . $photoid . "_1.file");
                        if(isset($this->files[$pname]["thumbnail"]))
                        {
                            rename($this->files[$pname]["thumbnail"], "files/" . $photoid . "_1.thumb");
                            if(!$data["thumbnail"])
                                $data["thumbnail"] = $photoid;
                        }

                        $trans[$p[0]] = "'/{$photoid}{$ext}'";                    }
                }

                $data["content"] = strtr($data["content"], $trans);
            }
            else
                $loop = $this->files;

            if(!count($loop)) $loop = array("" => "");

            foreach($loop as $fname => $finfo)
            {
                if($fname)
                {
                    /* Need to update the content file! */

                    /* Get current file version */
                    if($id)
                        $ver = vsql::get("SELECT (file_version + 1) AS ver FROM content WHERE id = " . vsql::quote($id), "ver");
                    else
                        $ver = 1;

                    /* Advance to this version */
                    $data["file_version"] = $ver;

                    /* Set MIME type */
                    $data["content"] = $finfo["mime"];

                    if(!$data["short"])
                    {
                        $short_proposal = strtolower(strtr($fname, array(
                            "ą" => "a", "ć" => "c", "ę" => "e", "ó" => "o", "ł" => "l", "ń" => "n", "ś" => "s", "ź" => "z", "ż" => "z",
                            "Ą" => "a", "Ć" => "c", "Ę" => "e", "Ó" => "o", "Ł" => "l", "Ń" => "n", "Ś" => "s", "Ź" => "z", "Ż" => "z",
                            " " => "_", '"' => "", "'" => "", "," => ".", "(" => "_", ")" => "_",
                            ":" => "_"
                        )));
                        if(preg_match('/^[a-z][0-9a-z_.~-]*$/', $short_proposal))
                            if($this->is_free_short($id, $data["categories"], $short_proposal))
                                $data["short"] = $short_proposal;
                    }

                    if($this->type != "paperback")
                        $data["title"] = $fname;
                }

                $id = parent::update($id, $data);

                if($fname)
                {
                    rename($finfo["path"], ($pfx = "files/" . $id . "_" . $ver) . ".file");
                    if(isset($finfo["thumbnail"]))
                        rename($finfo["thumbnail"], $pfx . ".thumb");
                    else if(preg_match('#^application/pdf#', $finfo["mime"]))
                        $this->pdf_thumbnail($pfx . ".file", $pfx . ".thumb");
                }

                if(isset($data["categories"]))
                {
                    $this->update_categories($id, $data["categories"]);
                    foreach(vsql::retr("SELECT id FROM content WHERE link = " . vsql::quote($id), "id", "id") as $linked_id)
                        $this->update_categories($linked_id, $data["categories"]);
                }

                /* Next uploaded file ... */
                $id = 0;
            }
        }

        protected function defaults()
        {
            foreach(array(access::getuid(), 0) as $uid)
                if($data = vsql::get($q= "SELECT cat.path AS categories, IF(c.link != 0, c.link, '') AS link FROM content AS c " .
                            " JOIN category_map AS cm ON cm.article = c.id AND cm.main = 1 " .
                            " JOIN categories AS cat ON cat.id = cm.category " .
                            " WHERE c.deleted = 0 AND c.type = " .
                            vsql::quote($this->type) .
                            ($uid ? (" AND c.creat_by = " . vsql::quote($uid)) : "") .
                            " ORDER BY c.creat DESC LIMIT 1"))
                    break;
            if(!$data) $data = array();

            if($cat = $_REQUEST['category'])
                $data["categories"] = vsql::get("SELECT path FROM categories WHERE id = " . vsql::quote($cat), "path", "");

            $data["date"] = date("Y-m-d");
            return $data;
        }

        protected function retr_query($filters, $extra_cols = "")
        {
            $query = "SELECT SQL_CALC_FOUND_ROWS t.id, t.date, t.title, t.short, t.creat, cat_main.path AS categories, issue.title AS link " .
                ($this->type != "article" ? ", t.content" : "") .
                $extra_cols .
                " FROM " . $this->table . " AS t " .
                " LEFT JOIN category_map AS cm_main ON cm_main.article = t.id AND cm_main.main = 1 " .
                " LEFT JOIN category_map AS cm_any ON cm_any.article = t.id " .
                " LEFT JOIN categories AS cat_main ON cat_main.id = cm_main.category " .
                " LEFT JOIN categories AS cat_any ON cat_any.id = cm_any.category " .
                " LEFT JOIN content AS issue ON issue.id = t.link AND issue.deleted = 0 " .
                " WHERE t.deleted = 0 " . $filters .
                $this->retr_extra_filters() .
                " AND t.type = " . vsql::quote($this->type) .
                " GROUP BY t.id ";

            return $query;
        }

        protected function complete_constraints($f)
        {
            if($f == "link")
                return " AND type = 'paperback'";
            if($f == "thumbnail")
                return " AND type = 'photo'";
            return "";
        }

        public function html()
        {
            $data = $this->fetch($_REQUEST["id"]);
            if(!$data) die("Obiekt nie istnieje.");

            $this->S->assign("data", $data);
            $this->S->display("insider/content_html.html");
        }

        public function preview()
        {
            header("Location: /" . $_REQUEST["id"]);
            exit;
        }

        static function gen_xid()
        {
            while($xid = (date("YmdHis") . substr(md5(uniqid("")), 0, 8)))
                if(!file_exists("upload/{$xid}"))
                    return $xid;
            return "NONE";
        }

        public function edit()
        {
            $this->S->assign("xid", $this->gen_xid());
            parent::edit();
        }

        public function add()
        {
            $this->S->assign("xid", $this->gen_xid());
            parent::add();
        }

        public function import()
        {
            if(isset($_REQUEST["entries"]))
            {
                $a = array();
                $ents = $_REQUEST["entries"]; $sect = ""; $link = $_REQUEST["link"];
                foreach(explode("\n", $ents) as $line)
                {
                    if(!($line = trim($line))) { $sect = ""; continue; }
                    if(!strpos($line, "|")) { $sect = $line; continue; }

                    list($title, $authors, $pages, $summary) = explode("|", $line, 4);
                    if($sect)
                        $title = $sect . " | " . $title;

                    $data = array(
                        "link" => $link,
                        "title" => $title,
                        "authors" => $authors,
                        "content" => $pages,
                        "lead" => $summary);

                    if((!($err = $this->validate(0, $data))) && $_REQUEST["commit"])
                        $this->update(0, $data);

                    $a[] = array_merge($data, array("err" => $err));
                }

                $this->S->assign("a", $a);
                if(!$_REQUEST["commit"])
                    $this->S->display("insider/paperbacks_import_preview.html");
                return;
            }
            $this->S->display("insider/paperbacks_import.html");
        }

        private function list_versions($id)
        {
            return vsql::retr("SELECT r.id, r.creat, u.ref AS user, r.contents AS file_version, r2.contents AS mime, r3.contents AS short
                     FROM register AS r
                     LEFT JOIN register AS r2 ON r2.transaction = r.transaction AND r2.field = 'content'
                     LEFT JOIN register AS r3 ON r3.transaction = r.transaction AND r3.field = 'short'
                     LEFT JOIN users AS u ON r.user = u.id
                     WHERE r.field = 'file_version' AND r.table = 'content' AND r.record = " . vsql::quote($id) .
                    " ORDER BY r.creat", "file_version");
        }

        public function download()
        {
            $v = $this->list_versions($id = $_REQUEST["id"]);
            if(isset($v[$ver = $_REQUEST["version"]]))
                content_file::serve($id, $ver, trim($v[$ver]["mime"]));
            else
                fail(404, "Nie odnaleziono żądanej wersji");
        }

        public function versions()
        {
            $id = $_REQUEST["id"];
            $this->S->assign("versions", $this->list_versions($id));
            $this->S->display("insider/content_versions.html");
        }

        public function category()
        {
            $this->S->display("insider/content_category.html");

        }

        public function view()
        {
            $links = array();
            if($i = vsql::get("SELECT c.title, c.type, c.short, ca.path
                            FROM content AS c
                            LEFT JOIN category_map AS cm ON cm.article = c.id AND cm.main = 1
                            LEFT JOIN categories AS ca ON ca.id = cm.category
                            WHERE c.id = " .vsql::quote($id = $_REQUEST["id"])))
            {
                $ext = "";
                if(in_array($i['type'], array("photo", "file", "paperback")))
                {
                    $m = array();
                    if(preg_match('/\.[a-z0-9]+$/i', $i["title"], $m))
                        $ext = $m[0];
                }

                if($i["type"] == "article") $ext = ".html";

                if($i["path"] && $i["short"])
                    $links[] = $i["path"] . "/" . $i["short"];

                if($i["path"])
                    $links[] = $i["path"] . "/" . $id . $ext;

                $links[] = "/" . $id . $ext;
            }

            $this->S->assign("links", $links);
            parent::view();
        }

        private function pdf_thumbnail($infile, $outfile)
        {
            $gs_cmd = "gs >/dev/null 2>/dev/null -q -sDEVICE=jpeg -dJPEGQ=85
                    -dFirstPage=1 -dLastPage=1
                    -dNOPAUSE -dBATCH -dDOINTERPOLATE
                    -dTextAlphaBits=4 -dGraphicsAlphaBits=4 -r72
                    -dDEVICEWIDTHPOINTS=256 -dDEVICEHEIGHTPOINTS=256
                    -dPDFFitPage -dEPSFitPage
                    -sOutputFile=" . escapeshellarg($outfile) .
                    " " . escapeshellarg($infile);
            $gs_cmd = strtr($gs_cmd, array("\n" => " ", "\r" => " "));
            system($gs_cmd);
        }

        public function correct_entities()
        {
            header("Content-type: text/plain; charset=utf-8");
            foreach(vsql::retr("SELECT id, title, content, lead FROM content")
                        as $id => $i)
            {
                $new_flag = false;
                foreach(array("title", "content", "lead") as $f)
                {
                    $new = preg_replace_callback(
                        "/(&#[0-9]+;)/",
                        function($m) {
                            return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES");
                        }, $i[$f]);
                    if($new != $i[$f])
                    {
                        $i[$f] = $new;
                        $new_flag = true;
                    }
                }
                if($new_flag)
                {
                    $q = "UPDATE content SET title = " . vsql::quote($i["title"]) .
                        ", lead = " . vsql::quote($i["lead"]) . ", content= " .
                        vsql::quote($i["content"]) . " WHERE id = " . vsql::quote($id);

                    echo $q . "\n";
                    if($_REQUEST["do"])
                        vsql::query($q);
                }
            }

        }

        protected function retr_extra_filters()
        {
            $f = "";
            if($_REQUEST["category"])
                $f .= " AND cat_main.id = " . vsql::quote($_REQUEST["category"]);
            return $f;
        }
    }
