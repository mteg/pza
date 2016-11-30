<?
    class insider_rank extends insider_action
    {


        function route()
        {
            $id = $_REQUEST["id"];
            $rank_options = vsql::retr("SELECT id, name FROM grounds WHERE type = 'rank:s' AND deleted = 0 ORDER BY start DESC, name", "id", "name");
            if(!$id) $id = key($rank_options);

            $rank_info = vsql::get("SELECT id, name, options, remarks FROM grounds WHERE deleted = 0 AND id = " . vsql::quote($id));
            $rank_name = "ranks_ranktest";
            if(preg_match('/^[a-z_0-9]+$/', $rank_info["options"]))
                if(file_exists("classes/ranks/" . $rank_info["options"] . ".php"))
                    $rank_name = "ranks_" . $rank_info["options"];


            $rank = new $rank_name;

            $scores = $rank->scores($id);
            $results = $rank->compute($scores);
            $events = $rank->events($results);
            if($_REQUEST["debug"])
            {
                header("Content-type: text/plain; charset=utf-8");
                print_r($results);
                print_r($events);
                exit;
            }


            $this->S->assign(array(
                "results" => $results,
                "events" => $events,
                "ranks" => $rank_options,
            ));
            $this->S->display("insider/rank.html");

        }
    }
