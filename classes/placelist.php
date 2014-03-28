<?php

class placelist
{
    static function get($p)
    {
        if(!preg_match('/^[a-z0-9_]+$/', $p)) return array();
        if(!file_exists($f = "data/" . $p . ".txt")) return array();
        return array_map("trim", file($f));
    }
}
