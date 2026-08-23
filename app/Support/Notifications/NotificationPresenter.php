<?php

namespace App\Support\Notifications;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

class NotificationPresenter
{
    public function typeKey(DatabaseNotification $notification): string
    {
        $payload = (array) ($notification->data ?? []);

        if (! empty($payload['type'])) {
            return (string) $payload['type'];
        }

        $class = class_basename((string) $notification->type);
        $message = mb_strtolower((string) ($payload['message'] ?? ''));

        return match (true) {
            str_contains($class, 'Feedback') => 'feedback',
            str_contains($class, 'Finance') => 'finance',
            str_contains($class, 'Urgence') || str_contains($message, 'urgence') => 'urgent',
            str_contains($class, 'Safety') || str_contains($class, 'Securite') => 'safety',
            str_contains($class, 'Admin') => 'admin',
            str_contains($class, 'Calendar') => 'calendar',
            str_contains($class, 'Rappel'), str_contains($class, 'Rdv'), str_contains($class, 'Rendez'), array_key_exists('rdv_id', $payload) => 'rendezvous',
            default => 'system',
        };
    }

    /** CE QUE CHAQUE ÉVÉNEMENT DIT, QUAND IL NE LE DIT PAS LUI-MÊME. */
    private const PAR_TYPE = [
        'mission_started' => ['Intervention', 'Intervention démarrée', 'Le prestataire a commencé l’intervention.'],
        'employee_en_route' => ['Intervention', 'Prestataire en route', 'Le prestataire est en route vers l’adresse.'],
        'employee_arrived' => ['Intervention', 'Prestataire arrivé', 'Le prestataire est arrivé sur place.'],
        'mission.offer' => ['Mission', 'Nouvelle mission', 'Une mission vous est proposée.'],
        'asap_search_outcome' => ['Recherche', 'Recherche immédiate', 'Le résultat de votre recherche immédiate est disponible.'],
        'kyc_completed' => ['Vérification', 'Vérification terminée', 'Votre vérification d’identité est terminée.'],
        'safety' => ['Sécurité', 'Alerte sécurité', 'Une alerte de sécurité a été signalée.'],
    ];

    public function label(DatabaseNotification $notification): string
    {
        $connu = self::PAR_TYPE[$this->typeKey($notification)][0] ?? null;

        if ($connu !== null) {
            return $connu;
        }

        return match ($this->typeKey($notification)) {
            'feedback' => 'Feedback',
            'finance' => 'Finance',
            'urgent' => 'Urgent',
            'admin' => 'Admin',
            'calendar' => 'Agenda',
            'rendezvous' => 'Rendez-vous',
            default => 'Système',
        };
    }

    public function severity(DatabaseNotification $notification): string
    {
        $payload = (array) ($notification->data ?? []);

        return (string) ($payload['severity'] ?? match ($this->typeKey($notification)) {
            'urgent' => 'danger',
            'finance' => 'warning',
            'admin', 'calendar' => 'info',
            default => 'default',
        });
    }

    public function severityClasses(DatabaseNotification $notification, bool $unread = false): string
    {
        return match ($this->severity($notification)) {
            'success' => $unread ? 'border-emerald-200 bg-emerald-50' : 'border-emerald-100 bg-white',
            'warning' => $unread ? 'border-amber-200 bg-amber-50' : 'border-amber-100 bg-white',
            'danger' => $unread ? 'border-red-200 bg-red-50' : 'border-red-100 bg-white',
            'info' => $unread ? 'border-blue-200 bg-blue-50' : 'border-slate-200 bg-white',
            default => $unread ? 'border-slate-300 bg-slate-50' : 'border-slate-200 bg-white',
        };
    }

    public function title(DatabaseNotification $notification): string
    {
        $payload = (array) ($notification->data ?? []);

        if (filled($payload['title'] ?? null)) {
            return (string) $payload['title'];
        }

        return self::PAR_TYPE[$this->typeKey($notification)][1] ?? $this->label($notification);
    }

    public function message(DatabaseNotification $notification): string
    {
        $payload = (array) ($notification->data ?? []);

        if (filled($payload['message'] ?? null)) {
            return (string) $payload['message'];
        }

        $texte = self::PAR_TYPE[$this->typeKey($notification)][2] ?? null;

        if ($texte === null) {
            return 'Notification';
        }

        // NOMMER LA PERSONNE QUAND ON LA CONNAÎT.
        $prestataire = $payload['employee_name'] ?? $payload['provider_name'] ?? null;

        if (filled($prestataire)) {
            $texte = match ($this->typeKey($notification)) {
                'mission_started' => $prestataire.' a commencé l’intervention.',
                'employee_en_route' => $prestataire.' est en route vers l’adresse.',
                'employee_arrived' => $prestataire.' est arrivé sur place.',
                default => $texte,
            };
        }

        $reference = $payload['booking_reference'] ?? null;

        return filled($reference) ? $texte.' (réservation '.$reference.')' : $texte;
    }

    public function context(DatabaseNotification $notification): array
    {
        $payload = (array) ($notification->data ?? []);

        return array_filter([
            'rdv_id' => $payload['rdv_id'] ?? null,
            'invoice_number' => $payload['invoice_number'] ?? null,
            'zone' => $payload['zone_name'] ?? null,
            'service' => $payload['service_label'] ?? null,
            'google_email' => $payload['google_email'] ?? null,
        ], static fn ($value) => filled($value));
    }

    public function searchableText(DatabaseNotification $notification): string
    {
        $payload = (array) ($notification->data ?? []);

        return mb_strtolower(implode(' ', array_filter([
            $this->label($notification),
            $this->title($notification),
            $this->message($notification),
            $payload['zone_name'] ?? null,
            $payload['service_label'] ?? null,
            $payload['invoice_number'] ?? null,
            $payload['google_email'] ?? null,
            $payload['booking_reference'] ?? null,
            $payload['rdv_id'] ?? null,
        ])));
    }

    /** CE QUE PROMET LE BOUTON DE RÉSOLUTION. */
    public function actionLabel(DatabaseNotification $notification, ?User $user): string
    {
        $payload = (array) ($notification->data ?? []);

        if (! empty($payload['action_url']) || ! $user) {
            return __('ui.notifications.action.generic');
        }

        return match ($this->typeKey($notification)) {
            'feedback' => __('ui.notifications.action.feedback'),
            'finance' => __('ui.notifications.action.finance'),
            'calendar' => __('ui.notifications.action.calendar'),
            'rendezvous' => __('ui.notifications.action.rendezvous'),
            default => __('ui.notifications.action.dashboard'),
        };
    }

    public function actionUrl(DatabaseNotification $notification, ?User $user): string
    {
        $payload = (array) ($notification->data ?? []);

        if (! empty($payload['action_url'])) {
            return (string) $payload['action_url'];
        }

        if (! $user) {
            return '#';
        }

        // TROIS RÔLES, TROIS DESTINATIONS — Y COMPRIS POUR `feedback` ET `finance`.
        return match ($this->typeKey($notification)) {
            'feedback' => match (true) {
                $user->isAdmin() => route('admin.feedbacks'),
                $user->isEmploye() => route('employe.feedbacks'),
                default => route('client.historique'),
            },
            'finance' => match (true) {
                $user->isAdmin() => route('admin.finance'),
                $user->isEmploye() => route('employe.wallet'),
                default => route('client.dashboard'),
            },
            'calendar' => $user->isAdmin() ? route('admin.calendar.settings') : ($user->isEmploye() ? route('employe.google.calendar') : route('client.dashboard')),
            default => $user->isAdmin()
                ? route('admin.dashboard')
                : ($user->isEmploye() ? route('employe.dashboard') : route('client.rendezvous.index')),
        };
    }
}
