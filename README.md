# Er strætó seinn?
Kóðinn á bak við [seinn.is](https://seinn.is). Gögnin eru sótt með Python, unnin í SQLite3 og birt með PHP. Kerfið er síðan keyrt upp í Docker-vél.


## Fyrstu skref
Fyrst þarf að búa til `straeto_link.py` sem lítur nokkurn veginn svona út, eftir að hafa fengið slíkan tengil frá Strætó BS:

```
STRAETO_API_LINK = 'https://opendata.straeto.is/bus/?????????????????/status.xml'
```

Svo er hægt að spinna upp Docker vélinni eða fylgja sambærilegum skrefum og er lýst í `Dockerfile`.

## Virkni
1. Vélin er keyrð upp og `startup.sh` sett í gang.
1. `gtfs.py` sækir GTFS gagnagrunninn frá Strætó og prentar hann í `gtfs.db`.
1. Notandinn opnar `index.html`.
1. JS sækir gögn eftir staðsetningu eða stöðvarheiti í `neareststop.php`.
1. Ef engin spánný gögn um staðsetningar vagna eru fyrir hendi eru þau sótt með `scrape.py`.
1. Gögn eru sótt með `functions.php` og þeim skeytt saman í JSON.
1. Síðan sækir gögnin með sömu stikum á um 10s fresti.

