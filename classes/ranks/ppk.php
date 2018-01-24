<?php
    class ranks_ppk extends ranks_pp
    {
        function scores($id)
        {
            $scores = parent::scores($id);

            /* Rozbij na osoby */
            $peruser = array();
            foreach($scores as $ent) {
                if(!is_array($peruser[$us = $ent["user"]][$ca = $ent["categ"]])) $peruser[$us][$ca] = array();
                $peruser[$us][$ca][] = $ent;
            }


            $out = array();
            foreach($peruser as $uid => $cats)
            {
                /* Odrzuć tych z jedną kategorią */
                if (count($cats) == 1) continue;

                /* Weź trzy najlepsze wyniki z każdej kategorii */
                foreach($cats as $catscores)
                {
                    if(count($catscores) > 3) {
                        usort($catscores, function ($a, $b) {
                            return $b["points"] - $a["points"];
                        });
                        $catscores = array_slice($catscores, 0, 3);
                    }
                    $out = array_merge($out, $catscores);
                }
            }

            /* Posortuj wg daty wyniku */
            usort($out, function ($a, $b) { return strcmp($a["event_date"], $b["event_date"]); });


            return $out;
        }
    }