import requests
import zipfile
import sqlite3
import io
import os

# download a zip file and extract it
url = 'https://opendata.straeto.is/data/gtfs/gtfs.zip'
r = requests.get(url)
z = zipfile.ZipFile(io.BytesIO(r.content))
extracted = z.namelist()
z.extractall()

if (os.path.exists('gtfs.db')):
    os.remove('gtfs.db')
if (os.path.exists('stops.js')):
    os.remove('stops.js')
if (os.path.exists('names.js')):
    os.remove('names.js')

# import gtfs/stops.txt into a sqlite database
conn = sqlite3.connect('gtfs.db')
c = conn.cursor()

c.execute('CREATE TABLE buses (time, lat, lon, head, fix, route, stop, next, code)')
c.execute('CREATE TABLE logs (table_name, updated)')

c.execute('CREATE TABLE stops (stop_id, stop_name, stop_lat, stop_lon, location_type)')
with open('stops.txt') as f:
    with open('stops.js', 'a') as j:
        names_used = []
        with open('names.js', 'a') as n:
            skip = True
            for line in f:
                if skip:
                    skip = False
                    j.write('var stops = {')
                    n.write('var names = [')
                else:
                    j.write('"' + line.strip().split(',')[0] + '": "' + line.strip().split(',')[1] + '",')
                    if not line.strip().split(',')[1] in names_used:
                        n.write('"' + line.strip().split(',')[1] + '",');
                        names_used.append(line.strip().split(',')[1])
                    c.execute('INSERT INTO stops VALUES (?,?,?,?,?)', line.strip().split(','))
            n.write('];')
        j.write('};')

# import gtfs/trips.txt into a sqlite database
c.execute('CREATE TABLE trips (route_id, service_id, trip_id, trip_headsign, trip_short_name, direction_id, block_id, shape_id)')
with open('trips.txt') as f:
    skip = True
    for line in f:
        if not skip:
            c.execute('INSERT INTO trips VALUES (?,?,?,?,?,?,?,?)', line.strip().split(','))
        skip = False

# import gtfs/stop_times.txt into a sqlite database
c.execute('CREATE TABLE stop_times (trip_id, arrival_time, departure_time, stop_id, stop_sequence INT, stop_headsign, pickup_type)')
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

for e in extracted:
    os.remove(e)

# set write permissions on the gtfs.db file
os.chmod('gtfs.db', 0o666)
#set write permissions on the directory
os.chmod('.', 0o777)