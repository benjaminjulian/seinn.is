<?php
    require("functions.php");
    $db = new PDO('sqlite:gtfs.db');
    $now = new DateTime();
    $updated = new DateTime(getUpdateTime($db));
    // get the difference between the current time and the last update time in seconds
    $diff = $now->getTimestamp() - $updated->getTimestamp();

    if ($diff > 11) {
        $t1 = microtime(true);
        $command = escapeshellcmd('python3 scrape.py');
        $output = shell_exec($command);
        $t2 = microtime(true);
        $diff = $t2 - $t1;
    } else {
        $diff = false;
        $output = "stored values used";
    }


    if (isset($_GET['lat']) && isset($_GET['lon']) && isset($_GET['stations'])) {
        if (is_numeric($_GET['lat']) && is_numeric($_GET['lon'])) {
            $lat = (float) $_GET['lat'];
            $lon = (float) $_GET['lon'];
        } else {
            $lat = 64.1353;
            $lon = -21.8952;
        }
        if (is_numeric($_GET['stations'])) {
            $stations = (int) $_GET['stations'];
        } else {
            $stations = 2;
        }
        $stops = getNearestStops($db, $lat, $lon, $stations);
    } else if (isset($_GET['stop'])) {
        $stops = getStopsByName($db, $_GET['stop']);
    }

    $result = $stops;

    foreach ($stops as $stop_num => $stop) {
        $trips = getStopTrips($db, $stop['stop_id']);
        $entries = array();
        $used_trips = array();
        $trips_found = array();

        $now = new DateTime();
        $now_string = $now->format('H:i:s');

        foreach ($trips as $trip) {
            $route_sign = $trip['route_short_name'].$trip['trip_headsign'];
            $rename_trip = $trip['route_short_name'];

            if (substr($trip['route_id'], 0, 3) == 'RY.') {
                $rename_trip = 'R'.$rename_trip;
            } else if (substr($trip['route_id'], 0, 3) == 'SA.') {
                $rename_trip = 'A'.$rename_trip;
            }
            if (!in_array($route_sign, $trips_found)) {
                $nearestBus = getNextBusLocation($db, $rename_trip, $trip['stop_sequence'], $trip['trip_id']);
                $trip['next_bus'] = $nearestBus;
                $entry = $trip;
                $entries[] = $entry;
            } else {
                $trips_found[] = $route_sign;
            }
        }
        $result[$stop_num]['trips'] = $entries;
    }

    $result['previous_scrape'] = $updated->format('Y-m-d H:i:s');
    $result['scrape_duration'] = $diff;
    // set $result['stops'] to the length of the $stops array
    $result['stops'] = count($stops);

    echo json_encode($result, JSON_PRETTY_PRINT);
?>