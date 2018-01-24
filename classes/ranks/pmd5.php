<?php
class ranks_pmd5 extends ranks_pp
{
    function scores($id)
    {
        $scores = parent::scores($id);

        /* Rozbij na osoby */
        $peruser = array();
        foreach($scores as $ent) {
            if(!is_array($peruser[$us = $ent["user"]])) $peruser[$us] = array();
            $peruser[$us][] = $ent;
        }


        $out = array();
        foreach($peruser as $uid => $catscores)
        {
            if(count($catscores) > 5) {
                usort($catscores, function ($a, $b) {
                    return $b["points"] - $a["points"];
                });
                $cnt = 0;
                foreach($catscores as $n => $ent)
                {
                    if($cnt >= 5) $catscores[$n]["nosum"] = true;
                    $cnt++;
                }
            }
            $out = array_merge($out, $catscores);
        }

        /* Posortuj wg daty wyniku */
        usort($out, function ($a, $b) { return strcmp($a["event_date"], $b["event_date"]); });


        return $out;
    }
}