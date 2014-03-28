<?
class content_file extends content
{
    protected $disposition = "attachment";

    static function exists($id, $version, $ext = "")
    {
        if(file_exists($path = "files/{$id}_{$version}.{$ext}"))
            return $path;

        return false;

    }

    static function serve($id, $version, $mime = "", $ext = "")
    {
        if(!($path = static::exists($id, $version, $ext)))
            fail(404, "Plik {$id} nie został przesłany na serwer.");
        // todo obowiązkowo naprawić cache

        header("Content-length: ". filesize($path));
        if($mime)
            header("Content-type: " . $mime);

        readfile($path);
        return true;
    }

    function render_object($id, $path)
    {
        $d = vsql::get("SELECT a.id, a.file_version, a.content, a.title
                        FROM content AS a
                        WHERE a.id = " . vsql::quote($id) . " AND a.deleted = 0");

        /* Brak pliku w bazie danych */
        if(!$d)
            fail(404, "Nie odnaleziono pliku {$id}");

        $version = $d["file_version"];
        $mime = $d["content"];

        if($d["title"])
            header("Content-Disposition: " . $this->disposition . "; filename=" . vsql::quote($d["title"]));

        $this->serve($id, $version, $mime, $_REQUEST["thumb"] ? "thumb" : "file");

        return true;
    }
}
