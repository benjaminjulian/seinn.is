import requests
import zipfile
import sqlite3
import io

# download a zip file and extract it
url = 'https://opendata.straeto.is/data/gtfs/gtfs.zip'
r = requests.get(url)
z = zipfile.ZipFile(io.BytesIO(r.content))
z.extractall()

# import gtfs/stops.txt into a sqlite database
conn = sqlite3.connect('gtfs.db')
c = conn.cursor()

c.execute('CREATE TABLE buses (time, lat, lon, head, fix, route, stop, next, code)')
c.execute('CREATE TABLE logs (table_name, updated)')

c.execute('CREATE TABLE stops (stop_id, stop_name, stop_lat, stop_lon, location_type)')
with open('stops.txt') as f:
    skip = True
    for line in f:
        if not skip:
            c.execute('INSERT INTO stops VALUES (?,?,?,?,?)', line.strip().split(','))
        skip = False

# import gtfs/trips.txt into a sqlite database
c.execute('CREATE TABLE trips (route_id, service_id, trip_id, trip_headsign, trip_short_name, direction_id, block_id, shape_id)')
with open('trips.txt') as f:
    skip = True
    for line in f:
        if not skip:
            c.execute('INSERT INTO trips VALUES (?,?,?,?,?,?,?,?)', line.strip().split(','))
        skip = False

# import gtfs/stop_times.txt into a sqlite database
c.execute('CREATE TABLE stop_times (trip_id, arrival_time, departure_time, stop_id, stop_sequence, stop_headsign, pickup_type)')
with open('stop_times.txt') as f:
    skip = True
    for line in f:
        if not skip:
            c.execute('INSERT INTO stop_times VALUES (?,?,?,?,?,?,?)', line.strip().split(','))
        skip = False

# import gtfs/routes.txt into a sqlite database
c.execute('CREATE TABLE routes (route_id, agency_id, route_short_name, route_long_name, route_type)')
with open('routes.txt') as f:
    skip = True
    for line in f:
        if not skip:
            c.execute('INSERT INTO routes VALUES (?,?,?,?,?)', line.strip().split(','))
        skip = False

conn.commit()
conn.close()