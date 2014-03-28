<?
class tpl_loader extends Smarty_Resource_Custom
{
    protected function fetch($name, &$source, &$mtime)
    {
        $mtime = 0; $source = ""; $id = 0;

        if(is_numeric($name))
            $id = $name;

        while($source == "")
        {
            $v = vsql::get("SELECT template, UNIX_TIMESTAMP(`mod`) AS ts,
                                   parent FROM categories WHERE " .
                            ($id ? ("id = " . vsql::quote($id)) :
                                  ("path = " . vsql::quote($name))));
            $id = $v["parent"];
            $source = $v["template"];
            $mtime = max($v["ts"], $mtime);
            if(!$id) break;
        }
    }

    protected function fetchTimestamp($name)
    {
        $source = ""; $mtime = 0;
        $this->fetch($name, $source, $mtime);

        return $mtime;
    }
}
