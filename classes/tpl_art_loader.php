<?
class tpl_art_loader extends Smarty_Resource_Custom
{
    protected function fetch($name, &$source, &$mtime)
    {
        $mtime = 0; $source = ""; $id = 0;
        if(is_numeric($name))
        {
            $v = vsql::get("SELECT content, UNIX_TIMESTAMP(`mod`) AS ts FROM content WHERE deleted = 0 AND id = " . vsql::quote($name));
            $source = $v["content"];
            $mtime = $v["ts"];
        }
        if($mtime == 0) $mtime = 1;
    }
}
