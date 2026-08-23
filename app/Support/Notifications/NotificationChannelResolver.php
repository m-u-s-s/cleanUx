<?php

namespace App\Support\Notifications;

use App\Models\User;
use App\Services\NotificationPreferences\NotificationPreferenceService;

/** LE PONT ENTRE LES NOTIFICATIONS ET LES PRÉFÉRENCES — il manquait des deux côtés. */
class NotificationChannelResolver
{
    /** Le nom Laravel du canal → le nom qu'emploie la matrice de préférences. */
    private const CANAUX = [
        'mail' => 'email',
        'database' => 'inapp',
        'broadcast' => 'inapp',
        'sms' => 'sms',
        'push' => 'push',
        'webhook' => 'webhook',
    ];

    /** Clé d'événement → catégorie de la matrice. */
    private const CATEGORIES = [
        // Rappels et déroulé d'une intervention
        'booking_reminder' => 'reminder',
        'reminder' => 'reminder',
        'rappel_rendez_vous' => 'reminder',
        'employe_en_route' => 'transactional',
        'employe_arrive' => 'transactional',
        'mission_started' => 'transactional',
        'mission_completed' => 'transactional',
        'presence_code' => 'verification',
        // Controle facial : `verification` comme le code de presence -- c'est la meme famille,
        // et cette categorie est forcee a ON sur l'e-mail (voir notification_preferences.forced_on).
        'face_check_blocked' => 'verification',
        'face_check_unblocked' => 'verification',
        'face_check_incident' => 'support',
        // Un imprévu chez soi n'est pas une nouvelle du service : c'est la mission qui bouge.
        'mission_incident' => 'transactional',
        // Un supplément proposé sur place attend une réponse pour que le travail continue.
        'mission_extra' => 'transactional',

        // Argent
        'invoice_reminder' => 'transactional',
        'payment_receipt' => 'transactional',
        'payout' => 'transactional',

        // Support
        'dispute_opened' => 'support',
        'dispute_updated' => 'support',
        'dispute_resolved' => 'support',
        'feedback_request' => 'product',
        'reassignment_suggestion' => 'support',
        'admin_digest' => 'product',

        // Croissance
        'marketing' => 'marketing',
        'promo' => 'marketing',
    ];

    public function categoriePour(string $eventKey): string
    {
        return self::CATEGORIES[$eventKey] ?? 'transactional';
    }

    public function nomDeMatrice(string $canalLaravel): ?string
    {
        return self::CANAUX[$canalLaravel] ?? null;
    }

    /** Ce destinataire accepte-t-il ce canal pour cet événement ? */
    public function accepte(User $user, string $eventKey, string $canalLaravel): bool
    {
        $canal = $this->nomDeMatrice($canalLaravel);

        if ($canal === null) {
            return true;
        }

        return app(NotificationPreferenceService::class)
            ->isAllowed($user, $canal, $this->categoriePour($eventKey));
    }
}
