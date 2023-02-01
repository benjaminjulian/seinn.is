<?php

    date_default_timezone_set('UTC');
    function wkday() {
        // Get the current time in UTC
        $now = new DateTime();
        // Subtract four hours from the current time
        $four_hours_ago = $now->sub(new DateInterval('PT4H'));
        // Get the name of the day (Monday, Tuesday, etc.)
        $day_name = $four_hours_ago->format('l');
        // Capitalize the first letter of the day name
        $capitalized_day_name = ucfirst($day_name);
        #return first letter of $capitalized_day_name
        $capitalized_day_name = substr($capitalized_day_name, 0, 1);

        if ($day_name === 'Saturday'){
            $capitalized_day_name =  '-'.$capitalized_day_name;
        }else if ($day_name === 'Sunday') {
            $capitalized_day_name =  $capitalized_day_name.'_';
        }

        return $capitalized_day_name;
    }

    function readBusTime($t) {
        // convert the format YYMMDDHHMMSS to YYYY-MM-DD HH:MM:SS
        $year = substr($t, 0, 2);
        $month = substr($t, 2, 2);
        $day = substr($t, 4, 2);
        $hour = substr($t, 6, 2);
        $minute = substr($t, 8, 2);
        $second = substr($t, 10, 2);
        $time = $year.'-'.$month.'-'.$day.' '.$hour.':'.$minute.':'.$second;
        return $time;
    }

    // get lat and lon parameters
    $lat = $_GET['lat'];
    $lon = $_GET['lon'];
    $stations = $_GET['stations'];
     
    $db = new SQLite3('gtfs.db');
    $q = "SELECT stop_id, stop_name, stop_lat, stop_lon, (stop_lat - $lat)*(stop_lat - $lat) + (stop_lon - $lon)*(stop_lon - $lon) AS distance FROM stops ORDER BY distance LIMIT $stations";
    $results = $db->query($q);

    $result = array('weekday' => wkday());
    
    $count = 1;

    # loop through the nearby stops
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $stop_id = $row['stop_id'];
        $stop_name = $row['stop_name'];
        $stop_lat = $row['stop_lat'];
        $stop_lon = $row['stop_lon'];

        $entry = array(
            'stop_id' => $stop_id,
            'stop_name' => $stop_name,
            'stop_lat' => $stop_lat,
            'stop_lon' => $stop_lon,
            'routes' => array(),
        );

        $weekday = wkday();
        $now = new DateTime();
        $now_string = $now->format('H:i:s');

        $q_trips_info = "SELECT DISTINCT trips.route_id, routes.route_short_name, trips.trip_id, trips.trip_headsign, stop_times.arrival_time, stop_times.departure_time, stop_times.stop_sequence FROM stop_times JOIN trips ON stop_times.trip_id = trips.trip_id JOIN routes ON trips.route_id = routes.route_id WHERE stop_times.stop_id = '$stop_id' AND trips.service_id LIKE '%$weekday%' AND stop_times.arrival_time > '$now_string' GROUP BY trips.route_id ORDER BY stop_times.arrival_time";
        $results_trips_info = $db->query($q_trips_info);

        while ($row_trips_info = $results_trips_info->fetchArray(SQLITE3_ASSOC)) {
            $route_id = $row_trips_info['route_id'];
            $route_short_name = $row_trips_info['route_short_name'];
            $trip_id = $row_trips_info['trip_id'];
            $trip_headsign = $row_trips_info['trip_headsign'];
            $arrival_time = $row_trips_info['arrival_time'];
            $departure_time = $row_trips_info['departure_time'];
            $stop_sequence = $row_trips_info['stop_sequence'];

            $entry["routes"][$route_id] = array(
                "sql" => $q_trips_info,
                "route_short_name" => $route_short_name,
                "trips" => array(),
            );

            $entry["routes"][$route_id]["trips"][$trip_id] = array(
                "trip_headsign" => $trip_headsign,
                "arrival_time" => $arrival_time,
                "departure_time" => $departure_time,
                "stop_sequence" => $stop_sequence,
            );

            $q_buses = "SELECT buses.route, buses.stop, buses.next, buses.time FROM buses JOIN stop_times ON buses.stop = stop_times.stop_id WHERE buses.route = '$route_short' AND stop_times.trip_id = '$trip_id'";
            $entry["routes"][$route_id]['busql'] = $q_buses;
            $results_buses = $db->query($q_buses);

            while ($row_buses = $results_buses->fetchArray(SQLITE3_ASSOC)) {
                $loop_start = microtime(true);
                $route = $row_buses['route'];
                $stop = $row_buses['stop'];
                $next = $row_buses['next'];
                $time = readBusTime($row_buses['time']);

                $entry["routes"][$route_id]['buses'] = array(
                    'route' => $route,
                    'stop' => $stop,
                    'next' => $next,
                    'time' => $time,
                );
                
                $q_next_expected = "SELECT stop_times.arrival_time, stop_times.stop_sequence FROM stop_times WHERE stop_times.stop_id='$next' AND stop_times.trip_id='$trip_id'";
                $entry['q_fail']= $q_next_expected;
                $results_next_expected = $db->query($q_next_expected);
                $row_next_expected = $results_next_expected->fetchArray(SQLITE3_ASSOC);
                $arrival_time = $row_next_expected['arrival_time'];
                $stop_sequence_next = $row_next_expected['stop_sequence'];

                $entry["routes"][$route_id]['buses']['next_schedule'] = array(
                    'arrival_time' => $arrival_time,
                    'stop_sequence' => $stop_sequence_next,
                );
                
                $q_prev_expected = "SELECT stop_times.arrival_time, stop_times.stop_sequence FROM stop_times WHERE stop_times.stop_id='$stop' AND stop_times.trip_id='$trip_id'";
                $entry['q_fail_2']= $q_prev_expected;
                $results_prev_expected = $db->query($q_prev_expected);
                $row_prev_expected = $results_prev_expected->fetchArray(SQLITE3_ASSOC);
                $arrival_time = $row_prev_expected['arrival_time'];
                $stop_sequence_prev = $row_prev_expected['stop_sequence'];

                $entry["routes"][$route_id]['buses']['prev_schedule'] = array(
                    'arrival_time' => $arrival_time,
                    'stop_sequence' => $stop_sequence_prev,
                );
            }
        }
        
        $result["s".$count] = $entry;
        $result['times'] = $times;
        $count++;
    }

    $db->close();

    // return the json object
    echo json_encode($result);

    //need to fix a lot!
?>