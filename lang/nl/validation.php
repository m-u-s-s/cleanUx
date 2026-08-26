<?php

/*
|--------------------------------------------------------------------------
| Validatieberichten
|--------------------------------------------------------------------------
|
| Dit bestand ontbrak voor het Nederlands. Elke afgekeurde invoer viel terug op het Frans — in een
| formulier dat verder volledig Nederlands was. Het is het bestand dat een gebruiker het vaakst
| leest zonder het te willen lezen.
|
| `attributes` vertaalt de VELDNAAM die in de zin wordt ingevuld. Zonder die tabel toont Laravel de
| sleutel zelf: « Het postal_code veld is verplicht. » De lijst volgt die van `lang/fr`, de meest
| volledige van het project.
|
| `custom` overschrijft de standaardzin voor drie gevallen waarin de standaardzin niet zegt wat de
| gebruiker moet doen.
*/

return [

    'accepted' => 'Het :attribute veld moet worden geaccepteerd.',
    'accepted_if' => 'Het :attribute veld moet worden geaccepteerd wanneer :other :value is.',
    'active_url' => 'Het :attribute veld moet een geldige URL zijn.',
    'after' => 'Het :attribute veld moet een datum na :date zijn.',
    'after_or_equal' => 'Het :attribute veld moet een datum na of gelijk aan :date zijn.',
    'alpha' => 'Het :attribute veld mag alleen letters bevatten.',
    'alpha_dash' => 'Het :attribute veld mag alleen letters, cijfers, streepjes en onderstrepingstekens bevatten.',
    'alpha_num' => 'Het :attribute veld mag alleen letters en cijfers bevatten.',
    'any_of' => 'Het :attribute veld is ongeldig.',
    'array' => 'Het :attribute veld moet een reeks zijn.',
    'ascii' => 'Het :attribute veld mag alleen alfanumerieke tekens en symbolen van één byte bevatten.',
    'before' => 'Het :attribute veld moet een datum vóór :date zijn.',
    'before_or_equal' => 'Het :attribute veld moet een datum vóór of gelijk aan :date zijn.',

    'between' => [
        'array' => 'Het :attribute veld moet tussen :min en :max items bevatten.',
        'file' => 'Het :attribute veld moet tussen :min en :max kilobytes zijn.',
        'numeric' => 'Het :attribute veld moet tussen :min en :max liggen.',
        'string' => 'Het :attribute veld moet tussen :min en :max tekens bevatten.',
    ],

    'boolean' => 'Het :attribute veld moet waar of onwaar zijn.',
    'can' => 'Het :attribute veld bevat een niet-toegestane waarde.',
    'confirmed' => 'De bevestiging van het :attribute veld komt niet overeen.',
    'contains' => 'In het :attribute veld ontbreekt een vereiste waarde.',
    'current_password' => 'Het wachtwoord is onjuist.',
    'date' => 'Het :attribute veld moet een geldige datum zijn.',
    'date_equals' => 'Het :attribute veld moet een datum gelijk aan :date zijn.',
    'date_format' => 'Het :attribute veld moet overeenkomen met de notatie :format.',
    'decimal' => 'Het :attribute veld moet :decimal decimalen hebben.',
    'declined' => 'Het :attribute veld moet worden geweigerd.',
    'declined_if' => 'Het :attribute veld moet worden geweigerd wanneer :other :value is.',
    'different' => 'Het :attribute veld en :other moeten verschillen.',
    'digits' => 'Het :attribute veld moet :digits cijfers bevatten.',
    'digits_between' => 'Het :attribute veld moet tussen :min en :max cijfers bevatten.',
    'dimensions' => 'Het :attribute veld heeft ongeldige afbeeldingsafmetingen.',
    'distinct' => 'Het :attribute veld bevat een dubbele waarde.',
    'doesnt_contain' => 'Het :attribute veld mag geen van de volgende bevatten: :values.',
    'doesnt_end_with' => 'Het :attribute veld mag niet eindigen op een van de volgende: :values.',
    'doesnt_start_with' => 'Het :attribute veld mag niet beginnen met een van de volgende: :values.',
    'email' => 'Het :attribute veld moet een geldig e-mailadres zijn.',
    'encoding' => 'Het :attribute veld moet in :encoding gecodeerd zijn.',
    'ends_with' => 'Het :attribute veld moet eindigen op een van de volgende: :values.',
    'enum' => 'De gekozen :attribute is ongeldig.',
    'exists' => 'De gekozen :attribute is ongeldig.',
    'extensions' => 'Het :attribute veld moet een van de volgende extensies hebben: :values.',
    'file' => 'Het :attribute veld moet een bestand zijn.',
    'filled' => 'Het :attribute veld moet een waarde hebben.',

    'gt' => [
        'array' => 'Het :attribute veld moet meer dan :value items bevatten.',
        'file' => 'Het :attribute veld moet groter zijn dan :value kilobytes.',
        'numeric' => 'Het :attribute veld moet groter zijn dan :value.',
        'string' => 'Het :attribute veld moet meer dan :value tekens bevatten.',
    ],

    'gte' => [
        'array' => 'Het :attribute veld moet :value items of meer bevatten.',
        'file' => 'Het :attribute veld moet groter dan of gelijk aan :value kilobytes zijn.',
        'numeric' => 'Het :attribute veld moet groter dan of gelijk aan :value zijn.',
        'string' => 'Het :attribute veld moet :value tekens of meer bevatten.',
    ],

    'hex_color' => 'Het :attribute veld moet een geldige hexadecimale kleur zijn.',
    'image' => 'Het :attribute veld moet een afbeelding zijn.',
    'in' => 'De gekozen :attribute is ongeldig.',
    'in_array' => 'Het :attribute veld moet voorkomen in :other.',
    'in_array_keys' => 'Het :attribute veld moet ten minste een van de volgende sleutels bevatten: :values.',
    'integer' => 'Het :attribute veld moet een geheel getal zijn.',
    'ip' => 'Het :attribute veld moet een geldig IP-adres zijn.',
    'ipv4' => 'Het :attribute veld moet een geldig IPv4-adres zijn.',
    'ipv6' => 'Het :attribute veld moet een geldig IPv6-adres zijn.',
    'json' => 'Het :attribute veld moet een geldige JSON-tekenreeks zijn.',
    'list' => 'Het :attribute veld moet een lijst zijn.',
    'lowercase' => 'Het :attribute veld moet in kleine letters staan.',

    'lt' => [
        'array' => 'Het :attribute veld moet minder dan :value items bevatten.',
        'file' => 'Het :attribute veld moet kleiner zijn dan :value kilobytes.',
        'numeric' => 'Het :attribute veld moet kleiner zijn dan :value.',
        'string' => 'Het :attribute veld moet minder dan :value tekens bevatten.',
    ],

    'lte' => [
        'array' => 'Het :attribute veld mag niet meer dan :value items bevatten.',
        'file' => 'Het :attribute veld moet kleiner dan of gelijk aan :value kilobytes zijn.',
        'numeric' => 'Het :attribute veld moet kleiner dan of gelijk aan :value zijn.',
        'string' => 'Het :attribute veld mag hoogstens :value tekens bevatten.',
    ],

    'mac_address' => 'Het :attribute veld moet een geldig MAC-adres zijn.',

    'max' => [
        'array' => 'Het :attribute veld mag niet meer dan :max items bevatten.',
        'file' => 'Het :attribute veld mag niet groter zijn dan :max kilobytes.',
        'numeric' => 'Het :attribute veld mag niet groter zijn dan :max.',
        'string' => 'Het :attribute veld mag niet meer dan :max tekens bevatten.',
    ],

    'max_digits' => 'Het :attribute veld mag niet meer dan :max cijfers bevatten.',
    'mimes' => 'Het :attribute veld moet een bestand zijn van het type: :values.',
    'mimetypes' => 'Het :attribute veld moet een bestand zijn van het type: :values.',

    'min' => [
        'array' => 'Het :attribute veld moet ten minste :min items bevatten.',
        'file' => 'Het :attribute veld moet ten minste :min kilobytes zijn.',
        'numeric' => 'Het :attribute veld moet ten minste :min zijn.',
        'string' => 'Het :attribute veld moet ten minste :min tekens bevatten.',
    ],

    'min_digits' => 'Het :attribute veld moet ten minste :min cijfers bevatten.',
    'missing' => 'Het :attribute veld mag niet aanwezig zijn.',
    'missing_if' => 'Het :attribute veld mag niet aanwezig zijn wanneer :other :value is.',
    'missing_unless' => 'Het :attribute veld mag niet aanwezig zijn tenzij :other :value is.',
    'missing_with' => 'Het :attribute veld mag niet aanwezig zijn wanneer :values aanwezig is.',
    'missing_with_all' => 'Het :attribute veld mag niet aanwezig zijn wanneer :values aanwezig zijn.',
    'multiple_of' => 'Het :attribute veld moet een veelvoud van :value zijn.',
    'not_in' => 'De gekozen :attribute is ongeldig.',
    'not_regex' => 'De notatie van het :attribute veld is ongeldig.',
    'numeric' => 'Het :attribute veld moet een getal zijn.',

    'password' => [
        'letters' => 'Het :attribute veld moet ten minste één letter bevatten.',
        'mixed' => 'Het :attribute veld moet ten minste één hoofdletter en één kleine letter bevatten.',
        'numbers' => 'Het :attribute veld moet ten minste één cijfer bevatten.',
        'symbols' => 'Het :attribute veld moet ten minste één symbool bevatten.',
        'uncompromised' => 'De opgegeven :attribute is in een datalek verschenen. Kies een andere :attribute.',
    ],

    'present' => 'Het :attribute veld moet aanwezig zijn.',
    'present_if' => 'Het :attribute veld moet aanwezig zijn wanneer :other :value is.',
    'present_unless' => 'Het :attribute veld moet aanwezig zijn tenzij :other :value is.',
    'present_with' => 'Het :attribute veld moet aanwezig zijn wanneer :values aanwezig is.',
    'present_with_all' => 'Het :attribute veld moet aanwezig zijn wanneer :values aanwezig zijn.',
    'prohibited' => 'Het :attribute veld is niet toegestaan.',
    'prohibited_if' => 'Het :attribute veld is niet toegestaan wanneer :other :value is.',
    'prohibited_if_accepted' => 'Het :attribute veld is niet toegestaan wanneer :other is geaccepteerd.',
    'prohibited_if_declined' => 'Het :attribute veld is niet toegestaan wanneer :other is geweigerd.',
    'prohibited_unless' => 'Het :attribute veld is niet toegestaan tenzij :other in :values voorkomt.',
    'prohibits' => 'Het :attribute veld verhindert dat :other aanwezig is.',
    'regex' => 'De notatie van het :attribute veld is ongeldig.',
    'required' => 'Het :attribute veld is verplicht.',
    'required_array_keys' => 'Het :attribute veld moet vermeldingen bevatten voor: :values.',
    'required_if' => 'Het :attribute veld is verplicht wanneer :other :value is.',
    'required_if_accepted' => 'Het :attribute veld is verplicht wanneer :other is geaccepteerd.',
    'required_if_declined' => 'Het :attribute veld is verplicht wanneer :other is geweigerd.',
    'required_unless' => 'Het :attribute veld is verplicht tenzij :other in :values voorkomt.',
    'required_with' => 'Het :attribute veld is verplicht wanneer :values aanwezig is.',
    'required_with_all' => 'Het :attribute veld is verplicht wanneer :values aanwezig zijn.',
    'required_without' => 'Het :attribute veld is verplicht wanneer :values niet aanwezig is.',
    'required_without_all' => 'Het :attribute veld is verplicht wanneer geen van :values aanwezig is.',
    'same' => 'Het :attribute veld moet overeenkomen met :other.',

    'size' => [
        'array' => 'Het :attribute veld moet :size items bevatten.',
        'file' => 'Het :attribute veld moet :size kilobytes zijn.',
        'numeric' => 'Het :attribute veld moet :size zijn.',
        'string' => 'Het :attribute veld moet :size tekens bevatten.',
    ],

    'starts_with' => 'Het :attribute veld moet beginnen met een van de volgende: :values.',
    'string' => 'Het :attribute veld moet een tekenreeks zijn.',
    'timezone' => 'Het :attribute veld moet een geldige tijdzone zijn.',
    'unique' => 'Deze :attribute is al in gebruik.',
    'uploaded' => 'Het uploaden van :attribute is mislukt.',
    'uppercase' => 'Het :attribute veld moet in hoofdletters staan.',
    'url' => 'Het :attribute veld moet een geldige URL zijn.',
    'ulid' => 'Het :attribute veld moet een geldige ULID zijn.',
    'uuid' => 'Het :attribute veld moet een geldige UUID zijn.',

    /*
     * De standaardzin zegt WAT er mis is, niet wat men moet doen. Voor deze drie is dat het
     * verschil tussen een gebruiker die opgeeft en een die zijn account terugvindt.
     */
    'custom' => [
        'email' => [
            'unique' => 'Er bestaat al een account met dit e-mailadres. Meld u aan of gebruik „Wachtwoord vergeten”.',
        ],
        'accept_terms' => [
            'accepted' => 'U moet de algemene voorwaarden aanvaarden om een account aan te maken.',
        ],
        'terms' => [
            'accepted' => 'U moet de algemene voorwaarden aanvaarden om een account aan te maken.',
        ],
    ],

    /*
     * Zonder deze tabel vult Laravel de SLEUTEL in de zin in: « Het postal_code veld is verplicht. »
     */
    'attributes' => [
        'accept_terms' => 'algemene voorwaarden',
        'address' => 'adres',
        'city' => 'stad',
        'code' => 'code',
        'company_name' => 'bedrijfsnaam',
        'current_password' => 'huidig wachtwoord',
        'device_name' => 'apparaat',
        'email' => 'e-mailadres',
        'name' => 'naam',
        'password' => 'wachtwoord',
        'password_confirmation' => 'wachtwoordbevestiging',
        'phone' => 'telefoonnummer',
        'postal_code' => 'postcode',
        'provider_company_name' => 'naam van het dienstverlenende bedrijf',
        'terms' => 'algemene voorwaarden',
        'tva_number' => 'btw-nummer',
        'two_factor_code' => 'authenticatiecode',
        'vat_number' => 'btw-nummer',
    ],

];
