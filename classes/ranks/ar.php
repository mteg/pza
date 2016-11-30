<?
    class ranks_ar extends rank
    {
        function scores($id)
        {
            $scores = vsql::retr("SELECT
                            a.id, g.start, a.position, a.user, a.points * g.difficulty AS points,
                            g.city, u.ref AS user_name,
                            CONCAT(g.name, ' (', g.difficulty, ') ') AS event_name, g.start AS event_date,
                            g.city AS event_city, g.id AS event_id
                            FROM grounds AS rank
                            JOIN achievements AS a ON a.date >= DATE_ADD(DATE(NOW()), INTERVAL -1 YEAR) AND a.deleted = 0
                              AND CONCAT(',', rank.categories, ',') LIKE CONCAT('%,', a.categ, ',%')
                            JOIN grounds AS g ON a.ground = g.id AND g.deleted = 0
                            JOIN users AS u ON u.id = a.user
                            WHERE rank.id = " . vsql::quote($id) . " ORDER BY g.start");

            return $scores;
        }

    }