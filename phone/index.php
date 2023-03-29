<?php
    require('credentials.php');

    date_default_timezone_set('UTC');

    function singularPlural($n, $singular, $plural) {
        if ($n % 10 == 1 && $n != 11) {
            return $n . ' ' . $singular;
        } else {
            return $n . ' ' . $plural;
        }
    }    

    function getTimeDifference($bustime, $stoptime) {
        $bus = new DateTime($bustime);
        $stop = mkDT(substr($bustime, 0, 11), $stoptime);
        
        if ($bus->format('G') == 23 && $stop->format('G') < 5) {
            // set stop to the day after
            $stop->modify('+1 day');
        } else if ($bus->format('G') < 5 && $stop->format('G') == 23) {
            // set stop to the day before
            $stop->modify('-1 day');
        }
        
        $diff = $bus->getTimestamp() - $stop->getTimestamp();
        return $diff;
    }
    
    function mkDT($date, $time) {
        $datetime = new DateTime($date . $time);
        return $datetime;
    }
    
    function getSmallerDiff($d1, $d2) {
        if (abs($d1) < abs($d2)) {
            return $d1;
        } else {
            return $d2;
        }
    }

    function formatTimeDifference($t, $fall = 'nf') {
        $fall_min = [
            'nf' => 'mín',
            'þf' => 'mín',
            'þgf' => 'mín',
            'ef' => 'mín',
        ];
        $fall_klst = [
            'nf' => 'klst',
            'þf' => 'klst',
            'þgf' => 'klst',
            'ef' => 'klst',
        ];
        if ($t > 60 * 60) {
            $t_str = floor($t / 3600) . ':' . str_pad(floor(($t % 3600) / 60), 2, '0', STR_PAD_LEFT) . " " . $fall_klst[$fall];
        } else {
            $t_str = floor($t / 60) . ':' . str_pad($t % 60, 2, '0', STR_PAD_LEFT) . " " . $fall_min[$fall];
        }
    
        return $t_str;
    }    
    
    function processTrips($data) {
        $already_printed = array();
        $time_sensitivity = 20;
        $routes = "";
        $prefix = "";
        for ($i = 1; $i <= 2; $i++) {
            $trips = $data['stop_' . $i]['trips'];
            if ($routes !== "")
                $routes .= "---\n";
            $routes .= $data['stop_1']['stop_name'] . "\n\n";
            foreach ($trips as $trip) {
                $add_text = "";
                $route_rename = $trip['route_short_name'];
                
                if (substr($trip['route_id'], 0, 3) == 'RY.') {
                    $route_rename = 'R' . $route_rename;
                } elseif (substr($trip['route_id'], 0, 3) == 'SA.') {
                    $route_rename = 'A' . $route_rename;
                }
                
                $trip_name = $route_rename . ' ' . $trip['trip_headsign'];
                
                if (!in_array($trip_name, $already_printed)) {
                    array_push($already_printed, $trip_name);

                    if ($trip['next_bus']) {
                        $late = false;
                        $early = false;
                        $next_stop_same = false;
                        $prev_stop_same = false;
                        $at_first = false;
                        $bus_turned_off = ($trip['next_bus']['code'] == "4");
                        $sequence_diff = false;
                    
                        if (isset($trip['next_bus']['next_scheduled'])) {
                            $sequence_diff = $trip['stop_sequence'] - $trip['next_bus']['next_scheduled']['stop_sequence'];

                            if (isset($trip['preceding_trip'])) {
                                $next_diff = getSmallerDiff(
                                    getTimeDifference($trip['next_bus']['time'], $trip['next_bus']['next_scheduled']['arrival_time']),
                                    getTimeDifference($trip['next_bus']['time'], $trip['preceding_trip']['arrival_time'])
                                );
                            } else {
                                $next_diff = getTimeDifference($trip['next_bus']['time'], $trip['next_bus']['next_scheduled']['arrival_time']);
                            }
                            
                            if ($trip['next_bus']['next_scheduled']['stop_id'] == $data['stop_' . $i]['stop_id']) {
                                $next_stop_same = true;
                            }
                            
                            if ($next_diff > $time_sensitivity) {
                                $late = true;
                            }
                        }
                        if (isset($trip['next_bus']['stop_scheduled'])) {
                            $sequence_diff = $trip['stop_sequence'] - $trip['next_bus']['stop_scheduled']['stop_sequence'];
                        
                            if (isset($trip['preceding_trip'])) {
                                $prev_diff = getSmallerDiff(getTimeDifference($trip['next_bus']['time'], $trip['next_bus']['stop_scheduled']['departure_time']), getTimeDifference($trip['next_bus']['time'], $trip['preceding_trip']['departure_time']));
                            } else {
                                $prev_diff = getTimeDifference($trip['next_bus']['time'], $trip['next_bus']['stop_scheduled']['arrival_time']);
                            }
                        
                            if ($trip['next_bus']['stop'] == $data['stop_' . $i]['stop_id']) {
                                $prev_stop_same = true;
                            }
                        
                            if ($trip['next_bus']['stop_scheduled']['stop_sequence'] == "1") {
                                $at_first = true;
                            }
                        
                            if ($prev_diff < -$time_sensitivity) {
                                $early = true;
                            }
                        }

                        if ($next_stop_same) {
                            if ($at_first) {
                                if ($bus_turned_off) {
                                    $add_text .= 'Ekki lagður af stað. ';
                                } else {
                                    $add_text .= 'Ekki lagður af stað. ';
                                }
                            } else {
                                $add_text .= 'Alveg að koma / kominn. ';
                            }
                        } else if ($prev_stop_same) {
                            if ($bus_turned_off) {
                                $add_text .= 'Kominn hingað. ';
                            } else {
                                $add_text .= 'Að fara héðan. ';
                            }
                        } else {
                            if ($sequence_diff >= 1) {
                                $add_text .= singularPlural($sequence_diff, 'stoppi', 'stoppum') . ' frá. ';
                            }
                        }
                        
                        if (!$bus_turned_off) {
                            if ($late && $next_diff < 1700) {
                                $add_text .= formatTimeDifference($next_diff, 'þgf') . ' á eftir áætlun. ';
                            } else if ($early && !$at_first) {
                                $add_text .= formatTimeDifference(-$prev_diff, 'þgf') . ' á undan áætlun. ';
                            }
                        }
                    } else {
                        $add_text = 'Ekki lagður af stað.';
                    }
                }
                if ($add_text !== "") {
                    $add_text = $trip_name . ": " . $add_text;
                    $routes .= $add_text . "\n";
                }
            }
        }
        return $routes;
    }


    $address = $_POST['Body'];
    $phone = $_POST['From'];

    $url = "https://maps.googleapis.com/maps/api/place/findplacefromtext/json?fields=formatted_address%2Cgeometry&input=".urlencode($address)."&inputtype=textquery&key=".$maps_key;

    $response = file_get_contents($url);
    $data = json_decode($response, true);

    $lat = $data['candidates'][0]['geometry']['location']['lat'];
    $lng = $data['candidates'][0]['geometry']['location']['lng'];

    $url = "http://localhost/data/index.php?lat=".$lat."&lon=".$lng."&stations=2";
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    $output = processTrips($data);

    var_dump($response);
    
    $url = 'https://api.twilio.com/2010-04-01/Accounts/AC0dc87a45fdb74445130e147535c770da/Messages.json';
    $account_sid = 'AC0dc87a45fdb74445130e147535c770da';
    $auth_token = $twilio_key;

    $data = [
        'To' => $phone,
        'MessagingServiceSid' => 'MGec67921118f08d8e27c4359e78af6e99',
        'Body' => $output,
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_USERPWD, $account_sid . ':' . $auth_token);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
    ]);

    $response = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    var_dump($response);
?>