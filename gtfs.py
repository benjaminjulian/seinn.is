import requests
import zipfile
import sqlite3
import io
import os
import csv

# download a zip file and extract it
url = 'https://opendata.straeto.is/data/gtfs/gtfs.zip'
r = requests.get(url)
print('fetched gtfs.zip, status', r.status_code)
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
if (os.path.exists('locations.js')):
    os.remove('locations.js')
if (os.path.exists('names.js')):
    os.remove('names.js')
if (os.path.exists('stops.js')):
    os.remove('stops.js')

print('Connecting to database...')
# import gtfs/stops.txt into a sqlite database
conn = sqlite3.connect('gtfs.db')
c = conn.cursor()

print('Creating tables...')
c.execute('CREATE TABLE buses (time TEXT, lat TEXT, lon TEXT, head TEXT, fix INTEGER, route TEXT, stop INTEGER, next INTEGER, code TEXT)')
c.execute('CREATE TABLE logs (table_name, updated)')

c.execute('CREATE TABLE stops (stop_id INTEGER, stop_name TEXT, stop_lat TEXT, stop_lon TEXT, location_type TEXT)')
c.execute('CREATE INDEX idx_stops ON stops (stop_id, stop_name, stop_lat, stop_lon)')
with open('stops.txt') as f_txt:
    f = csv.DictReader(f_txt)
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
                    j.write('"' + line['stop_id'] + '": "' + line['stop_name'] + '",')
                    l.write('"' + line['stop_id'] + '": [' + line['stop_lat'] + ', ' + line['stop_lon'] + '],')
                    if not line['stop_name'] in names_used:
                        n.write('"' + line['stop_name'] + '",')
                        names_used.append(line['stop_name'])
                    c.execute('INSERT INTO stops VALUES (?,?,?,?,?)', (line['stop_id'], line['stop_name'], line['stop_lat'], line['stop_lon'], line['location_type'],))
                l.write('}')
            n.write('];')
        j.write('};')

# import gtfs/trips.txt into a sqlite database
c.execute('CREATE TABLE trips (route_id TEXT, service_id TEXT, trip_id INTEGER, trip_headsign TEXT, trip_short_name TEXT, direction_id TEXT, block_id TEXT, shape_id TEXT)')
c.execute('CREATE INDEX idx_trips ON trips (trip_id, route_id, service_id)')
with open('trips.txt') as f_txt:
    f = csv.DictReader(f_txt)
    for line in f:
        c.execute('INSERT INTO trips VALUES (?,?,?,?,?,?,?,?)', (line['route_id'], line['service_id'], line['trip_id'], line['trip_headsign'], line['trip_short_name'], line['direction_id'], line['block_id'], line['shape_id'],))

# import gtfs/stop_times.txt into a sqlite database
c.execute('CREATE TABLE stop_times (trip_id INTEGER, arrival_time TEXT, departure_time TEXT, stop_id INTEGER, stop_sequence INTEGER, stop_headsign TEXT, pickup_type TEXT, arrival_time_mod TEXT, departure_time_mod TEXT)')
c.execute('CREATE INDEX idx_times ON stop_times (trip_id, arrival_time, departure_time, stop_id, stop_sequence, arrival_time_mod, departure_time_mod)')
with open('stop_times.txt') as f_txt:
    f = csv.DictReader(f_txt)
    for line in f:
        insert = (line['trip_id'], line['arrival_time'], line['departure_time'], line['stop_id'], line['stop_sequence'], line['stop_headsign'], line['pickup_type'], line['arrival_time'], line['departure_time'],)
        c.execute('INSERT INTO stop_times VALUES (?,?,?,?,?,?,?,?,?)', insert)

# import gtfs/routes.txt into a sqlite database
c.execute('CREATE TABLE routes (route_id TEXT, agency_id TEXT, route_short_name TEXT, route_long_name TEXT, route_type TEXT)')
c.execute('CREATE INDEX idx_routes ON routes (route_id, route_short_name, route_long_name)')
with open('routes.txt') as f_txt:
    f = csv.DictReader(f_txt)
    for line in f:
        c.execute('INSERT INTO routes VALUES (?,?,?,?,?)', (line['route_id'], line['agency_id'], line['route_short_name'], line['route_long_name'], line['route_type'],))

# import gtfs/calendar_dates.txt into a sqlite database
c.execute('CREATE TABLE calendar_dates (service_id TEXT, date TEXT, exception_type TEXT)')
c.execute('CREATE INDEX idx_dates ON calendar_dates (service_id, date)')
with open('calendar_dates.txt') as f_txt:
    f = csv.DictReader(f_txt)
    for line in f:
        c.execute('INSERT INTO calendar_dates VALUES (?,?,?)', (line['service_id'], line['date'], line['exception_type'],))

c.execute('CREATE TABLE calendar (service_id TEXT, monday INT, tuesday INT, wednesday INT, thursday INT, friday INT, saturday INT, sunday INT, start_date TEXT, end_date TEXT)')
c.execute('CREATE INDEX idx_dates_calendar ON calendar (service_id, start_date, end_date)')
if (os.path.exists('calendar.txt')):
    with open('calendar.txt') as f_txt:
        f = csv.DictReader(f_txt)
        for line in f:
            c.execute('INSERT INTO calendar VALUES(?,?,?,?,?,?,?,?,?,?)', (line['service_id'], line['monday'], line['tuesday'], line['wednesday'], line['thursday'], line['friday'], line['saturday'], line['sunday'], line['start_date'], line['end_date'],))

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