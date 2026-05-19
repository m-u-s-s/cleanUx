<?php

return [
    'enabled' => env('CHAT_V2_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Message constraints
    |--------------------------------------------------------------------------
    */
    'max_message_length' => (int) env('CHAT_MAX_MESSAGE_LENGTH', 4096),
    'min_message_length' => 1,

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    */
    'attachments_enabled' => env('CHAT_ATTACHMENTS_ENABLED', true),
    'attachments_disk' => env('CHAT_ATTACHMENTS_DISK', 'local'),
    'attachments_path_prefix' => env('CHAT_ATTACHMENTS_PATH', 'chat_attachments'),
    'max_attachment_size_kb' => (int) env('CHAT_MAX_ATTACHMENT_KB', 5120),  // 5 MB
    'allowed_mime_types' => [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
        'application/pdf',
        'text/plain', 'text/csv',
    ],

    /*
    |--------------------------------------------------------------------------
    | Moderation
    |--------------------------------------------------------------------------
    */
    'moderation' => [
        // Active la redaction PII automatique (email, téléphone, IBAN, etc.)
        'pii_redaction_enabled' => env('CHAT_PII_REDACTION', true),

        // Active le blocage messages avec mots toxiques whitelistés
        'toxic_block_enabled' => env('CHAT_TOXIC_BLOCK', true),

        // Patterns regex pour redaction PII (replaced par [REDACTED:type]).
        // Ordre important : motifs les plus spécifiques d'abord pour éviter
        // que `phone` ne mange les chiffres d'un IBAN/CB avant qu'ils soient matchés.
        'pii_patterns' => [
            'email' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
            'iban' => '/\b[A-Z]{2}[0-9]{2}\s?(?:[A-Z0-9]{4}\s?){3,7}[A-Z0-9]{1,4}\b/',
            'credit_card' => '/\b[0-9]{4}[\s-]?[0-9]{4}[\s-]?[0-9]{4}[\s-]?[0-9]{4}\b/',
            'phone' => '/\b(?:\+?[0-9]{1,3}[\s.-]?)?\(?[0-9]{2,4}\)?[\s.-]?[0-9]{2,4}[\s.-]?[0-9]{2,4}(?:[\s.-]?[0-9]{2,4})?\b/',
        ],

        // Whitelist mots toxiques (insensitive). Si l'un d'eux apparaît → block.
        'toxic_words' => [
            'kill', 'die', 'idiot', 'stupid',
            'connard', 'salaud', 'salope', 'pute',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcast
    |--------------------------------------------------------------------------
    | Channel format : "chat.thread.{thread_id}" (private channel)
    */
    'broadcast_enabled' => env('CHAT_BROADCAST_ENABLED', true),
    'broadcast_channel_format' => 'chat.thread.{thread_id}',

    /*
    |--------------------------------------------------------------------------
    | Auto-archive threads inactifs après N jours
    |--------------------------------------------------------------------------
    */
    'auto_archive_after_days' => (int) env('CHAT_AUTO_ARCHIVE_DAYS', 90),
    'auto_close_on_booking_completed' => env('CHAT_AUTO_CLOSE_BOOKING_COMPLETED', true),

    /*
    |--------------------------------------------------------------------------
    | Allowed context types pour démarrer un thread
    |--------------------------------------------------------------------------
    */
    'allowed_context_types' => ['booking', 'dispute', 'admin', 'generic'],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */
    'messages_per_page' => 50,
];
