<?php

/*
|--------------------------------------------------------------------------
| Messaggi di validazione
|--------------------------------------------------------------------------
|
| Questo file mancava per l’italiano. Ogni voce rifiutata ricadeva sul francese — in un modulo per il
| resto interamente in italiano. È il file che un utente legge più spesso senza volerlo leggere.
|
| `attributes` traduce il NOME DEL CAMPO che viene inserito nella frase. Senza quella tabella
| Laravel mostra la chiave stessa: «Il campo postal_code è obbligatorio.» L’elenco segue quello di
| `lang/fr`, il più completo del progetto.
|
| `custom` sostituisce la frase standard in tre casi in cui questa non dice che cosa fare.
*/

return [

    'accepted' => 'Il campo :attribute deve essere accettato.',
    'accepted_if' => 'Il campo :attribute deve essere accettato quando :other è :value.',
    'active_url' => 'Il campo :attribute deve essere un URL valido.',
    'after' => 'Il campo :attribute deve essere una data successiva a :date.',
    'after_or_equal' => 'Il campo :attribute deve essere una data successiva o uguale a :date.',
    'alpha' => 'Il campo :attribute può contenere solo lettere.',
    'alpha_dash' => 'Il campo :attribute può contenere solo lettere, numeri, trattini e trattini bassi.',
    'alpha_num' => 'Il campo :attribute può contenere solo lettere e numeri.',
    'any_of' => 'Il campo :attribute non è valido.',
    'array' => 'Il campo :attribute deve essere un array.',
    'ascii' => 'Il campo :attribute può contenere solo caratteri alfanumerici e simboli a un byte.',
    'before' => 'Il campo :attribute deve essere una data precedente a :date.',
    'before_or_equal' => 'Il campo :attribute deve essere una data precedente o uguale a :date.',

    'between' => [
        'array' => 'Il campo :attribute deve contenere fra :min e :max elementi.',
        'file' => 'Il campo :attribute deve occupare fra :min e :max kilobyte.',
        'numeric' => 'Il campo :attribute deve essere compreso fra :min e :max.',
        'string' => 'Il campo :attribute deve avere fra :min e :max caratteri.',
    ],

    'boolean' => 'Il campo :attribute deve essere vero o falso.',
    'can' => 'Il campo :attribute contiene un valore non autorizzato.',
    'confirmed' => 'La conferma del campo :attribute non corrisponde.',
    'contains' => 'Nel campo :attribute manca un valore obbligatorio.',
    'current_password' => 'La password non è corretta.',
    'date' => 'Il campo :attribute deve essere una data valida.',
    'date_equals' => 'Il campo :attribute deve essere una data uguale a :date.',
    'date_format' => 'Il campo :attribute deve rispettare il formato :format.',
    'decimal' => 'Il campo :attribute deve avere :decimal decimali.',
    'declined' => 'Il campo :attribute deve essere rifiutato.',
    'declined_if' => 'Il campo :attribute deve essere rifiutato quando :other è :value.',
    'different' => 'Il campo :attribute e :other devono essere diversi.',
    'digits' => 'Il campo :attribute deve avere :digits cifre.',
    'digits_between' => 'Il campo :attribute deve avere fra :min e :max cifre.',
    'dimensions' => 'Il campo :attribute ha dimensioni immagine non valide.',
    'distinct' => 'Il campo :attribute contiene un valore duplicato.',
    'doesnt_contain' => 'Il campo :attribute non deve contenere nessuno dei seguenti: :values.',
    'doesnt_end_with' => 'Il campo :attribute non deve terminare con uno dei seguenti: :values.',
    'doesnt_start_with' => 'Il campo :attribute non deve iniziare con uno dei seguenti: :values.',
    'email' => 'Il campo :attribute deve essere un indirizzo e-mail valido.',
    'encoding' => 'Il campo :attribute deve essere codificato in :encoding.',
    'ends_with' => 'Il campo :attribute deve terminare con uno dei seguenti: :values.',
    'enum' => 'Il valore scelto per :attribute non è valido.',
    'exists' => 'Il valore scelto per :attribute non è valido.',
    'extensions' => 'Il campo :attribute deve avere una delle seguenti estensioni: :values.',
    'file' => 'Il campo :attribute deve essere un file.',
    'filled' => 'Il campo :attribute deve avere un valore.',

    'gt' => [
        'array' => 'Il campo :attribute deve contenere più di :value elementi.',
        'file' => 'Il campo :attribute deve occupare più di :value kilobyte.',
        'numeric' => 'Il campo :attribute deve essere maggiore di :value.',
        'string' => 'Il campo :attribute deve avere più di :value caratteri.',
    ],

    'gte' => [
        'array' => 'Il campo :attribute deve contenere :value elementi o più.',
        'file' => 'Il campo :attribute deve occupare :value kilobyte o più.',
        'numeric' => 'Il campo :attribute deve essere maggiore o uguale a :value.',
        'string' => 'Il campo :attribute deve avere :value caratteri o più.',
    ],

    'hex_color' => 'Il campo :attribute deve essere un colore esadecimale valido.',
    'image' => 'Il campo :attribute deve essere un’immagine.',
    'in' => 'Il valore scelto per :attribute non è valido.',
    'in_array' => 'Il campo :attribute deve esistere in :other.',
    'in_array_keys' => 'Il campo :attribute deve contenere almeno una delle seguenti chiavi: :values.',
    'integer' => 'Il campo :attribute deve essere un numero intero.',
    'ip' => 'Il campo :attribute deve essere un indirizzo IP valido.',
    'ipv4' => 'Il campo :attribute deve essere un indirizzo IPv4 valido.',
    'ipv6' => 'Il campo :attribute deve essere un indirizzo IPv6 valido.',
    'json' => 'Il campo :attribute deve essere una stringa JSON valida.',
    'list' => 'Il campo :attribute deve essere un elenco.',
    'lowercase' => 'Il campo :attribute deve essere in minuscolo.',

    'lt' => [
        'array' => 'Il campo :attribute deve contenere meno di :value elementi.',
        'file' => 'Il campo :attribute deve occupare meno di :value kilobyte.',
        'numeric' => 'Il campo :attribute deve essere minore di :value.',
        'string' => 'Il campo :attribute deve avere meno di :value caratteri.',
    ],

    'lte' => [
        'array' => 'Il campo :attribute non deve contenere più di :value elementi.',
        'file' => 'Il campo :attribute deve occupare :value kilobyte o meno.',
        'numeric' => 'Il campo :attribute deve essere minore o uguale a :value.',
        'string' => 'Il campo :attribute deve avere :value caratteri o meno.',
    ],

    'mac_address' => 'Il campo :attribute deve essere un indirizzo MAC valido.',

    'max' => [
        'array' => 'Il campo :attribute non deve contenere più di :max elementi.',
        'file' => 'Il campo :attribute non deve occupare più di :max kilobyte.',
        'numeric' => 'Il campo :attribute non deve essere maggiore di :max.',
        'string' => 'Il campo :attribute non deve avere più di :max caratteri.',
    ],

    'max_digits' => 'Il campo :attribute non deve avere più di :max cifre.',
    'mimes' => 'Il campo :attribute deve essere un file di tipo: :values.',
    'mimetypes' => 'Il campo :attribute deve essere un file di tipo: :values.',

    'min' => [
        'array' => 'Il campo :attribute deve contenere almeno :min elementi.',
        'file' => 'Il campo :attribute deve occupare almeno :min kilobyte.',
        'numeric' => 'Il campo :attribute deve essere almeno :min.',
        'string' => 'Il campo :attribute deve avere almeno :min caratteri.',
    ],

    'min_digits' => 'Il campo :attribute deve avere almeno :min cifre.',
    'missing' => 'Il campo :attribute non deve essere presente.',
    'missing_if' => 'Il campo :attribute non deve essere presente quando :other è :value.',
    'missing_unless' => 'Il campo :attribute non deve essere presente a meno che :other non sia :value.',
    'missing_with' => 'Il campo :attribute non deve essere presente quando :values è presente.',
    'missing_with_all' => 'Il campo :attribute non deve essere presente quando :values sono presenti.',
    'multiple_of' => 'Il campo :attribute deve essere un multiplo di :value.',
    'not_in' => 'Il valore scelto per :attribute non è valido.',
    'not_regex' => 'Il formato del campo :attribute non è valido.',
    'numeric' => 'Il campo :attribute deve essere un numero.',

    'password' => [
        'letters' => 'Il campo :attribute deve contenere almeno una lettera.',
        'mixed' => 'Il campo :attribute deve contenere almeno una maiuscola e una minuscola.',
        'numbers' => 'Il campo :attribute deve contenere almeno un numero.',
        'symbols' => 'Il campo :attribute deve contenere almeno un simbolo.',
        'uncompromised' => 'Il :attribute indicato è comparso in una violazione di dati. Ne scelga un altro.',
    ],

    'present' => 'Il campo :attribute deve essere presente.',
    'present_if' => 'Il campo :attribute deve essere presente quando :other è :value.',
    'present_unless' => 'Il campo :attribute deve essere presente a meno che :other non sia :value.',
    'present_with' => 'Il campo :attribute deve essere presente quando :values è presente.',
    'present_with_all' => 'Il campo :attribute deve essere presente quando :values sono presenti.',
    'prohibited' => 'Il campo :attribute non è ammesso.',
    'prohibited_if' => 'Il campo :attribute non è ammesso quando :other è :value.',
    'prohibited_if_accepted' => 'Il campo :attribute non è ammesso quando :other è stato accettato.',
    'prohibited_if_declined' => 'Il campo :attribute non è ammesso quando :other è stato rifiutato.',
    'prohibited_unless' => 'Il campo :attribute non è ammesso a meno che :other non sia in :values.',
    'prohibits' => 'Il campo :attribute impedisce che :other sia presente.',
    'regex' => 'Il formato del campo :attribute non è valido.',
    'required' => 'Il campo :attribute è obbligatorio.',
    'required_array_keys' => 'Il campo :attribute deve contenere voci per: :values.',
    'required_if' => 'Il campo :attribute è obbligatorio quando :other è :value.',
    'required_if_accepted' => 'Il campo :attribute è obbligatorio quando :other è stato accettato.',
    'required_if_declined' => 'Il campo :attribute è obbligatorio quando :other è stato rifiutato.',
    'required_unless' => 'Il campo :attribute è obbligatorio a meno che :other non sia in :values.',
    'required_with' => 'Il campo :attribute è obbligatorio quando :values è presente.',
    'required_with_all' => 'Il campo :attribute è obbligatorio quando :values sono presenti.',
    'required_without' => 'Il campo :attribute è obbligatorio quando :values non è presente.',
    'required_without_all' => 'Il campo :attribute è obbligatorio quando nessuno di :values è presente.',
    'same' => 'Il campo :attribute deve corrispondere a :other.',

    'size' => [
        'array' => 'Il campo :attribute deve contenere :size elementi.',
        'file' => 'Il campo :attribute deve occupare :size kilobyte.',
        'numeric' => 'Il campo :attribute deve essere :size.',
        'string' => 'Il campo :attribute deve avere :size caratteri.',
    ],

    'starts_with' => 'Il campo :attribute deve iniziare con uno dei seguenti: :values.',
    'string' => 'Il campo :attribute deve essere una stringa.',
    'timezone' => 'Il campo :attribute deve essere un fuso orario valido.',
    'unique' => 'Questo :attribute è già in uso.',
    'uploaded' => 'Il caricamento di :attribute non è riuscito.',
    'uppercase' => 'Il campo :attribute deve essere in maiuscolo.',
    'url' => 'Il campo :attribute deve essere un URL valido.',
    'ulid' => 'Il campo :attribute deve essere un ULID valido.',
    'uuid' => 'Il campo :attribute deve essere un UUID valido.',

    /*
     * La frase standard dice CHE COSA non va, non che cosa fare. Per queste tre è la differenza fra
     * un utente che rinuncia e uno che ritrova il proprio account.
     */
    'custom' => [
        'email' => [
            'unique' => 'Esiste già un account con questo indirizzo e-mail. Acceda oppure usi «Password dimenticata».',
        ],
        'accept_terms' => [
            'accepted' => 'Deve accettare le condizioni generali per creare un account.',
        ],
        'terms' => [
            'accepted' => 'Deve accettare le condizioni generali per creare un account.',
        ],
    ],

    /*
     * Senza questa tabella Laravel inserisce la CHIAVE nella frase: «Il campo postal_code è
     * obbligatorio.»
     */
    'attributes' => [
        'accept_terms' => 'condizioni generali',
        'address' => 'indirizzo',
        'city' => 'città',
        'code' => 'codice',
        'company_name' => 'ragione sociale',
        'current_password' => 'password attuale',
        'device_name' => 'dispositivo',
        'email' => 'indirizzo e-mail',
        'name' => 'nome',
        'password' => 'password',
        'password_confirmation' => 'conferma della password',
        'phone' => 'numero di telefono',
        'postal_code' => 'codice postale',
        'provider_company_name' => 'ragione sociale dell’azienda di servizi',
        'terms' => 'condizioni generali',
        'tva_number' => 'partita IVA',
        'two_factor_code' => 'codice di autenticazione',
        'vat_number' => 'partita IVA',
    ],

];
