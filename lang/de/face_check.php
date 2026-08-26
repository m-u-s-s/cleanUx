<?php

/**
 * Gesichtsprüfung für Dienstleister — Texte, die der DIENSTLEISTER liest.
 *
 * Die vollständige Begründung steht in `lang/fr/face_check.php`. Kurz: hier lebt nur, was ein
 * Dienstleister zu sehen bekommt; die Verwaltungsoberfläche bleibt französisch wie der Rest der
 * Konsole.
 *
 * JEDE ÄNDERUNG AN `consent.text` ERFORDERT EINE ERHÖHUNG VON `FACE_CHECK_CONSENT_VERSION`:
 * eine gegen eine ältere Fassung erteilte Einwilligung deckt keine neuere ab.
 *
 * EINE ÜBERSETZUNG IST KEINE ÄNDERUNG. Dieser Text sagt dasselbe wie das französische Original in
 * einer anderen Sprache — der Gegenstand der Einwilligung bleibt derselbe, die Version also auch.
 */
return [

    'consent' => [
        'text' => 'Ich bin damit einverstanden, dass mein Gesicht aufgenommen und mit meinem '
            .'Ausweisdokument abgeglichen wird, um zu prüfen, dass ich wirklich die Person bin, die '
            .'bei Kundinnen und Kunden erscheint. Mein Referenzgesicht wird verschlüsselt gespeichert, '
            .'solange mein Konto besteht; die bei den Prüfungen aufgenommenen Fotos werden nach '
            .':days Tagen gelöscht. Kein Kunde sieht diese Bilder jemals. Ich kann meine Einwilligung '
            .'jederzeit widerrufen: mein Gesicht wird dann gelöscht und ich kann nicht mehr in den '
            .'Gewerken arbeiten, die diese Prüfung verlangen.',
        'legal_note' => 'Biometrische Daten — besondere Kategorie nach Artikel 9 DSGVO.',
        'version_label' => 'Fassung der Einwilligung: :version',
        'withdraw_done' => 'Ihr Referenzgesicht wurde gelöscht. Sie können in Gewerken mit '
            .'Identitätsprüfung erst wieder arbeiten, wenn Sie es erneut hinterlegen.',
    ],

    'gate' => [
        'enrolment_required' => 'Hinterlegen Sie Ihr Gesicht, um bei Kunden erscheinen zu können. Das dauert dreißig Sekunden.',
        'check_required' => 'Vor dem Fortfahren ist eine Identitätsprüfung erforderlich.',
        'check_pending' => 'Ihre Prüfung wird ausgewertet. Nur noch wenige Sekunden.',
        'blocked' => 'Ihr Konto ist nach einer Identitätsprüfung gesperrt. Ein Administrator muss die '
            .'Sperre aufheben: melden Sie es über den Prüfungsbildschirm.',
    ],

    'screen' => [
        'eyebrow_enrolment' => 'Erster Schritt',
        'eyebrow_check' => 'Identitätsprüfung',
        'title_enrolment' => 'Gesicht hinterlegen',
        'title_check' => 'Bestätigen Sie, dass Sie es sind',
        'help_enrolment' => 'Dieses Foto dient als Referenz. Es bleibt privat: weder Ihre Kunden noch '
            .'Ihr Unternehmen sehen es.',
        'help_check' => 'Schauen Sie in die Linse, ohne Sonnenbrille und ohne Maske. Kein Kunde sieht dieses Foto.',
        'liveness_hint' => 'Nehmen Sie das Foto live auf: ein abfotografierter Bildschirm besteht die Prüfung nicht.',
        'capture_enrolment' => 'Mein Gesicht hinterlegen',
        'capture_check' => 'Foto aufnehmen',
        'later' => 'Später',
        'all_good_title' => 'Alles in Ordnung',
        'all_good_body' => 'Ihre Identität ist bestätigt. Sie können normal arbeiten.',
        'enrolled_since' => 'Gesicht hinterlegt :when',
        'pending_title' => 'Prüfung läuft',
        'pending_body' => 'Nur noch wenige Sekunden. Schließen Sie die App nicht.',
        'blocked_title' => 'Konto gesperrt',
        'blocked_note' => 'Eine Problemmeldung hebt die Sperre nicht auf: sie eröffnet einen Vorgang, '
            .'den ein Administrator bearbeitet.',
        'not_concerned' => 'Keines Ihrer Gewerke verlangt in Ihrem Gebiet eine Gesichtsprüfung. Hier ist nichts zu tun.',
        'refresh' => 'Aktualisieren',
        'attempt_recap' => 'Versuch :number · vorheriger Grund: :reason',
    ],

    'camera' => [
        'permission_title' => 'Kamerazugriff',
        'permission_body' => 'Die Identitätsprüfung benötigt die Frontkamera. Kein Bild wird mit Ihren Kunden geteilt.',
        'permission_action' => 'Kamera erlauben',
        'unavailable' => 'Die Kamera konnte nicht geöffnet werden. Erlauben Sie sie in Ihrem Browser oder melden Sie das Problem unten.',
        'empty_capture' => 'Die Kamera hat nichts geliefert. Versuchen Sie es erneut.',
    ],

    'result' => [
        'passed' => 'Identität bestätigt. Einen guten Tag.',
        'enrolled' => 'Ihr Gesicht wurde hinterlegt.',
        'failed_final' => 'Wir konnten Sie nicht erkennen. Ein Administrator prüft Ihren Fall.',
        'failed_retry' => 'Wir haben Sie nicht erkannt. Drehen Sie sich zum Licht. Sie haben noch :left Versuch(e).',
        'liveness_retry' => 'Abfotografierter Bildschirm erkannt. Nehmen Sie das Foto live auf. Versuch :number von :total.',
        'network' => 'Verbindung unterbrochen. Prüfen Sie Ihr Netz und versuchen Sie es erneut.',
        'upload_failed' => 'Das Hochladen ist fehlgeschlagen. Prüfen Sie Ihre Verbindung und versuchen Sie es erneut.',
    ],

    'errors' => [
        'consent_required' => 'Das Hinterlegen Ihres Gesichts erfordert Ihre ausdrückliche Einwilligung.',
        'empty_image' => 'Leeres Bild.',
        'check_closed' => 'Diese Prüfung ist bereits abgeschlossen.',
        'check_expired' => 'Diese Prüfung ist abgelaufen. Beginnen Sie von vorn.',
        'orphan_check' => 'Verwaiste Prüfung.',
        'no_open_check' => 'Keine Prüfung im Gange. Laden Sie die Seite neu.',
        'default' => 'Vor dem Fortfahren ist eine Identitätsprüfung erforderlich.',
    ],

    'incident' => [
        'title' => 'Die Prüfung funktioniert nicht?',
        'subtitle' => 'Beschreiben Sie, was passiert. Ein Administrator sieht sich Ihren Fall an.',
        'cta' => 'Es funktioniert nicht',
        'cta_blocked' => 'Problem melden',
        'field_label' => 'Beschreiben Sie das Problem',
        'placeholder' => 'Z. B.: die Kamera bleibt schwarz, wenn ich die Seite öffne.',
        'no_unblock_warning' => 'Diese Meldung entsperrt Ihr Konto nicht. Sie eröffnet einen mit '
            .'Zeitstempel versehenen Vorgang samt technischer Angaben zu Ihrem Gerät.',
        'sent_title' => 'Vorgang eröffnet',
        'sent_body' => 'Ein Administrator wurde benachrichtigt. Ihr Konto bleibt in Prüfung: diese '
            .'Meldung entsperrt es nicht.',
        'send' => 'Senden',
        'cancel' => 'Abbrechen',
        'close' => 'Schließen',
    ],

    'notifications' => [
        'blocked' => [
            'subject' => 'Ihr Konto ist gesperrt — Identitätsprüfung',
            'greeting' => 'Hallo :name,',
            'line_reason' => 'Ihr Zugang zu Einsätzen ist gesperrt: :reason',
            'line_action' => 'Ein Administrator muss diese Sperre aufheben. Sie können sie nicht selbst '
                .'aufheben, und Abwarten hebt sie ebenfalls nicht auf.',
            'line_report' => 'Wenn die Prüfung bei Ihnen nicht funktioniert, melden Sie es über den '
                .'Prüfungsbildschirm: das eröffnet einen Vorgang, den ein Administrator bearbeitet.',
            'action' => 'Prüfung öffnen',
            'reason' => [
                'failed_checks' => 'mehrere Gesichtsprüfungen sind nicht gelungen.',
                'id_mismatch' => 'das hinterlegte Gesicht stimmt nicht mit dem Porträt auf Ihrem Ausweisdokument überein.',
                'consent_withdrawn' => 'Sie haben Ihre Einwilligung zur Gesichtsprüfung widerrufen.',
                'admin_decision' => 'Entscheidung eines Administrators.',
                'unknown' => 'eine Identitätsprüfung ist nicht gelungen.',
            ],
        ],
        'unblocked' => [
            'subject' => 'Ihr Konto ist wieder aktiv',
            'greeting' => 'Hallo :name,',
            'line_lifted' => 'Ein Administrator hat die Sperre Ihres Kontos aufgehoben.',
            'line_next' => 'Beim nächsten Onlinegehen wird eine neue Prüfung verlangt: die Aufhebung '
                .'gibt Ihnen die Gelegenheit, Ihre Identität nachzuweisen, sie befreit Sie nicht davon.',
            'action' => 'Zurück an die Arbeit',
        ],
    ],

];
