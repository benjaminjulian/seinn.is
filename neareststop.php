<?php
    // get lat and lon parameters
    $lat = $_GET['lat'];
    $lon = $_GET['lon'];
     
    $db = new SQLite3('gtfs.db');
    $q = "SELECT stop_id, stop_name, stop_lat, stop_lon, (stop_lat - $lat)*(stop_lat - $lat) + (stop_lon - $lon)*(stop_lon - $lon) AS distance FROM stops ORDER BY distance LIMIT 3";
    $results = $db->query($q);

    $result = array();
    
    $count = 1;

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
            'routes' => array()
        );

        $q2 = "select trips.route_id from stop_times inner join trips on stop_times.trip_id = trips.trip_id where stop_times.stop_id='$stop_id' group by trips.route_id";
        $results2 = $db->query($q2);

        while ($row2 = $results2->fetchArray(SQLITE3_ASSOC)) {
            $route_id = $row2['route_id'];
            array_push($entry["routes"], $route_id);
        }

        $result["s".$count] = $entry;
        $count++;
    }

    $db->close();

    // return the json object
    echo json_encode($result);
?>