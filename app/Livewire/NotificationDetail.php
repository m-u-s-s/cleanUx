<?php

namespace App\Livewire;

use App\Models\User;
use App\Support\Notifications\NotificationPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** LA FICHE COMPLÈTE D'UNE NOTIFICATION. */
class NotificationDetail extends Component
{
    public string $notificationId = '';

    /** L'APPARTENANCE EST DANS LA REQUÊTE, PAS DANS UN CONTRÔLE À CÔTÉ. */
    public function mount(string $notification): void
    {
        $this->notificationId = $notification;

        $trouvee = $this->notification();

        if (! $trouvee) {
            abort(404);
        }

        // Ouvrir vaut lecture — c'est ce que fait tout centre de notifications, et sans cela le compteur ne redescendrait jamais qu'à la main.
        if (is_null($trouvee->read_at)) {
            $trouvee->markAsRead();
        }
    }

    protected function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    public function notification(): ?DatabaseNotification
    {
        $notification = $this->currentUser()?->notifications()->whereKey($this->notificationId)->first();

        return $notification instanceof DatabaseNotification ? $notification : null;
    }

    public function markAsUnread(): void
    {
        $notification = $this->notification();

        if ($notification && ! is_null($notification->read_at)) {
            $notification->forceFill(['read_at' => null])->save();
        }
    }

    public function markAsRead(): void
    {
        $notification = $this->notification();

        if ($notification && is_null($notification->read_at)) {
            $notification->markAsRead();
        }
    }

    public function deleteNotification(): void
    {
        $this->notification()?->delete();

        $this->redirectRoute('notifications.index', navigate: true);
    }

    /**
     * TOUT LE PAYLOAD, PAS SEULEMENT LES CLÉS QUE CET ÉCRAN CONNAÎT.
     *
     * @return array<string, string>
     */
    public function payloadDetaille(): array
    {
        $notification = $this->notification();

        if (! $notification) {
            return [];
        }

        $payload = (array) ($notification->data ?? []);

        // `type` et `severity` sont rendus par les pastilles ; les trois autres, en tête de page.
        unset($payload['title'], $payload['message'], $payload['action_url'], $payload['type'], $payload['severity']);

        $lignes = [];

        foreach ($payload as $cle => $valeur) {
            if (blank($valeur) && ! is_numeric($valeur) && ! is_bool($valeur)) {
                continue;
            }

            $lignes[(string) $cle] = match (true) {
                is_bool($valeur) => $valeur ? __('ui.navigation.yes') : __('ui.navigation.no'),
                is_scalar($valeur) => (string) $valeur,
                default => (string) json_encode($valeur, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            };
        }

        return $lignes;
    }

    /** Les clés techniques d'un payload ne se lisent pas. */
    public function libellePayload(string $cle): string
    {
        $connues = [
            'rdv_id' => __('ui.notifications.payload.rdv_id'),
            'booking_id' => __('ui.notifications.payload.rdv_id'),
            'booking_reference' => __('ui.notifications.payload.booking_reference'),
            'invoice_number' => __('ui.notifications.payload.invoice_number'),
            'zone_name' => __('ui.notifications.payload.zone_name'),
            'service_label' => __('ui.notifications.payload.service_label'),
            'google_email' => __('ui.notifications.payload.google_email'),
        ];

        return $connues[$cle] ?? $cle;
    }

    public function render(): View
    {
        $notification = $this->notification();

        if (! $notification) {
            abort(404);
        }

        $presenter = app(NotificationPresenter::class);
        $user = $this->currentUser();

        return view('livewire.notification-detail', [
            'notification' => $notification,
            'presenter' => $presenter,
            'libelle' => $presenter->label($notification),
            'titre' => $presenter->title($notification),
            'message' => $presenter->message($notification),
            'severite' => $presenter->severity($notification),
            'lienResolution' => $presenter->actionUrl($notification, $user),
            'libelleResolution' => $presenter->actionLabel($notification, $user),
            'payload' => $this->payloadDetaille(),
        ]);
    }
}
