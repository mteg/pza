<?
    class insider_upcoming extends insider_action
    {
        function route()
        {
            $upcoming =
                vsql::retr("SELECT g.id, g.name, g.remarks, g.options AS link, g.categories,
                            CONCAT(g.city, IF(g.address != '', CONCAT(', ', g.address), '')) AS address,
                            IF(g.start = g.finish, g.start, CONCAT(g.start, ' ~ ', g.finish)) AS date,
                            g.start, g.reguntil FROM grounds AS g
                            WHERE g.deleted = 0 AND g.type LIKE 'comp:%' AND g.deleted = 0
                            AND g.type LIKE " . vsql::quote($_REQUEST["type"]) .
                           " AND g.reguntil >= DATE(NOW()) ORDER BY g.start");

            foreach($upcoming as $id => $info)
                $upcoming[$id]["usercats"] = insider_signup::user_cats($info["categories"], $info["start"]);

            foreach(vsql::id_retr(array_keys($upcoming),
                "g.id",
                "SELECT COUNT(a.id) AS gcnt, cat.name, g.id AS g_id, cat.id AS cat_id
                        FROM grounds AS g
                        JOIN grounds AS cat ON CONCAT(',', g.categories, ',') LIKE CONCAT('%,', cat.id, ',%') AND cat.deleted = 0
                        LEFT JOIN achievements AS a ON a.role < 100 AND a.deleted = 0 AND a.categ = cat.id AND a.ground = g.id
                        WHERE
                        ", "", " GROUP BY g_id, cat_id ORDER BY cat.name") as $i)
            {
                if(!isset($upcoming[$i["g_id"]]["categs"]))
                    $upcoming[$i["g_id"]]["categs"] = array();
                $upcoming[$i["g_id"]]["categs"][$i["cat_id"]] =  $i;
            }

            $this->S->assign(array("upcoming" => $upcoming,
                                   "mysignups" => vsql::retr("SELECT a.id, a.ground FROM achievements AS a WHERE
                                    a.role < 100 AND a.deleted = 0 AND a.user = " . vsql::quote(access::getuid()), "ground", "id")));
            $this->S->display("insider/upcoming.html");

        }
    }