function translatePage() {
    document.getElementById("station").placeholder = txt('Stoppistöð');
    document.getElementById("title-text").innerHTML = txt('Er strætó seinn?');
}

function abort_all_xhr(){
    if (xhrs.length>0) {
        for(var i=0; i<xhrs.length; i++){
            xhrs[i].abort();
        }
        xhrs = [];
    };
}
function distance(lat1, lon1, lat2, lon2) {
    if ((lat1 == lat2) && (lon1 == lon2)) {
        return 0;
    }
    else {
        var radlat1 = Math.PI * lat1/180;
        var radlat2 = Math.PI * lat2/180;
        var theta = lon1-lon2;
        var radtheta = Math.PI * theta/180;
        var dist = Math.sin(radlat1) * Math.sin(radlat2) + Math.cos(radlat1) * Math.cos(radlat2) * Math.cos(radtheta);
        if (dist > 1) {
            dist = 1;
        }
        dist = Math.acos(dist);
        dist = dist * 180/Math.PI;
        dist = dist * 60 * 1.1515 * 1609.344;
        return dist;
    }
}

function formatDist(k) {
    if (k < 1000) {
        return Math.round(k) + " m";
    } else {
        return Math.round(k/100)/10 + " km";
    }
}

function setLoadingPlaceholder() {
    document.getElementById("results").innerHTML = txt('<p class="loading">Sæki gögn...</p>');
}

function fetchByLocation(force=false) {
    document.getElementById("crosshairs").src = "styling/crosshairs.gif";
    document.getElementById("station").value = "";
    byPosition = true;
    byName = false;
    if (position && !force) {
        refreshSearch();
    } else {
        abort_all_xhr();
        setLoadingPlaceholder();
        fetchNearestStop();
    }
}

function fetchByName() {
    station_name = document.getElementById("station").value;
    byPosition = false;
    byName = true;
    abort_all_xhr();
    setLoadingPlaceholder();
    refreshSearch();
}

function fetchNearestStop() {
    byPosition = true;
    byName = false;
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(fetchStopComparison);
        setRefreshTimer();
    } else {
        return 0;
    }
}
function fetchStopComparison(pos) {
    position = pos;
    var xhr = new XMLHttpRequest();
    xhrs.push(xhr);
    xhr.open("GET", "data/?lat=" + pos.coords.latitude + "&lon=" + pos.coords.longitude + "&stations=" + station_count, true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4 && xhr.status == 200) {
            data = JSON.parse(xhr.responseText);
            printStops(data);
        }
    }
    xhr.send();
}
function setStation() {
    var xhr = new XMLHttpRequest();
    xhrs.push(xhr);
    xhr.open("GET", "data/?stop=" + station_name, true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState == 4 && xhr.status == 200) {
            data = JSON.parse(xhr.responseText);
            printStops(data);
        }
    }
    xhr.send();
}
function formatTimeDifference(t, fall='nf') {
    fall_min = {
        'nf': txt('mínútur'),
        'þf': txt('mínútur'),
        'þgf': txt('mínútum'),
        'ef': txt('mínútna'),
    };
    fall_klst = {
        'nf': txt('klukkustundir'),
        'þf': txt('klukkustundir'),
        'þgf': txt('klukkustundum'),
        'ef': txt('klukkustunda'),
    };
    if (t > 60*60) {
        t_str = Math.floor(t/3600)+':'+('0'+Math.floor((t%3600)/60)).slice(-2) + " " + fall_klst[fall];
    } else {
        t_str = Math.floor(t/60)+':'+('0'+(t%60)).slice(-2) + " " + fall_min[fall];
    }

    return t_str;
}
function getTimeDifference(bustime, stoptime) {
    var bus = new Date(bustime);
    var stop = mkDT(bustime.substr(0,11), stoptime);
    if (bus.getHours() == 23 && stop.getHours() < 5) {
        // set stop to the day after
        stop.setDate(stop.getDate() + 1);
    } else if (bus.getHours() < 5 && stop.getHours() == 23) {
        // set stop to the day before
        stop.setDate(stop.getDate() - 1);
    }
    var diff = (bus - stop) / 1000;
    return diff;
}
function getSmallerDiff(d1, d2) {
    if (Math.abs(d1) < Math.abs(d2)) {
        return d1;
    } else {
        return d2;
    }
}
function stopHTML(stop_id) {
    return '<span class="station">' + stops[stop_id] + '</span>';
}
function mkDT(date, time) {
    hr = parseInt(time.substr(0,2));
    date_add = 0;
    if (hr > 23) {
        date_add = 1;
        hr %= 24;
    }
    dt = new Date(date.substr(0,4), date.substr(5,2)-1, date.substr(8,2), hr, time.substr(3,2), time.substr(6,2));
    dt.setDate(dt.getDate() + date_add);
    return dt;
}
function singularPlural(n, singular, plural) {
    if (n % 10 == 1 && n != 11) {
        return n + ' ' + singular;
    } else {
        return n + ' ' + plural;
    }
}
function printStops(data) {
    min_dist = 50;
    time_sensitivity = 20;
    new_stops = [];
    stop_count = data['stops'];
    for (i = 1; i <= stop_count; i++) {
        dontadd = false;
        new_stop = document.createElement('fieldset');
        new_stop.className = "stop";
        new_stop.innerHTML = '<legend>' + data['stop_' + i].stop_name + '</legend>';
        already_printed = [];
        routes = [];
        data['stop_'+i].trips.forEach(function(trip) {
            delay_secs = 0;
            route_rename = trip.route_short_name;
            // if trip.route_id starts with 'RY.' or 'SA.', prefix 'R' or 'A' to route_rename
            if (trip.route_id.substr(0,3) == 'RY.') {
                route_rename = 'R' + route_rename;
            } else if (trip.route_id.substr(0,3) == 'SA.') {
                route_rename = 'A' + route_rename;
            }
            trip_name = '<span class="routenr route-' + route_rename + '">' + route_rename + '</span> ' + trip.trip_headsign;
            if (!already_printed.includes(trip_name)) {
                already_printed.push(trip_name);
                el_trip = document.createElement('div');
                el_trip.className = "trip";
                var dep_str = trip.departure_time;
                bus_distance = -1;
                if (trip.next_bus) {
                    lat1 = 1.0*trip.next_bus.lat;
                    lon1 = 1.0*trip.next_bus.lon;
                    lat2 = 1.0*data['stop_'+i].stop_lat;
                    lon2 = 1.0*data['stop_'+i].stop_lon;
                    bus_distance = distance(lat1,lon1,lat2,lon2);

                    late = false;
                    early = false;
                    next_stop_distance = false;
                    prev_stop_distance = false;
                    next_stop_close = false;
                    prev_stop_close = false;
                    next_stop_same = false;
                    prev_stop_same = false;
                    at_first = false;
                    going_from_here = false;
                    bus_turned_off = (trip.next_bus.code == "4");
                    sequence_diff = false;

                    delay_secs = 0;

                    add_text = '<p class="next-bus">';

                    if (trip.next_bus.next_scheduled) {
                        sequence_diff = trip.stop_sequence - trip.next_bus.next_scheduled.stop_sequence;
                        next_stop_lat = locations[trip.next_bus.next_scheduled.stop_id][0];
                        next_stop_lon = locations[trip.next_bus.next_scheduled.stop_id][1];
                        next_stop_distance = distance(lat1,lon1,next_stop_lat,next_stop_lon);
                        if (trip.preceding_trip) {
                            next_diff = getSmallerDiff(getTimeDifference(trip.next_bus.time, trip.next_bus.next_scheduled.arrival_time), getTimeDifference(trip.next_bus.time, trip.preceding_trip.arrival_time));
                        } else {
                            next_diff = getTimeDifference(trip.next_bus.time, trip.next_bus.next_scheduled.arrival_time);
                        }
                        if (trip.next_bus.next_scheduled.stop_id == data['stop_'+i].stop_id) {
                            next_stop_same = true;
                        }

                        if (next_diff > time_sensitivity) {
                            late = true;
                            delay_secs = next_diff;
                        }

                        if (next_stop_distance < min_dist) {
                            next_stop_close = true;
                        }
                    }
                    if (trip.next_bus.stop_scheduled) {
                        sequence_diff = trip.stop_sequence - trip.next_bus.stop_scheduled.stop_sequence;
                        prev_stop_lat = locations[trip.next_bus.stop_scheduled.stop_id][0];
                        prev_stop_lon = locations[trip.next_bus.stop_scheduled.stop_id][1];
                        prev_stop_distance = distance(lat1,lon1,prev_stop_lat,prev_stop_lon);
                        if (trip.preceding_trip) {
                            prev_diff = getSmallerDiff(getTimeDifference(trip.next_bus.time, trip.next_bus.stop_scheduled.departure_time), getTimeDifference(trip.next_bus.time, trip.preceding_trip.departure_time));
                        } else {
                            prev_diff = getTimeDifference(trip.next_bus.time, trip.next_bus.stop_scheduled.arrival_time);
                        }
                        if (trip.next_bus.stop == data['stop_'+i].stop_id) {
                            prev_stop_same = true;
                        }

                        if (trip.next_bus.stop_scheduled.stop_sequence == "1") {
                            at_first = true;
                        }

                        if (prev_diff < -time_sensitivity) {
                            early = true;
                        }

                        if (prev_stop_distance < min_dist) {
                            prev_stop_close = true;
                        }
                    }

                    if (next_stop_same) {
                        if (next_stop_close) {
                            add_text += txt('Strætó er kominn. ');
                        } else {
                            if (at_first) {
                                if (bus_turned_off) {
                                    add_text += txt('Strætóinn er í bið við upphaf leiðarinnar. ');
                                } else {
                                    add_text += txt('Strætóinn er á fyrsta stoppi leiðarinnar. ');
                                }
                            } else {
                                add_text += txt('Næsta stopp vagnsins er hér. ');
                            }
                        }
                    } else if (prev_stop_same) {
                        if (bus_turned_off) {
                            add_text += txt('Strætó er í bið hérna. ');
                        } else {
                            if (prev_stop_close) {
                                add_text += txt('Strætó er að leggja af stað héðan. ');
                            } else {
                                add_text += txt('Strætó er á leiðinni héðan á stoppið ') + stopHTML(trip.next_bus.next) + '. ';
                                going_from_here = true;
                            }
                        }
                    } else {
                        if (next_stop_close || bus_turned_off) {
                            if (at_first) {
                                add_text += txt('Strætóinn er á fyrsta stoppi leiðarinnar, ');
                            } else {
                                add_text += txt('Strætóinn er á stoppinu ');
                            }
                            add_text += stopHTML(trip.next_bus.stop);
                        } else if (prev_stop_close || bus_turned_off) {
                            add_text += txt('Strætóinn er á stoppinu ') + stopHTML(trip.next_bus.stop);
                        } else if (trip.next_bus.next_scheduled) {
                            add_text += txt('Strætóinn er á leiðinni á stoppið ') + stopHTML(trip.next_bus.next);
                        } else {
                            add_text += txt('Strætóinn er á leiðinni frá stoppinu ') + stopHTML(trip.next_bus.stop);
                        }

                        if (sequence_diff >= 1) {
                            add_text += ', ' + singularPlural(sequence_diff, txt('stoppi'), txt('stoppum')) + txt(' frá. ');
                        } else {
                            add_text += '. ';
                        }
                    }

                    if (!bus_turned_off) {
                        if (late && next_diff < 1800) {
                            add_text += txt('Hann er <span class="time">') + formatTimeDifference(next_diff, 'þgf') + txt('</span> á eftir áætlun. ');
                        } else if (early && !at_first) {
                            add_text += txt('Hann er <span class="time">') + formatTimeDifference(-prev_diff, 'þgf') + txt('</span> á undan áætlun. ');
                        }
                    } else {
                        add_text += txt('(Það er slökkt á honum.) ');
                    }
                    

                    add_text += '</p>';
                } else {
                    add_text = txt('<p class="next-bus">Strætó er ekki lagður af stað.</p>');
                }

                if (trip.preceding_trip) {
                    var now = new Date();
                    var datestring = now.getFullYear() + '-' + ('0'+(now.getMonth()+1)).slice(-2) + '-' + ('0'+now.getDate()).slice(-2);
                    var departure = mkDT(datestring, trip.departure_time);
                    var prev_departure = mkDT(datestring, trip.preceding_trip.departure_time);
                    if (prev_departure / 1000 + delay_secs + 30 > now/1000) {
                        dep_str = trip.preceding_trip.departure_time;
                    }
                }

                if (bus_distance >= 0) {
                    dist_text = ' (' + formatDist(bus_distance) + ')';
                } else {
                    dist_text = '';
                }
                
                el_trip.innerHTML = '<div class="trip-descriptor" data-sorter="' + dep_str + '"><h4 class="trip-name">' + trip_name + '<span class="distance" id="trip' + trip.trip_id + '">' + dist_text + '</span></h4>' +
                                    '<span class="scheduled-departure" data-departure="' + dep_str + '" delay="' + delay_secs + '"></span></div>';
                el_trip.innerHTML += add_text;
                
                if (!dontadd)
                    routes.push([dep_str, el_trip]);
            }
        });
        sorted_routes = routes.sort(function(a, b) {
            return a[0] > b[0];
        });
        sorted_routes.forEach(function(route) {
            new_stop.appendChild(route[1]);
        });

        new_stops.push(new_stop);
    }
    document.getElementById("results").innerHTML = "";
    // loop through new_stops and append elements to results
    for (i = 0; i < new_stops.length; i++) {
        document.getElementById("results").appendChild(new_stops[i]);
    }
    countdown(false);
    document.getElementById("crosshairs").src = "styling/crosshairs.png";
    setRefreshTimer();
}
function formatHMS(t) {
    var hms = t.split(':');
    if (hms[0] > '23') {
        hms[0] = ''+(hms[0]-24);
    }
    return parseInt(hms[0]).toString() + ':' + hms[1];
}
function countdown(loop=true) {
    var now = new Date();
    var scheduled = document.getElementsByClassName('scheduled-departure');
    for (i = 0; i < scheduled.length; i++) {
        const fiveHoursAgo = new Date(now.getTime() - (5 * 60 * 60 * 1000)); // subtract 5 hours in milliseconds
        datestringFHA = fiveHoursAgo.getFullYear() + '-' + ('0'+(fiveHoursAgo.getMonth()+1)).slice(-2) + '-' + ('0'+fiveHoursAgo.getDate()).slice(-2);
        datestring = now.getFullYear() + '-' + ('0'+(now.getMonth()+1)).slice(-2) + '-' + ('0'+now.getDate()).slice(-2);
        if (now.getHours() < 5) {
            var departure = mkDT(datestringFHA, scheduled[i].getAttribute('data-departure'));
        } else {
            var departure = mkDT(datestring, scheduled[i].getAttribute('data-departure'));
        }
        
        var diff = Math.round((departure - now) / 1000);
        if (diff > 0) {
            scheduled[i].innerHTML = formatTimeDifference(diff) + txt(" í áætlaða brottför kl. ") + formatHMS(scheduled[i].getAttribute('data-departure'));
        } else {
            scheduled[i].innerHTML = txt('Áætluð brottför var kl. ') + formatHMS(scheduled[i].getAttribute('data-departure')) + '.';
        }
    }
    if (loop)
        setTimeout(countdown, 1000);
}
function setRefreshTimer() {
    clearTimeout(refreshHandle);
    refreshHandle = setTimeout(refreshSearch, 10000);
}
function refreshSearch() {
    if (!freeze) {
        if (byPosition) {
            fetchStopComparison(position);
        }
        if (byName) {
            setStation();
        }
        setRefreshTimer();
    }
}