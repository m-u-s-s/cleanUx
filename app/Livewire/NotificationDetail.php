<?php

namespace App\Livewire;

use App\Models\User;
use App\Support\Notifications\NotificationPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * LA FICHE COMPLÈTE D'UNE NOTIFICATION.
 *
 * Le centre montre une ligne par notification ; il ne montre pas tout. Un payload porte souvent
 * plus que son message — références, montants, zone, horodatages, l'URL de résolution — et rien
 * n'exposait ces champs. Cette page les rend TOUS, y compris les clés qu'aucun écran ne connaît
 * encore, et met en avant la seule chose qu'on vient y chercher : où aller pour régler le problème.
 */
class NotificationDetail extends Component
{
    public string $notificationId = '';

    /**
     * L'APPARTENANCE EST DANS LA REQUÊTE, PAS DANS UN CONTRÔLE À CÔTÉ.
     *
     * Volontairement pas de liaison implicite de modèle sur `{notification}` : elle résoudrait
     * l'identifiant SANS regarder à qui la notification appartient, et il faudrait s'en souvenir
     * pour ajouter un `abort_if()` derrière — une marche qu'on oublie. En passant par
     * `$user->notifications()`, la notification d'autrui n'existe tout simplement pas : le 404
     * n'est pas une décision, c'est le résultat de la requête.
     */
    public function mount(string $notification): void
    {
        $this->notificationId = $notification;

        $trouvee = $this->notification();

        if (! $trouvee) {
            abort(404);
        }

        /*
         * Ouvrir vaut lecture — c'est ce que fait tout centre de notifications, et sans cela le
         * compteur ne redescendrait jamais qu'à la main. Le geste reste réversible : « Remettre
         * non lu » est sur cette page.
         */
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
     * Les champs déjà rendus en haut de page (titre, message, lien) sont retirés pour ne pas les
     * répéter ; tout le reste est affiché tel quel, y compris une clé ajoutée demain par une
     * notification qu'on n'a pas encore écrite. Un tableau réduit aux clés connues redeviendrait
     * la moitié du problème qu'on corrige.
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

    /**
     * Les clés techniques d'un payload ne se lisent pas. Celles qu'on connaît reçoivent leur nom
     * français ; les autres sont rendues telles quelles plutôt que masquées.
     */
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
