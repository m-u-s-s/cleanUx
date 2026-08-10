<?php

namespace App\Support\Notifications;

use App\Models\User;
use App\Services\NotificationPreferences\NotificationPreferenceService;

/**
 * LE PONT ENTRE LES NOTIFICATIONS ET LES PRÉFÉRENCES — il manquait des deux côtés.
 *
 * `InteractsWithUserNotificationPreferences::preferredChannels()` demandait au destinataire
 * `wantsNotificationChannel()`… une méthode qui n'existait sur AUCUN modèle. Le garde
 * `method_exists()` renvoyait donc toujours faux, et la fonction rendait ses valeurs par défaut
 * telles quelles. Résultat : la matrice de préférences — versionnée, auditée, dotée d'un écran de
 * réglage et d'une notion de « forcé légalement » — n'était consultée par aucun envoi. Un
 * utilisateur qui coupait les rappels par courriel continuait de les recevoir.
 *
 * DEUX VOCABULAIRES SE PARLENT ICI. Laravel nomme ses canaux `mail`, `database`, `sms`, `push` ;
 * la matrice les nomme `email`, `inapp`, `sms`, `push`. Et les notifications se désignent par une
 * clé d'événement (`dispute_opened`, `booking_reminder`) là où la matrice raisonne par catégorie
 * (`support`, `reminder`). La traduction est faite ici, en un seul endroit : la disperser dans
 * chaque notification aurait garanti la divergence.
 *
 * LE DOUTE PROFITE À L'ENVOI. Une clé d'événement inconnue est traitée comme transactionnelle —
 * la catégorie que l'utilisateur ne peut pas couper sur les canaux légaux. Se tromper dans ce sens
 * envoie un message de trop ; se tromper dans l'autre fait manquer au client le code dont il a
 * besoin pour ouvrir sa porte au prestataire.
 */
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

    /**
     * Clé d'événement → catégorie de la matrice.
     *
     * Ce qui n'y figure pas retombe sur `transactional` : voir l'en-tête, le doute profite à
     * l'envoi.
     */
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

    /**
     * Ce destinataire accepte-t-il ce canal pour cet événement ?
     *
     * Un canal que la matrice ne connaît pas (un canal maison) passe : ce n'est pas à ce pont de
     * décider du sort de ce qu'il ne sait pas lire.
     */
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
