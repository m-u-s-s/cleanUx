<?php

/**
 * Verifica facciale del professionista — testi che legge il PROFESSIONISTA.
 *
 * La motivazione completa è in `lang/fr/face_check.php`. In breve: qui vive solo ciò che vede un
 * professionista; le schermate di amministrazione restano in francese, come il resto della console.
 *
 * OGNI MODIFICA A `consent.text` RICHIEDE DI INCREMENTARE `FACE_CHECK_CONSENT_VERSION`:
 * un consenso prestato su una versione precedente non copre quella successiva.
 *
 * UNA TRADUZIONE NON È UNA MODIFICA. Questo testo dice la stessa cosa dell’originale francese in
 * un’altra lingua — l’oggetto del consenso non cambia, e nemmeno la versione.
 */
return [

    'consent' => [
        'text' => 'Acconsento a che il mio volto sia registrato e confrontato con il mio documento '
            .'d’identità, al fine di verificare che io sia davvero la persona che si presenta dai '
            .'clienti. Il mio volto di riferimento è conservato cifrato per tutta la durata del mio '
            .'account; le foto scattate durante i controlli sono eliminate dopo :days giorni. Nessun '
            .'cliente vede mai queste immagini. Posso revocare il consenso in qualsiasi momento: il '
            .'mio volto sarà allora eliminato e non potrò più lavorare nei mestieri che richiedono '
            .'questo controllo.',
        'legal_note' => 'Dato biometrico — categoria particolare ai sensi dell’articolo 9 del GDPR.',
        'version_label' => 'Versione del consenso: :version',
        'withdraw_done' => 'Il suo volto di riferimento è stato eliminato. Non potrà lavorare nei '
            .'mestieri che richiedono un controllo d’identità finché non lo registrerà di nuovo.',
    ],

    'gate' => [
        'enrolment_required' => 'Registri il suo volto per poter andare dai clienti. Bastano trenta secondi.',
        'check_required' => 'È richiesto un controllo d’identità prima di proseguire.',
        'check_pending' => 'Il suo controllo è in verifica. Ancora qualche secondo.',
        'blocked' => 'Il suo account è sospeso in seguito a un controllo d’identità. Un amministratore '
            .'deve revocare la sospensione: lo segnali dalla schermata di verifica.',
    ],

    'screen' => [
        'eyebrow_enrolment' => 'Primo passo',
        'eyebrow_check' => 'Controllo d’identità',
        'title_enrolment' => 'Registri il suo volto',
        'title_check' => 'Confermi che è lei',
        'help_enrolment' => 'Questa foto servirà da riferimento. Resta privata: né i suoi clienti né la '
            .'sua azienda la vedono.',
        'help_check' => 'Guardi l’obiettivo, senza occhiali da sole né mascherina. Nessun cliente vedrà questa foto.',
        'liveness_hint' => 'Scatti la foto dal vivo: la foto di uno schermo non supera il controllo.',
        'capture_enrolment' => 'Registra il mio volto',
        'capture_check' => 'Scatta la foto',
        'later' => 'Più tardi',
        'all_good_title' => 'Tutto a posto',
        'all_good_body' => 'La sua identità è verificata. Può lavorare normalmente.',
        'enrolled_since' => 'Volto registrato :when',
        'pending_title' => 'Verifica in corso',
        'pending_body' => 'Ancora qualche secondo. Non chiuda l’applicazione.',
        'blocked_title' => 'Account sospeso',
        'blocked_note' => 'Segnalare un problema non revoca la sospensione: apre una pratica che un '
            .'amministratore prenderà in carico.',
        'not_concerned' => 'Nessuno dei suoi mestieri richiede la verifica facciale nella sua zona. Qui non c’è nulla da fare.',
        'refresh' => 'Aggiorna',
        'attempt_recap' => 'Tentativo :number · motivo precedente: :reason',
    ],

    'camera' => [
        'permission_title' => 'Accesso alla fotocamera',
        'permission_body' => 'Il controllo d’identità richiede la fotocamera frontale. Nessuna immagine è condivisa con i suoi clienti.',
        'permission_action' => 'Consenti la fotocamera',
        'unavailable' => 'Non è stato possibile aprire la fotocamera. La consenta nel suo browser oppure segnali il problema qui sotto.',
        'empty_capture' => 'La fotocamera non ha restituito nulla. Riprovi.',
    ],

    'result' => [
        'passed' => 'Identità confermata. Buona giornata.',
        'enrolled' => 'Il suo volto è stato registrato.',
        'failed_final' => 'Non siamo riusciti a riconoscerla. Un amministratore esaminerà il suo caso.',
        'failed_retry' => 'Non l’abbiamo riconosciuta. Si giri verso la luce. Le restano :left tentativo/i.',
        'liveness_retry' => 'Rilevata la foto di uno schermo. Scatti la foto dal vivo. Tentativo :number di :total.',
        'network' => 'Connessione persa. Verifichi la rete e riprovi.',
        'upload_failed' => 'L’invio non è riuscito. Verifichi la connessione e riprovi.',
    ],

    'errors' => [
        'consent_required' => 'Registrare il suo volto richiede il suo consenso esplicito.',
        'empty_image' => 'Immagine vuota.',
        'check_closed' => 'Questo controllo è già chiuso.',
        'check_expired' => 'Questo controllo è scaduto. Ricominci.',
        'orphan_check' => 'Controllo orfano.',
        'no_open_check' => 'Nessun controllo in corso. Ricarichi la pagina.',
        'default' => 'È richiesto un controllo d’identità prima di proseguire.',
    ],

    'incident' => [
        'title' => 'Il controllo non funziona?',
        'subtitle' => 'Descriva che cosa succede. Un amministratore esaminerà il suo caso.',
        'cta' => 'Non funziona',
        'cta_blocked' => 'Segnala un problema',
        'field_label' => 'Descriva il problema',
        'placeholder' => 'Es.: la fotocamera resta nera quando apro la pagina.',
        'no_unblock_warning' => 'Questa segnalazione non sblocca il suo account. Apre una pratica con '
            .'marca temporale e i dati tecnici del suo dispositivo.',
        'sent_title' => 'Pratica aperta',
        'sent_body' => 'Un amministratore è stato avvisato. Il suo account resta in attesa di verifica: '
            .'questa segnalazione non lo sblocca.',
        'send' => 'Invia',
        'cancel' => 'Annulla',
        'close' => 'Chiudi',
    ],

    'notifications' => [
        'blocked' => [
            'subject' => 'Il suo account è sospeso — controllo d’identità',
            'greeting' => 'Buongiorno :name,',
            'line_reason' => 'Il suo accesso agli interventi è sospeso: :reason',
            'line_action' => 'Un amministratore deve revocare questa sospensione. Non può revocarla lei '
                .'stesso, e nemmeno attendere la revocherà.',
            'line_report' => 'Se il controllo non funziona dalla sua parte, lo segnali dalla schermata '
                .'di verifica: si apre una pratica che un amministratore prenderà in carico.',
            'action' => 'Apri la verifica',
            'reason' => [
                'failed_checks' => 'diversi controlli facciali non sono riusciti.',
                'id_mismatch' => 'il volto registrato non corrisponde alla foto del suo documento d’identità.',
                'consent_withdrawn' => 'ha revocato il consenso al controllo facciale.',
                'admin_decision' => 'decisione di un amministratore.',
                'unknown' => 'un controllo d’identità non è riuscito.',
            ],
        ],
        'unblocked' => [
            'subject' => 'Il suo account è di nuovo attivo',
            'greeting' => 'Buongiorno :name,',
            'line_lifted' => 'Un amministratore ha revocato la sospensione del suo account.',
            'line_next' => 'Alla prossima connessione le sarà chiesto un nuovo controllo: la revoca le '
                .'dà l’occasione di dimostrare la sua identità, non la esonera dal farlo.',
            'action' => 'Torna al lavoro',
        ],
    ],

];
