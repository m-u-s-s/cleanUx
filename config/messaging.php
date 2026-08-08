<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pièces jointes des messages (Phase 4 messaging)
    |--------------------------------------------------------------------------
    */

    'attachments' => [
        'disk' => env('MESSAGING_ATTACHMENTS_DISK', 'public'),
        'max_size_bytes' => (int) env('MESSAGING_MAX_SIZE_BYTES', 25 * 1024 * 1024),
    ],

    /*
    |--------------------------------------------------------------------------
    | Anti-malware
    |--------------------------------------------------------------------------
    | Si 'required' = true, les attachments restent en av_status = 'pending'
    | jusqu'au scan, et MessageAttachment::isReady() ne les considère pas
    | comme prêts. À activer en prod avec un job ScanAttachmentForMalware
    | qui appelle ClamAV ou un service tiers.
    */

    /*
    |--------------------------------------------------------------------------
    | Modération des canaux internes
    |--------------------------------------------------------------------------
    | `ModerationService` (ChatV2) n'examinait que le fil client↔prestataire. Les canaux internes
    | n'avaient AUCUNE modération : une équipe pouvait y coller le numéro de carte d'un client sans
    | que rien ne le voie — alors que ces canaux existent précisément pour éviter que les échanges
    | partent sur WhatsApp, hors de toute trace.
    |
    | Les messages SYSTÈME en sont toujours exclus : ce sont des textes que le produit écrit
    | lui-même, et les caviarder troue des annonces techniques.
    */
    'moderation' => [
        'channels' => (bool) env('MESSAGING_MODERATION_CHANNELS', true),
    ],

    'av' => [
        'required' => (bool) env('MESSAGING_AV_REQUIRED', false),
        'engine' => env('MESSAGING_AV_ENGINE', 'clamav'),
    ],

];
