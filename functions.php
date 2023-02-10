<?php
    date_default_timezone_set('UTC');

    function wkday() {
        // Get the current time in UTC
        $now = new DateTime();
        // Subtract four hours from the current time
        $four_hours_ago = $now->sub(new DateInterval('PT5H'));
        // Get the name of the day (Monday, Tuesday, etc.)
        $day_name = $four_hours_ago->format('l');
        // Capitalize the first letter of the day name
        $capitalized_day_name = ucfirst($day_name);
        #return first letter of $capitalized_day_name
        $capitalized_day_name = substr($capitalized_day_name, 0, 1);

        if ($day_name === 'Saturday'){
            $capitalized_day_name =  '-'.$capitalized_day_name.'-';
        }else if ($day_name === 'Sunday') {
            $capitalized_day_name =  '------'.$capitalized_day_name;
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
        $time = '20'.$year.'-'.$month.'-'.$day.' '.$hour.':'.$minute.':'.$second;
        return $time;
    }

    function getNearestStops($db, $lat, $lon, $station_count=2) {
        $stations = (int) $station_count;
        $q = "SELECT stop_id, stop_name, stop_lat, stop_lon, (stop_lat - $lat)*(stop_lat - $lat) + (stop_lon - $lon)*(stop_lon - $lon) AS distance FROM stops ORDER BY distance LIMIT $stations";
        $results = $db->query($q);
        
        $count = 1;

        $result = array();
    
        # loop through the nearby stops
        foreach ($results as $row) {
            $stop_id = $row['stop_id'];
            $stop_name = $row['stop_name'];
            $stop_lat = $row['stop_lat'];
            $stop_lon = $row['stop_lon'];
            $distance = $row['distance'];
    
            $entry = array(
                'stop_name' => $stop_name,
                'stop_id' => $stop_id,
                'stop_lat' => $stop_lat,
                'stop_lon' => $stop_lon
            );

            $result["stop_$count"] = $entry;

            $count++;
        }
        return $result;
    }

    function getStopsByName($db, $name) {
        $q = $db->prepare("SELECT stop_id, stop_name, stop_lat, stop_lon FROM stops WHERE stop_name = :name");
        $q->execute([':name' => $name]);
        $result = array();
        $results = $q->fetchAll(PDO::FETCH_ASSOC);

        $count = 1;

        foreach ($results as $row) {
            $stop_id = $row['stop_id'];
            $stop_name = $row['stop_name'];
            $stop_lat = $row['stop_lat'];
            $stop_lon = $row['stop_lon'];
            $entry = array(
                'stop_id' => $stop_id,
                'stop_name' => $stop_name,
                'stop_lat' => $stop_lat,
                'stop_lon' => $stop_lon
            );
            $result['stop_'.$count] = $entry;

            $count++;
        }
        return $result;
    }

    function getStopTrips($db, $stop_id) {
        $result = [];
        $weekday = wkday();
        $now = new DateTime();
        $now_pre_string = $now->format('H:i:s');
        $now->modify("-31 minutes");
        $now_string = $now->format('H:i:s');
        $today_string = $now->format('Ymd');

        $preceding_trip = array();
        
        $q_trips_info = "SELECT trips.route_id, routes.route_short_name, trips.trip_id, trips.trip_headsign, stop_times.arrival_time, stop_times.departure_time, stop_times.stop_sequence FROM stop_times JOIN trips ON stop_times.trip_id = trips.trip_id JOIN routes ON trips.route_id = routes.route_id WHERE stop_times.stop_id = '$stop_id' AND trips.service_id LIKE '%$weekday%' AND stop_times.departure_time > '$now_string' AND trips.service_id <= '$today_string' ORDER BY trips.service_id DESC, stop_times.arrival_time ASC LIMIT 50";
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

    function getPrecedingTrip($db, $stop_sequence, $trip_id) {
        $q = "SELECT arrival_time, departure_time, stop_id, stop_sequence FROM stop_times WHERE stop_sequence <= $stop_sequence AND trip_id = '$trip_id' ORDER BY stop_sequence DESC";
        $results = $db->query($q);
        $result = array();
        foreach ($results as $row) {
            $arrival_time = $row['arrival_time'];
            $departure_time = $row['departure_time'];
            $stop_id = $row['stop_id'];
            $stop_sequence = $row['stop_sequence'];
            $entry = array(
                'arrival_time' => $arrival_time,
                'departure_time' => $departure_time,
                'stop_id' => $stop_id,
                'stop_sequence' => $stop_sequence
            );
            $result[$stop_id] = $entry;
        }
        return $result;
    }

    function getRouteOtherTrips($db, $trip) {
        $q = "SELECT service_id, route_id, trip_id FROM trips WHERE trip_id = '$trip'";
        $results = $db->query($q);
        $result = array();
        foreach ($results as $row) {
            $service_id = $row['service_id'];
            $route_id = $row['route_id'];
        }
        $q = "SELECT trip_id FROM trips WHERE service_id = '$service_id' AND route_id = '$route_id'";
        $results = $db->query($q);
        foreach ($results as $row) {
            $trip_id = $row['trip_id'];
            $result[] = $trip_id;
        }
        return $result;
    }

    function getClosestSchedulePoint($db, $stop_sequence, $stop_id, $trip_id, $time) {
        $all_trips = getRouteOtherTrips($db, $trip_id);

        $t = substr($time, 0, 11);
        $q = "SELECT arrival_time, departure_time, ABS(strftime('%s', '$time') - strftime('%s', '$t' || departure_time)) as diff FROM stop_times WHERE stop_id = '$stop_id' AND trip_id IN ('".implode("','", $all_trips)."') AND stop_sequence = $stop_sequence ORDER BY diff ASC LIMIT 1";

        $results = $db->query($q);
        $result = array();
        $entry = false;
        foreach ($results as $row) {
            $arrival_time = $row['arrival_time'];
            $departure_time = $row['departure_time'];
            $entry = array(
                'stop_id' => $stop_id,
                'stop_sequence' => $stop_sequence,
                'arrival_time' => $arrival_time,
                'departure_time' => $departure_time,
            );
        }
        return $entry;
    }

    function getNextBusLocation($db, $route, $stop_sequence, $trip_id) {
        $previous_stops = getPrecedingTrip($db, $stop_sequence, $trip_id);

        $q = "SELECT buses.time, lat, lon, buses.stop, buses.next FROM buses WHERE buses.route = '$route'";
        $results = $db->query($q);
        $result = false;
        $possible_results = array();
        foreach ($results as $row) {
            if (array_key_exists($row['stop'], $previous_stops) || array_key_exists($row['next'], $previous_stops)) {
                $time = $row['time'];
                $lat = $row['lat'];
                $lon = $row['lon'];
                $stop = $row['stop'];
                $next = $row['next'];
                $prev_schedule = false;
                $next_schedule = false;

                if (array_key_exists($stop, $previous_stops)) {
                    $prev_schedule = $previous_stops[$stop];
                }
                if (array_key_exists($next, $previous_stops)) {
                    $next_schedule = $previous_stops[$next];
                }

                $entry = array(
                    'time' => $time,
                    'lat' => $lat,
                    'lon' => $lon,
                    'stop' => $stop,
                    'next' => $next,
                    "stop_scheduled" => $prev_schedule,
                    "next_scheduled" => $next_schedule
                );
                $possible_results[] = $entry;
            }
        }
        $top_sequence_id = 0;
        $top_sequence_index = -1;
        for ($i = 0; $i < count($possible_results); $i++) {
            if ($possible_results[$i]['stop_scheduled']) {
                if ($possible_results[$i]['stop_scheduled']['stop_sequence'] > $top_sequence_id) {
                    $top_sequence_id = $possible_results[$i]['stop_scheduled']['stop_sequence'];
                    $top_sequence_index = $i;
                }
            }
            if ($possible_results[$i]['next_scheduled']) {
                if ($possible_results[$i]['next_scheduled']['stop_sequence'] > $top_sequence_id) {
                    $top_sequence_id = $possible_results[$i]['next_scheduled']['stop_sequence'];
                    $top_sequence_index = $i;
                }
            }
        }
        if ($top_sequence_index != -1) {
            if ($possible_results[$top_sequence_index]['next']){
                $possible_results[$top_sequence_index]['next_scheduled'] = getClosestSchedulePoint($db, $top_sequence_id, $possible_results[$top_sequence_index]['next'], $trip_id, $possible_results[$top_sequence_index]['time']);
            } else if ($possible_results[$top_sequence_index]['stop']){
                $possible_results[$top_sequence_index]['stop_scheduled'] = getClosestSchedulePoint($db, $top_sequence_id, $possible_results[$top_sequence_index]['stop'], $trip_id, $possible_results[$top_sequence_index]['time']);
            }
            return $possible_results[$top_sequence_index];
        } else {
            return false;
        }
    }

    function getUpdateTime($db) {
        $q = "SELECT updated FROM logs WHERE table_name='buses' ORDER BY updated DESC LIMIT 1";
        $results = $db->query($q);
        $time = '2000-01-01 00:00:00';
        foreach ($results as $row) {
            $time = $row['updated'] ?? false;
        }
        return $time;
    }