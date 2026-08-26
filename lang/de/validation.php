<?php

/*
|--------------------------------------------------------------------------
| Validierungsmeldungen
|--------------------------------------------------------------------------
|
| Diese Datei fehlte für das Deutsche. Jede abgelehnte Eingabe fiel auf das Französische zurück — in
| einem Formular, das ansonsten vollständig deutsch war. Es ist die Datei, die eine Nutzerin am
| häufigsten liest, ohne sie lesen zu wollen.
|
| `attributes` übersetzt den FELDNAMEN, der in den Satz eingesetzt wird. Ohne diese Tabelle zeigt
| Laravel den Schlüssel selbst: „Das postal_code Feld ist erforderlich." Die Liste folgt der von
| `lang/fr`, der vollständigsten des Projekts.
|
| `custom` überschreibt den Standardsatz in drei Fällen, in denen dieser nicht sagt, was zu tun ist.
*/

return [

    'accepted' => 'Das Feld :attribute muss akzeptiert werden.',
    'accepted_if' => 'Das Feld :attribute muss akzeptiert werden, wenn :other :value ist.',
    'active_url' => 'Das Feld :attribute muss eine gültige URL sein.',
    'after' => 'Das Feld :attribute muss ein Datum nach :date sein.',
    'after_or_equal' => 'Das Feld :attribute muss ein Datum nach oder gleich :date sein.',
    'alpha' => 'Das Feld :attribute darf nur Buchstaben enthalten.',
    'alpha_dash' => 'Das Feld :attribute darf nur Buchstaben, Zahlen, Binde- und Unterstriche enthalten.',
    'alpha_num' => 'Das Feld :attribute darf nur Buchstaben und Zahlen enthalten.',
    'any_of' => 'Das Feld :attribute ist ungültig.',
    'array' => 'Das Feld :attribute muss ein Array sein.',
    'ascii' => 'Das Feld :attribute darf nur alphanumerische Ein-Byte-Zeichen und Symbole enthalten.',
    'before' => 'Das Feld :attribute muss ein Datum vor :date sein.',
    'before_or_equal' => 'Das Feld :attribute muss ein Datum vor oder gleich :date sein.',

    'between' => [
        'array' => 'Das Feld :attribute muss zwischen :min und :max Einträge enthalten.',
        'file' => 'Das Feld :attribute muss zwischen :min und :max Kilobyte groß sein.',
        'numeric' => 'Das Feld :attribute muss zwischen :min und :max liegen.',
        'string' => 'Das Feld :attribute muss zwischen :min und :max Zeichen lang sein.',
    ],

    'boolean' => 'Das Feld :attribute muss wahr oder falsch sein.',
    'can' => 'Das Feld :attribute enthält einen unzulässigen Wert.',
    'confirmed' => 'Die Bestätigung des Feldes :attribute stimmt nicht überein.',
    'contains' => 'Im Feld :attribute fehlt ein erforderlicher Wert.',
    'current_password' => 'Das Passwort ist falsch.',
    'date' => 'Das Feld :attribute muss ein gültiges Datum sein.',
    'date_equals' => 'Das Feld :attribute muss ein Datum gleich :date sein.',
    'date_format' => 'Das Feld :attribute muss dem Format :format entsprechen.',
    'decimal' => 'Das Feld :attribute muss :decimal Nachkommastellen haben.',
    'declined' => 'Das Feld :attribute muss abgelehnt werden.',
    'declined_if' => 'Das Feld :attribute muss abgelehnt werden, wenn :other :value ist.',
    'different' => 'Das Feld :attribute und :other müssen sich unterscheiden.',
    'digits' => 'Das Feld :attribute muss :digits Ziffern haben.',
    'digits_between' => 'Das Feld :attribute muss zwischen :min und :max Ziffern haben.',
    'dimensions' => 'Das Feld :attribute hat ungültige Bildabmessungen.',
    'distinct' => 'Das Feld :attribute enthält einen doppelten Wert.',
    'doesnt_contain' => 'Das Feld :attribute darf keines der folgenden enthalten: :values.',
    'doesnt_end_with' => 'Das Feld :attribute darf nicht mit einem der folgenden enden: :values.',
    'doesnt_start_with' => 'Das Feld :attribute darf nicht mit einem der folgenden beginnen: :values.',
    'email' => 'Das Feld :attribute muss eine gültige E-Mail-Adresse sein.',
    'encoding' => 'Das Feld :attribute muss in :encoding kodiert sein.',
    'ends_with' => 'Das Feld :attribute muss mit einem der folgenden enden: :values.',
    'enum' => 'Der gewählte Wert für :attribute ist ungültig.',
    'exists' => 'Der gewählte Wert für :attribute ist ungültig.',
    'extensions' => 'Das Feld :attribute muss eine der folgenden Dateiendungen haben: :values.',
    'file' => 'Das Feld :attribute muss eine Datei sein.',
    'filled' => 'Das Feld :attribute muss einen Wert haben.',

    'gt' => [
        'array' => 'Das Feld :attribute muss mehr als :value Einträge enthalten.',
        'file' => 'Das Feld :attribute muss größer als :value Kilobyte sein.',
        'numeric' => 'Das Feld :attribute muss größer als :value sein.',
        'string' => 'Das Feld :attribute muss länger als :value Zeichen sein.',
    ],

    'gte' => [
        'array' => 'Das Feld :attribute muss :value Einträge oder mehr enthalten.',
        'file' => 'Das Feld :attribute muss größer oder gleich :value Kilobyte sein.',
        'numeric' => 'Das Feld :attribute muss größer oder gleich :value sein.',
        'string' => 'Das Feld :attribute muss mindestens :value Zeichen lang sein.',
    ],

    'hex_color' => 'Das Feld :attribute muss eine gültige Hexadezimalfarbe sein.',
    'image' => 'Das Feld :attribute muss ein Bild sein.',
    'in' => 'Der gewählte Wert für :attribute ist ungültig.',
    'in_array' => 'Das Feld :attribute muss in :other vorkommen.',
    'in_array_keys' => 'Das Feld :attribute muss mindestens einen der folgenden Schlüssel enthalten: :values.',
    'integer' => 'Das Feld :attribute muss eine ganze Zahl sein.',
    'ip' => 'Das Feld :attribute muss eine gültige IP-Adresse sein.',
    'ipv4' => 'Das Feld :attribute muss eine gültige IPv4-Adresse sein.',
    'ipv6' => 'Das Feld :attribute muss eine gültige IPv6-Adresse sein.',
    'json' => 'Das Feld :attribute muss eine gültige JSON-Zeichenkette sein.',
    'list' => 'Das Feld :attribute muss eine Liste sein.',
    'lowercase' => 'Das Feld :attribute muss kleingeschrieben sein.',

    'lt' => [
        'array' => 'Das Feld :attribute muss weniger als :value Einträge enthalten.',
        'file' => 'Das Feld :attribute muss kleiner als :value Kilobyte sein.',
        'numeric' => 'Das Feld :attribute muss kleiner als :value sein.',
        'string' => 'Das Feld :attribute muss kürzer als :value Zeichen sein.',
    ],

    'lte' => [
        'array' => 'Das Feld :attribute darf nicht mehr als :value Einträge enthalten.',
        'file' => 'Das Feld :attribute muss kleiner oder gleich :value Kilobyte sein.',
        'numeric' => 'Das Feld :attribute muss kleiner oder gleich :value sein.',
        'string' => 'Das Feld :attribute darf höchstens :value Zeichen lang sein.',
    ],

    'mac_address' => 'Das Feld :attribute muss eine gültige MAC-Adresse sein.',

    'max' => [
        'array' => 'Das Feld :attribute darf nicht mehr als :max Einträge enthalten.',
        'file' => 'Das Feld :attribute darf nicht größer als :max Kilobyte sein.',
        'numeric' => 'Das Feld :attribute darf nicht größer als :max sein.',
        'string' => 'Das Feld :attribute darf nicht länger als :max Zeichen sein.',
    ],

    'max_digits' => 'Das Feld :attribute darf nicht mehr als :max Ziffern haben.',
    'mimes' => 'Das Feld :attribute muss eine Datei des Typs :values sein.',
    'mimetypes' => 'Das Feld :attribute muss eine Datei des Typs :values sein.',

    'min' => [
        'array' => 'Das Feld :attribute muss mindestens :min Einträge enthalten.',
        'file' => 'Das Feld :attribute muss mindestens :min Kilobyte groß sein.',
        'numeric' => 'Das Feld :attribute muss mindestens :min sein.',
        'string' => 'Das Feld :attribute muss mindestens :min Zeichen lang sein.',
    ],

    'min_digits' => 'Das Feld :attribute muss mindestens :min Ziffern haben.',
    'missing' => 'Das Feld :attribute darf nicht vorhanden sein.',
    'missing_if' => 'Das Feld :attribute darf nicht vorhanden sein, wenn :other :value ist.',
    'missing_unless' => 'Das Feld :attribute darf nicht vorhanden sein, außer :other ist :value.',
    'missing_with' => 'Das Feld :attribute darf nicht vorhanden sein, wenn :values vorhanden ist.',
    'missing_with_all' => 'Das Feld :attribute darf nicht vorhanden sein, wenn :values vorhanden sind.',
    'multiple_of' => 'Das Feld :attribute muss ein Vielfaches von :value sein.',
    'not_in' => 'Der gewählte Wert für :attribute ist ungültig.',
    'not_regex' => 'Das Format des Feldes :attribute ist ungültig.',
    'numeric' => 'Das Feld :attribute muss eine Zahl sein.',

    'password' => [
        'letters' => 'Das Feld :attribute muss mindestens einen Buchstaben enthalten.',
        'mixed' => 'Das Feld :attribute muss mindestens einen Groß- und einen Kleinbuchstaben enthalten.',
        'numbers' => 'Das Feld :attribute muss mindestens eine Zahl enthalten.',
        'symbols' => 'Das Feld :attribute muss mindestens ein Sonderzeichen enthalten.',
        'uncompromised' => 'Das angegebene :attribute ist in einem Datenleck aufgetaucht. Wählen Sie ein anderes :attribute.',
    ],

    'present' => 'Das Feld :attribute muss vorhanden sein.',
    'present_if' => 'Das Feld :attribute muss vorhanden sein, wenn :other :value ist.',
    'present_unless' => 'Das Feld :attribute muss vorhanden sein, außer :other ist :value.',
    'present_with' => 'Das Feld :attribute muss vorhanden sein, wenn :values vorhanden ist.',
    'present_with_all' => 'Das Feld :attribute muss vorhanden sein, wenn :values vorhanden sind.',
    'prohibited' => 'Das Feld :attribute ist nicht zulässig.',
    'prohibited_if' => 'Das Feld :attribute ist nicht zulässig, wenn :other :value ist.',
    'prohibited_if_accepted' => 'Das Feld :attribute ist nicht zulässig, wenn :other akzeptiert wurde.',
    'prohibited_if_declined' => 'Das Feld :attribute ist nicht zulässig, wenn :other abgelehnt wurde.',
    'prohibited_unless' => 'Das Feld :attribute ist nicht zulässig, außer :other liegt in :values.',
    'prohibits' => 'Das Feld :attribute verhindert, dass :other vorhanden ist.',
    'regex' => 'Das Format des Feldes :attribute ist ungültig.',
    'required' => 'Das Feld :attribute ist erforderlich.',
    'required_array_keys' => 'Das Feld :attribute muss Einträge enthalten für: :values.',
    'required_if' => 'Das Feld :attribute ist erforderlich, wenn :other :value ist.',
    'required_if_accepted' => 'Das Feld :attribute ist erforderlich, wenn :other akzeptiert wurde.',
    'required_if_declined' => 'Das Feld :attribute ist erforderlich, wenn :other abgelehnt wurde.',
    'required_unless' => 'Das Feld :attribute ist erforderlich, außer :other liegt in :values.',
    'required_with' => 'Das Feld :attribute ist erforderlich, wenn :values vorhanden ist.',
    'required_with_all' => 'Das Feld :attribute ist erforderlich, wenn :values vorhanden sind.',
    'required_without' => 'Das Feld :attribute ist erforderlich, wenn :values nicht vorhanden ist.',
    'required_without_all' => 'Das Feld :attribute ist erforderlich, wenn keines von :values vorhanden ist.',
    'same' => 'Das Feld :attribute muss mit :other übereinstimmen.',

    'size' => [
        'array' => 'Das Feld :attribute muss :size Einträge enthalten.',
        'file' => 'Das Feld :attribute muss :size Kilobyte groß sein.',
        'numeric' => 'Das Feld :attribute muss :size sein.',
        'string' => 'Das Feld :attribute muss :size Zeichen lang sein.',
    ],

    'starts_with' => 'Das Feld :attribute muss mit einem der folgenden beginnen: :values.',
    'string' => 'Das Feld :attribute muss eine Zeichenkette sein.',
    'timezone' => 'Das Feld :attribute muss eine gültige Zeitzone sein.',
    'unique' => 'Dieses :attribute ist bereits vergeben.',
    'uploaded' => 'Das Hochladen von :attribute ist fehlgeschlagen.',
    'uppercase' => 'Das Feld :attribute muss großgeschrieben sein.',
    'url' => 'Das Feld :attribute muss eine gültige URL sein.',
    'ulid' => 'Das Feld :attribute muss eine gültige ULID sein.',
    'uuid' => 'Das Feld :attribute muss eine gültige UUID sein.',

    /*
     * Der Standardsatz sagt, WAS falsch ist, nicht was zu tun ist. Bei diesen dreien ist das der
     * Unterschied zwischen jemandem, der aufgibt, und jemandem, der sein Konto wiederfindet.
     */
    'custom' => [
        'email' => [
            'unique' => 'Mit dieser E-Mail-Adresse besteht bereits ein Konto. Melden Sie sich an oder nutzen Sie „Passwort vergessen".',
        ],
        'accept_terms' => [
            'accepted' => 'Sie müssen die Allgemeinen Geschäftsbedingungen annehmen, um ein Konto anzulegen.',
        ],
        'terms' => [
            'accepted' => 'Sie müssen die Allgemeinen Geschäftsbedingungen annehmen, um ein Konto anzulegen.',
        ],
    ],

    /*
     * Ohne diese Tabelle setzt Laravel den SCHLÜSSEL in den Satz ein: „Das postal_code Feld ist
     * erforderlich."
     */
    'attributes' => [
        'accept_terms' => 'Allgemeine Geschäftsbedingungen',
        'address' => 'Adresse',
        'city' => 'Stadt',
        'code' => 'Code',
        'company_name' => 'Firmenname',
        'current_password' => 'aktuelles Passwort',
        'device_name' => 'Gerät',
        'email' => 'E-Mail-Adresse',
        'name' => 'Name',
        'password' => 'Passwort',
        'password_confirmation' => 'Passwortbestätigung',
        'phone' => 'Telefonnummer',
        'postal_code' => 'Postleitzahl',
        'provider_company_name' => 'Name des Dienstleistungsunternehmens',
        'terms' => 'Allgemeine Geschäftsbedingungen',
        'tva_number' => 'USt-IdNr.',
        'two_factor_code' => 'Authentifizierungscode',
        'vat_number' => 'USt-IdNr.',
    ],

];
