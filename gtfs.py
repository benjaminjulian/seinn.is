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

print('Removing old files...')
if (os.path.exists('gtfs.db')):
    os.remove('gtfs.db')
if (os.path.exists('stops.js')):
    os.remove('stops.js')
if (os.path.exists('names.js')):
    os.remove('names.js')

print('Connecting to database...')
# import gtfs/stops.txt into a sqlite database
conn = sqlite3.connect('gtfs.db')
c = conn.cursor()

print('Creating tables...')
c.execute('CREATE TABLE buses (time TEXT, lat TEXT, lon TEXT, head TEXT, fix INTEGER, route TEXT, stop INTEGER, next INTEGER, code TEXT)')
c.execute('CREATE TABLE logs (table_name, updated)')

c.execute('CREATE TABLE stops (stop_id INTEGER, stop_name TEXT, stop_lat TEXT, stop_lon TEXT, location_type TEXT)')
c.execute('CREATE INDEX idx_stops ON stops (stop_id, stop_name, stop_lat, stop_lon)')
with open('stops.txt') as f:
    with open('stops.js', 'a') as j:
        names_used = []
        with open('names.js', 'a') as n:
            with open('locations.js', 'a') as l:
                skip = True
                for line in f:
                    if skip:
                        skip = False
                        j.write('var stops = {')
                        n.write('var names = [')
                        l.write('var locations = {')
                    else:
                        j.write('"' + line.strip().split(',')[0] + '": "' + line.strip().split(',')[1] + '",')
                        l.write('"' + line.strip().split(',')[0] + '": [' + line.strip().split(',')[2] + ', ' + line.strip().split(',')[3] + '],')
                        if not line.strip().split(',')[1] in names_used:
                            n.write('"' + line.strip().split(',')[1] + '",');
                            names_used.append(line.strip().split(',')[1])
                        c.execute('INSERT INTO stops VALUES (?,?,?,?,?)', line.strip().split(','))
                l.write('}')
            n.write('];')
        j.write('};')

# import gtfs/trips.txt into a sqlite database
c.execute('CREATE TABLE trips (route_id TEXT, service_id TEXT, trip_id INTEGER, trip_headsign TEXT, trip_short_name TEXT, direction_id TEXT, block_id TEXT, shape_id TEXT)')
c.execute('CREATE INDEX idx_trips ON trips (trip_id, route_id, service_id)')
with open('trips.txt') as f:
    skip = True
    for line in f:
        if not skip:
            c.execute('INSERT INTO trips VALUES (?,?,?,?,?,?,?,?)', line.strip().split(','))
        skip = False

# import gtfs/stop_times.txt into a sqlite database
c.execute('CREATE TABLE stop_times (trip_id INTEGER, arrival_time TEXT, departure_time TEXT, stop_id INTEGER, stop_sequence INTEGER, stop_headsign TEXT, pickup_type TEXT, arrival_time_mod TEXT, departure_time_mod TEXT)')
c.execute('CREATE INDEX idx_times ON stop_times (trip_id, arrival_time, departure_time, stop_id, stop_sequence, arrival_time_mod, departure_time_mod)')
with open('stop_times.txt') as f:
    skip = True
    for line in f:
        if not skip:
            insert = line.strip().split(',')
            insert.append(insert[1])
            insert.append(insert[2])
            c.execute('INSERT INTO stop_times VALUES (?,?,?,?,?,?,?,?,?)', insert)
        skip = False

# import gtfs/routes.txt into a sqlite database
c.execute('CREATE TABLE routes (route_id TEXT, agency_id TEXT, route_short_name TEXT, route_long_name TEXT, route_type TEXT)')
c.execute('CREATE INDEX idx_routes ON routes (route_id, route_short_name, route_long_name)')
with open('routes.txt') as f:
    skip = True
    for line in f:
        if not skip:
            c.execute('INSERT INTO routes VALUES (?,?,?,?,?)', line.strip().split(','))
        skip = False

# import gtfs/calendar_dates.txt into a sqlite database
c.execute('CREATE TABLE calendar_dates (service_id TEXT, date TEXT, exception_type TEXT)')
c.execute('CREATE INDEX idx_dates ON calendar_dates (service_id, date)')
with open('calendar_dates.txt') as f:
    skip = True
    for line in f:
        if not skip:
            c.execute('INSERT INTO calendar_dates VALUES (?,?,?)', line.strip().split(','))
        skip = False

# cleanup

print('Rewriting some data...')
c.execute('UPDATE stop_times SET arrival_time_mod = CASE WHEN substr(arrival_time, 0, 3) >= "24" THEN "0" || CAST((CAST(substr(arrival_time, 0, 3) AS INTEGER) - 24) AS TEXT) || substr(arrival_time, 3) ELSE arrival_time END;')
c.execute('UPDATE stop_times SET departure_time_mod = CASE WHEN substr(departure_time, 0, 3) >= "24" THEN "0" || CAST((CAST(substr(departure_time, 0, 3) AS INTEGER) - 24) AS TEXT) || substr(departure_time, 3) ELSE departure_time END;')

conn.commit()
conn.close()

print('Cleaning up...')
for e in extracted:
    os.remove(e)

# set write permissions on the gtfs.db file
os.chmod('gtfs.db', 0o666)
#set write permissions on the directory
os.chmod('.', 0o777)

print('Done.')