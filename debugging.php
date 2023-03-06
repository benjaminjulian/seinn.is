<?php
    require("functions.php");
    $db = new PDO('sqlite:gtfs.db');

    function getStopTripsTimed($db, $stop_id, $time) {
        $result = [];
        $now = new DateTime();
        $now_pre_string = $now->format('H:i:s');
        $now->modify("-31 minutes");
        $now_string = getUnmodString($now);
        $now->modify("-5 hours");
        $today_string_early = $now->format('Ymd');

        $preceding_trip = array();

        $service_ids = getServiceIds($db, $today_string_early);
        $q_trips_info = "SELECT trips.route_id, routes.route_short_name, trips.trip_id, trips.trip_headsign, stop_times.arrival_time_mod, stop_times.departure_time_mod, stop_times.stop_sequence FROM stop_times JOIN trips ON stop_times.trip_id = trips.trip_id JOIN routes ON trips.route_id = routes.route_id WHERE stop_times.stop_id = '$stop_id' AND service_id IN ('".implode("','", $service_ids)."') AND stop_times.departure_time > '$now_string' ORDER BY stop_times.arrival_time ASC LIMIT 500";

        $results = $db->query($q_trips_info);
        $done = array();
        foreach ($results as $row) {
            $route_id = $row['route_id'];
            $route_short_name = $row['route_short_name'];
            $trip_id = $row['trip_id'];
            $trip_headsign = $row['trip_headsign'];
            $arrival_time = $row['arrival_time'];
            $departure_time = $row['departure_time'];
            $stop_sequence = $row['stop_sequence'];

            $entry = array(
                'route_id' => $route_id,
                'route_short_name' => $route_short_name,
                'trip_id' => $trip_id,
                'trip_headsign' => $trip_headsign,
                'arrival_time' => $arrival_time,
                'departure_time' => $departure_time,
                'stop_sequence' => $stop_sequence,
                'preceding_trip' => false
            );
            
            if ($departure_time >= $now_pre_string) {
                if (array_key_exists($route_id, $preceding_trip) && $preceding_trip['departure_time'] < $now_pre_string) {
                    $entry['preceding_trip'] = $preceding_trip[$route_id];
                }
                if (!in_array($route_id.$trip_headsign, $done)) {
                    $done[] = $route_id.$trip_headsign;
                    $result[] = $entry;
                }
            }
            $preceding_trip[$route_id] = array(
                'trip_id' => $trip_id,
                'arrival_time' => $arrival_time,
                'departure_time' => $departure_time
            );
        }
        return $result;
    }
    $stop_id = $_GET['stop_id'];
    $time = $_GET['time'];
    $r = getStopTripsTimed($db, $stop_id, $time);
    var_dump($r);
?>