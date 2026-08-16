<?php

/**
 * Gezichtscontrole van dienstverleners — teksten die de DIENSTVERLENER leest.
 *
 * Zie `lang/fr/face_check.php` voor de volledige toelichting. Kort: alleen wat een dienstverlener
 * ziet staat hier ; de beheerschermen blijven in het Frans, zoals de rest van de console.
 *
 * ELKE WIJZIGING AAN `consent.text` VEREIST EEN NIEUWE `FACE_CHECK_CONSENT_VERSION` :
 * een toestemming gegeven op een oudere versie dekt een nieuwe versie niet.
 */
return [

    'consent' => [
        'text' => 'Ik ga ermee akkoord dat mijn gezicht wordt geregistreerd en vergeleken met mijn '
            .'identiteitsbewijs, om te controleren dat ik wel degelijk de persoon ben die bij klanten '
            .'langsgaat. Mijn referentiegezicht wordt versleuteld bewaard zolang mijn account bestaat ; '
            .'de foto’s van de controles worden na :days dagen gewist. Geen enkele klant ziet deze '
            .'beelden. Ik kan mijn toestemming op elk moment intrekken : mijn gezicht wordt dan '
            .'verwijderd en ik kan niet langer werken in de beroepen die deze controle vereisen.',
        'legal_note' => 'Biometrische gegevens — bijzondere categorie in de zin van artikel 9 AVG.',
        'version_label' => 'Versie van de toestemming: :version',
        'withdraw_done' => 'Uw referentiegezicht is verwijderd. U kunt niet langer werken in de beroepen '
            .'die een identiteitscontrole vereisen zolang u het niet opnieuw registreert.',
    ],

    'gate' => [
        'enrolment_required' => 'Registreer uw gezicht om bij klanten te kunnen werken. Het duurt dertig seconden.',
        'check_required' => 'Een controle van uw identiteit is nodig voor u verdergaat.',
        'check_pending' => 'Uw controle wordt geverifieerd. Nog even geduld.',
        'blocked' => 'Uw account is geschorst na een identiteitscontrole. Een beheerder moet de schorsing '
            .'opheffen: meld het via het verificatiescherm.',
    ],

    'screen' => [
        'eyebrow_enrolment' => 'Eerste stap',
        'eyebrow_check' => 'Identiteitscontrole',
        'title_enrolment' => 'Registreer uw gezicht',
        'title_check' => 'Bevestig dat u het bent',
        'help_enrolment' => 'Deze foto dient als referentie. Ze blijft privé: noch uw klanten noch uw '
            .'vennootschap zien ze.',
        'help_check' => 'Kijk in de lens, zonder zonnebril of mondmasker. Geen enkele klant ziet deze foto.',
        'liveness_hint' => 'Neem de foto live: een foto van een scherm doorstaat de controle niet.',
        'capture_enrolment' => 'Mijn gezicht registreren',
        'capture_check' => 'Foto nemen',
        'later' => 'Later',
        'all_good_title' => 'Alles in orde',
        'all_good_body' => 'Uw identiteit is geverifieerd. U kunt normaal werken.',
        'enrolled_since' => 'Gezicht geregistreerd :when',
        'pending_title' => 'Verificatie bezig',
        'pending_body' => 'Nog even geduld. Sluit de app niet.',
        'blocked_title' => 'Account geschorst',
        'blocked_note' => 'Een probleem melden heft de schorsing niet op: het opent een dossier dat een '
            .'beheerder behandelt.',
        'not_concerned' => 'Geen van uw beroepen vereist een gezichtscontrole in uw zone. Hier valt niets te doen.',
        'refresh' => 'Vernieuwen',
        'attempt_recap' => 'Poging :number · vorige reden: :reason',
    ],

    'camera' => [
        'permission_title' => 'Toegang tot de camera',
        'permission_body' => 'De identiteitscontrole heeft de camera aan de voorzijde nodig. Geen enkel '
            .'beeld wordt met uw klanten gedeeld.',
        'permission_action' => 'Camera toestaan',
        'unavailable' => 'De camera kan niet worden geopend. Sta ze toe in uw browser, of meld het '
            .'probleem hieronder.',
        'empty_capture' => 'De camera gaf niets terug. Probeer opnieuw.',
    ],

    'result' => [
        'passed' => 'Identiteit bevestigd. Fijne dag.',
        'enrolled' => 'Uw gezicht is geregistreerd.',
        'failed_final' => 'We konden u niet herkennen. Een beheerder bekijkt uw dossier.',
        'failed_retry' => 'We hebben u niet herkend. Ga in het licht staan. U hebt nog :left poging(en).',
        'liveness_retry' => 'Schermfoto gedetecteerd. Neem de foto live. Poging :number van :total.',
        'network' => 'Verbinding verbroken. Controleer uw netwerk en probeer opnieuw.',
        'upload_failed' => 'Het versturen is mislukt. Controleer uw verbinding en probeer opnieuw.',
    ],

    'errors' => [
        'consent_required' => 'Het registreren van uw gezicht vereist uw uitdrukkelijke toestemming.',
        'empty_image' => 'Lege afbeelding.',
        'check_closed' => 'Deze controle is al afgesloten.',
        'check_expired' => 'Deze controle is verlopen. Begin opnieuw.',
        'orphan_check' => 'Verweesde controle.',
        'no_open_check' => 'Geen lopende controle. Herlaad de pagina.',
        'default' => 'Een identiteitscontrole is nodig voor u verdergaat.',
    ],

    'incident' => [
        'title' => 'Werkt de controle niet?',
        'subtitle' => 'Beschrijf wat er gebeurt. Een beheerder bekijkt uw dossier.',
        'cta' => 'Het werkt niet',
        'cta_blocked' => 'Een probleem melden',
        'field_label' => 'Beschrijf het probleem',
        'placeholder' => 'Bv.: de camera blijft zwart wanneer ik de pagina open.',
        'no_unblock_warning' => 'Deze melding deblokkeert uw account niet. Ze opent een gedateerd dossier '
            .'met de technische gegevens van uw toestel.',
        'sent_title' => 'Dossier geopend',
        'sent_body' => 'Een beheerder is verwittigd. Uw account blijft in afwachting van verificatie: '
            .'deze melding deblokkeert het niet.',
        'send' => 'Versturen',
        'cancel' => 'Annuleren',
        'close' => 'Sluiten',
    ],

    'notifications' => [
        'blocked' => [
            'subject' => 'Uw account is geschorst — identiteitscontrole',
            'greeting' => 'Dag :name,',
            'line_reason' => 'Uw toegang tot opdrachten is geschorst: :reason',
            'line_action' => 'Een beheerder moet deze schorsing opheffen. U kunt dat niet zelf, en wachten '
                .'helpt evenmin.',
            'line_report' => 'Werkt de controle bij u niet? Meld het via het verificatiescherm: dat opent '
                .'een dossier dat een beheerder behandelt.',
            'action' => 'Verificatie openen',
            'reason' => [
                'failed_checks' => 'meerdere gezichtscontroles zijn mislukt.',
                'id_mismatch' => 'het geregistreerde gezicht komt niet overeen met de foto op uw identiteitsbewijs.',
                'consent_withdrawn' => 'u hebt uw toestemming voor de gezichtscontrole ingetrokken.',
                'admin_decision' => 'beslissing van een beheerder.',
                'unknown' => 'een identiteitscontrole is mislukt.',
            ],
        ],
        'unblocked' => [
            'subject' => 'Uw account is opnieuw actief',
            'greeting' => 'Dag :name,',
            'line_lifted' => 'Een beheerder heeft de schorsing van uw account opgeheven.',
            'line_next' => 'Bij uw volgende aanmelding wordt een nieuwe controle gevraagd: de opheffing '
                .'geeft u de kans uw identiteit te bewijzen, ze stelt u er niet van vrij.',
            'action' => 'Weer aan het werk',
        ],
    ],

];
