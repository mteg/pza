<?
    class insider_rank extends insider_action
    {

        static function get_rank($id)
        {
            $rank_info = vsql::get("SELECT id, name, options, remarks FROM grounds WHERE deleted = 0 AND id = " . vsql::quote($id));
            $a = explode(":", $rank_info["options"]);

            $rank_name = "ranks_ranktest";
            if(preg_match('/^[a-z_0-9]+$/', $a[0]))
                if(file_exists("classes/ranks/" . $a[0] . ".php"))
                    $rank_name = "ranks_" . $a[0];


            $rank = new $rank_name;

            $scores = $rank->scores($id);
            $results = $rank->compute($scores);
            $events = $rank->events($results);

            return array(
                "scores" => $scores,
                "results" => $results,
                "events" => $events,
            );
        }

        static function get_ranks($hidden = false)
        {
            if($hidden)
                $hcond = "";
            else
                $hcond = " AND CONCAT(options, ':') NOT LIKE CONCAT('%:hide:%')";
            $rank_options = vsql::retr("SELECT id, CONCAT(IF(start <> '0000-00-00', CONCAT(YEAR(start), ': '), ''), name) AS name FROM grounds WHERE type = 'rank:s' AND deleted = 0 $hcond ORDER BY start DESC, name", "id", "name");
            return $rank_options;
        }


        function route()
        {
            $id = $_REQUEST["id"];
            $this->S->assign("ranks", $rank_options = $this->get_ranks(true));
            if(!$id) $id = key($rank_options);

            $rankinfo = $this->get_rank($id);

            list($scores, $results, $events) = $rankinfo;
            if($_REQUEST["debug"])
            {
                header("Content-type: text/plain; charset=utf-8");
                echo "scores: \n";
                print_r($scores);
                echo "scores: \n";
                print_r($results);
                echo "scores: \n";
                print_r($events);
                exit;
            }


            $this->S->assign($rankinfo);
            $this->S->display("insider/rank.html");

        }
    }
