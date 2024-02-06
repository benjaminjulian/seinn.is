import xml.etree.ElementTree as ET
import requests
import sqlite3
import time
from datetime import datetime
from straeto_link import STRAETO_API_LINK

def readBusTime(t):
    #convert the format YYMMDDHHMMSS to YYYY-MM-DD HH:MM:SS
    return '20' + t[0:2] + '-' + t[2:4] + '-' + t[4:6] + ' ' + t[6:8] + ':' + t[8:10] + ':' + t[10:12]

def importBuses():
    r = requests.get(STRAETO_API_LINK)

    # parse the xml in r.content
    tree = ET.fromstring(r.content)

    buses = tree.findall('bus')

    conn = sqlite3.connect('../gtfs.db')
    #conn.execute("PRAGMA journal_mode=WAL")
    #conn.execute("PRAGMA synchronous=NORMAL")
    c = conn.cursor()

    # insert current UTC time into the logs table
    c.execute('DELETE FROM buses WHERE 1')
    c.execute('DELETE FROM logs WHERE 1')
    c.execute('INSERT INTO logs VALUES (?,?)', ('buses', datetime.fromtimestamp(time.time()).strftime('%Y-%m-%d %H:%M:%S')))

    for bus in buses:
        b = bus.attrib
        # insert b into the database search.db, table buses

        c.execute('INSERT INTO buses VALUES (?,?,?,?,?,?,?,?,?)', (
            readBusTime(b['time']),
            b['lat'],
            b['lon'],
            b['head'],
            b['fix'],
            b['route'],
            b['stop'],
            b['next'],
            b['code']
        ))

    conn.commit()
    conn.close()

importBuses()