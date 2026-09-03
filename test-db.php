<?php

require_once __DIR__ . '/bd.php';


$stmt = $pdo->query("

    SELECT

        s.id,
        s.name,

        MIN(t.price) AS price

    FROM services s

    JOIN tariffs t
        ON t.service_id = s.id

    WHERE
        s.active = 1
        AND t.active = 1

    GROUP BY
        s.id,
        s.name

    ORDER BY s.id

");


$services = $stmt->fetchAll();


echo '<pre>';

print_r($services);

echo '</pre>';
