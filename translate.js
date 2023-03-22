txt_translations = {
    'pl': {
        'Er strætó seinn?': 'Autobus jest spóźniony?',
        'Stoppistöð': 'Przystanek',
        'mínútur': 'minut',
        'mínútum': 'minut',
        'mínútna': 'minut',
        'mínúta': 'minuta',
        'mínútu': 'minuta',
        'klukkustundir': 'godziny',
        'klukkustundum': 'godzin',
        'klukkustunda': 'godzina',
        'klukkustund': 'godzina',
        'klukkustundar': 'godzin',
        'Strætó er kominn. ': 'Autobus jest tu. ',
        'Strætóinn er í bið við upphaf leiðarinnar. ': 'Autobus jest w trakcie jazdy. ',
        'Strætóinn er á fyrsta stoppi leiðarinnar. ': 'Autobus jest na pierwszym przystanku. ',
        'Næsta stopp vagnsins er hér. ': 'Następny przystanek autobusu jest tutaj. ',
        'Strætó er í bið hérna. ': 'Autobus jest tutaj. ',
        'Strætó er að leggja af stað héðan. ': 'Autobus jest w trakcie jazdy. ',
        'Strætó er á leiðinni héðan á stoppið ': 'Autobus jest w trakcie jazdy ',
        'Strætóinn er á fyrsta stoppi leiðarinnar, ': 'Autobus jest na pierwszym przystanku, ',
        'Strætóinn er á stoppinu ': 'Autobus jest na przystanku ',
        'Strætóinn er á leiðinni á stoppið ': 'Autobus jest w trakcie jazdy na przystanku ',
        'Strætóinn er á leiðinni frá stoppinu ': 'Autobus jest w trakcie jazdy od przystanku ',
        'stoppi': 'przystanku',
        'stoppum': 'przystanków',
        ' frá. ': ' od. ',
        'Hann er <span class="time">': 'Jest <span class="time">',
        '</span> á eftir áætlun. ': '</span> później. ',
        '</span> á undan áætlun. ': '</span> wcześniej. ',
        '(Það er slökkt á honum.) ': '(Jest wyłączony.) ',
        '<p class="next-bus">Strætó er ekki lagður af stað.</p>': '<p class="next-bus">Autobus nie jest w trakcie jazdy.</p>',
        ' í áætlaða brottför kl. ': ' w planowanym czasie jazdy o ',
        'Áætluð brottför var kl. ': 'Planowana jazda miała miejsce o ',
        '<p class="loading">Sæki gögn...</p>' : '<p class="loading">Pobieranie danych...</p>',
    }
};

function txt(text) {
    if (lang == 'is') {
        return text;
    } else {
        return txt_translations[lang][text];
    }
}