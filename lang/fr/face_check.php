<?php

/**
 * Vérification faciale des prestataires — textes vus par le PRESTATAIRE.
 *
 * PÉRIMÈTRE : ce fichier ne couvre que ce qu'un prestataire lit, sur les deux surfaces (web
 * Livewire et application mobile). Les écrans d'administration restent en français comme tout le
 * reste de la console — les traduire ici donnerait l'illusion d'une couverture qui n'existe nulle
 * part ailleurs dans l'administration.
 *
 * LE TEXTE DE CONSENTEMENT VIT ICI, ET NULLE PART AILLEURS. C'est le seul texte du module qui a
 * une portée juridique : il est servi par l'API (`GET /provider/face-check/status`) pour que
 * l'application mobile affiche EXACTEMENT le même que le web. Deux copies d'un texte de
 * consentement finiraient par diverger, et c'est celle qu'on n'a pas relue qui serait affichée.
 *
 * TOUTE MODIFICATION DE `consent.text` EXIGE D'INCRÉMENTER `FACE_CHECK_CONSENT_VERSION` :
 * un consentement donné sur une ancienne version n'en couvre pas une nouvelle.
 */
return [

    // ── Consentement (art. 9 RGPD) ───────────────────────────────────────────
    'consent' => [
        'text' => "J'accepte que mon visage soit enregistré et comparé à ma pièce d'identité afin de "
            .'vérifier que je suis bien la personne qui intervient chez les clients. Mon visage de '
            .'référence est conservé de façon chiffrée tant que mon compte existe ; les photos prises '
            .'lors des contrôles sont effacées au bout de :days jours. Aucun client ne voit ces images. '
            .'Je peux retirer mon accord à tout moment : mon visage sera alors supprimé et je ne '
            .'pourrai plus intervenir sur les métiers qui exigent ce contrôle.',
        'legal_note' => 'Donnée biométrique — catégorie particulière au sens de l’article 9 du RGPD.',
        'version_label' => 'Version du consentement : :version',
        'withdraw_done' => 'Votre visage de référence a été supprimé. Vous ne pourrez plus intervenir '
            ."sur les métiers qui exigent un contrôle d'identité tant que vous ne l'aurez pas ré-enregistré.",
    ],

    // ── Verdicts de la porte ─────────────────────────────────────────────────
    'gate' => [
        'enrolment_required' => 'Enregistrez votre visage pour pouvoir intervenir chez des clients. '
            ."C'est l'affaire de trente secondes.",
        'check_required' => 'Un contrôle de votre identité est nécessaire avant de continuer.',
        'check_pending' => 'Votre contrôle est en cours de vérification. Encore quelques secondes.',
        'blocked' => "Votre compte est suspendu à la suite d'un contrôle d'identité. Un administrateur "
            .'doit lever la suspension : signalez-le depuis l’écran de vérification.',
    ],

    // ── Écrans ───────────────────────────────────────────────────────────────
    'screen' => [
        'eyebrow_enrolment' => 'Première étape',
        'eyebrow_check' => "Vérification d'identité",
        'title_enrolment' => 'Enregistrez votre visage',
        'title_check' => 'Confirmez que c’est bien vous',
        'help_enrolment' => 'Cette photo servira de référence. Elle reste privée : ni vos clients ni '
            .'votre société ne la voient.',
        'help_check' => 'Regardez l’objectif, sans lunettes de soleil ni masque. Aucun client ne verra '
            .'cette photo.',
        'liveness_hint' => 'Prenez la photo en direct : une photo d’écran ne passe pas le contrôle.',
        'capture_enrolment' => 'Enregistrer mon visage',
        'capture_check' => 'Prendre la photo',
        'later' => 'Plus tard',
        'all_good_title' => 'Tout est en règle',
        'all_good_body' => 'Votre identité est vérifiée. Vous pouvez travailler normalement.',
        'enrolled_since' => 'Visage enregistré :when',
        'pending_title' => 'Vérification en cours',
        'pending_body' => 'Encore quelques secondes. Ne fermez pas l’application.',
        'blocked_title' => 'Compte suspendu',
        'blocked_note' => 'Signaler un problème ne lève pas la suspension : cela ouvre un dossier qu’un '
            .'administrateur traitera.',
        'not_concerned' => "Aucun de vos métiers n'exige de vérification faciale dans votre zone. Rien à faire ici.",
        'refresh' => 'Actualiser',
        'attempt_recap' => 'Essai :number · motif précédent : :reason',
    ],

    // ── Caméra ───────────────────────────────────────────────────────────────
    'camera' => [
        'permission_title' => 'Accès à la caméra',
        'permission_body' => "La vérification d'identité a besoin de la caméra frontale. Aucune image "
            ."n'est partagée avec vos clients.",
        'permission_action' => 'Autoriser la caméra',
        'unavailable' => 'Impossible d’ouvrir la caméra. Autorisez-la dans votre navigateur, ou signalez '
            .'le problème ci-dessous.',
        'empty_capture' => 'La caméra n’a rien renvoyé. Réessayez.',
    ],

    // ── Résultats ────────────────────────────────────────────────────────────
    'result' => [
        'passed' => 'Identité confirmée. Bonne journée.',
        'enrolled' => 'Votre visage a été enregistré.',
        'failed_final' => "Nous n'avons pas pu vous reconnaître. Un administrateur va examiner votre dossier.",
        'failed_retry' => 'Nous ne vous avons pas reconnu. Placez-vous face à la lumière. Il vous reste '
            .':left essai(s).',
        'liveness_retry' => 'Photo d’écran détectée. Prenez la photo en direct. Essai :number sur :total.',
        'network' => 'Connexion perdue. Vérifiez votre réseau et réessayez.',
        'upload_failed' => "L'envoi a échoué. Vérifiez votre connexion et réessayez.",
    ],

    // ── Refus levés par les services ─────────────────────────────────────────
    'errors' => [
        'consent_required' => "L'enregistrement de votre visage exige votre accord explicite.",
        'empty_image' => 'Image vide.',
        'check_closed' => 'Ce contrôle est déjà clos.',
        'check_expired' => 'Ce contrôle a expiré. Recommencez.',
        'orphan_check' => 'Contrôle orphelin.',
        'no_open_check' => 'Aucun contrôle en cours. Rechargez la page.',
        'default' => "Un contrôle d'identité est nécessaire avant de continuer.",
    ],

    // ── Signalement de panne ─────────────────────────────────────────────────
    'incident' => [
        'title' => 'Le contrôle ne fonctionne pas ?',
        'subtitle' => 'Décrivez ce qui se passe. Un administrateur regardera votre dossier.',
        'cta' => 'Ça ne marche pas',
        'cta_blocked' => 'Signaler un problème',
        'field_label' => 'Décrivez le problème',
        'placeholder' => "Ex. : la caméra reste noire quand j'ouvre la page.",
        'no_unblock_warning' => 'Ce signalement ne débloque pas votre compte. Il ouvre un dossier '
            .'horodaté avec les informations techniques de votre appareil.',
        'sent_title' => 'Dossier ouvert',
        'sent_body' => 'Un administrateur a été prévenu. Votre compte reste en attente de vérification : '
            .'ce signalement ne le débloque pas.',
        'send' => 'Envoyer',
        'cancel' => 'Annuler',
        'close' => 'Fermer',
    ],

    // ── Notifications au prestataire ─────────────────────────────────────────
    'notifications' => [
        'blocked' => [
            'subject' => 'Votre compte est suspendu — vérification d’identité',
            'greeting' => 'Bonjour :name,',
            'line_reason' => 'Votre accès aux missions est suspendu : :reason',
            'line_action' => 'Un administrateur doit lever cette suspension. Vous ne pouvez pas la lever '
                .'vous-même, et attendre ne la lèvera pas non plus.',
            'line_report' => 'Si le contrôle ne fonctionne pas de votre côté, signalez-le depuis l’écran '
                .'de vérification : cela ouvre un dossier qu’un administrateur traitera.',
            'action' => 'Ouvrir la vérification',
            'reason' => [
                'failed_checks' => 'plusieurs contrôles faciaux n’ont pas abouti.',
                'id_mismatch' => 'le visage enregistré ne correspond pas au portrait de votre pièce d’identité.',
                'consent_withdrawn' => 'vous avez retiré votre consentement au contrôle facial.',
                'admin_decision' => 'décision d’un administrateur.',
                'unknown' => 'un contrôle d’identité n’a pas abouti.',
            ],
        ],
        'unblocked' => [
            'subject' => 'Votre compte est de nouveau actif',
            'greeting' => 'Bonjour :name,',
            'line_lifted' => 'Un administrateur a levé la suspension de votre compte.',
            'line_next' => 'Un nouveau contrôle vous sera demandé dès votre prochaine mise en ligne : '
                .'la levée vous rend la possibilité de prouver votre identité, elle ne vous en dispense pas.',
            'action' => 'Reprendre le travail',
        ],
    ],

];
