<?php
    require("functions.php");
    $db = new PDO('sqlite:gtfs.db');
    $route = $_GET['route'];
    $stop_sequence = $_GET['stop_sequence'];
    $trip_id = $_GET['trip_id'];
    $r = getClosestSchedulePoint($db, $stop_sequence, $stop_id, $trip_id, $time);
    var_dump($r);
?>