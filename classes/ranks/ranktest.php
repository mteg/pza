<?
    class ranks_ranktest extends rank
    {
        function compute($results)
        {
            $out = array();
            foreach($results as $i)
            {
                $us = $i["user"];
                if(!isset($out[$us]))
                    $out[$us] = array_merge($i, array("points" => 0));

                $out[$us]["points"] += $i["points"];
            }


            usort($out, function ($a, $b) {
               return $b["points"] - $a["points"];
            });

            $points = -1; $pos = 0; $rank = array();
            foreach($out as $k => $i)
            {
                if($i["points"] != $points) $pos ++;
                $points = $i["points"];
                $rank[] = array(
                    "user" => $i["user"],
                    "points" => $points,
                    "position" => $pos,
                );
            }

            return $rank;
        }
    }