<?
    abstract class rank
    {
        function update($id)
        {
            /* Flush old results */
            vsql::delete("achievements", $id, "ground");

            /* Get all achievements */
            $rank_data = vsql::get("SELECT start, options, categories FROM grounds WHERE deleted = 0 AND id = " . vsql::quote($id));
            $cats = $rank_data["categories"];

            /* Get results */
            $results =
                vsql::retr("SELECT a.id, g.start, g.type, a.position, a.user, a.points, a.style, a.duration, a.place
                            FROM achievements AS a
                            JOIN grounds AS g ON a.ground = g.id AND g.deleted = 0
                            WHERE a.deleted = 0 AND " . vsql::id_condition($cats, "a.categ"));

            /* Compute */
            foreach($this->compute($results) as $item)
                vsql::insert("achievements",
                    array_merge($item, array("ground" => $id, "date" => $rank_data["start"])));

            /* We're done! */
        }

        abstract function compute($results);
    }
