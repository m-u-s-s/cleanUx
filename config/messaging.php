<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pièces jointes des messages (Phase 4 messaging)
    |--------------------------------------------------------------------------
    */

    'attachments' => [
        'disk'           => env('MESSAGING_ATTACHMENTS_DISK', 'public'),
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

    'av' => [
        'required' => (bool) env('MESSAGING_AV_REQUIRED', false),
        'engine'   => env('MESSAGING_AV_ENGINE', 'clamav'),
    ],

];
