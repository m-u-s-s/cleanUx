<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\Notifications\NotificationPresenter;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

/** « SYSTÈME / SYSTÈME / NOTIFICATION », TROIS FOIS DE SUITE. */
class NotificationPresenterLibellesTest extends TestCase
{
    private function notification(array $data, string $type = 'App\Notifications\GenericNotification'): DatabaseNotification
    {
        return new DatabaseNotification([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'notifiable_type' => User::class,
            'notifiable_id' => 1,
            'data' => $data,
        ]);
    }

    public function test_un_evenement_sans_texte_est_quand_meme_lisible(): void
    {
        $presenter = app(NotificationPresenter::class);

        $notification = $this->notification([
            'type' => 'mission_started',
            'mission_id' => 1,
            'employee_name' => 'B. Sanchez',
            'booking_reference' => 'CUX-E1ZXTF',
        ]);

        $this->assertSame('Intervention', $presenter->label($notification));
        $this->assertSame('Intervention démarrée', $presenter->title($notification));
        $this->assertSame(
            'B. Sanchez a commencé l’intervention. (réservation CUX-E1ZXTF)',
            $presenter->message($notification),
        );
    }

    public function test_le_texte_de_la_charge_utile_gagne_toujours(): void
    {
        // TÉMOIN POSITIF.
        $presenter = app(NotificationPresenter::class);

        $notification = $this->notification([
            'type' => 'mission_started',
            'title' => 'Titre maison',
            'message' => 'Message maison',
        ]);

        $this->assertSame('Titre maison', $presenter->title($notification));
        $this->assertSame('Message maison', $presenter->message($notification));
    }

    public function test_un_type_inconnu_garde_le_repli_historique(): void
    {
        // Second témoin : le comportement d'origine doit survivre pour tout ce qui n'est pas listé.
        $presenter = app(NotificationPresenter::class);

        $notification = $this->notification([], 'App\Notifications\PlainNotification');

        $this->assertSame('system', $presenter->typeKey($notification));
        $this->assertSame('Système', $presenter->label($notification));
        $this->assertSame('Notification', $presenter->message($notification));
    }

    public function test_une_alerte_securite_cesse_de_passer_pour_du_systeme(): void
    {
        $presenter = app(NotificationPresenter::class);

        $notification = $this->notification([], 'App\Notifications\SafetyAlertRaised');

        $this->assertSame('safety', $presenter->typeKey($notification));
        $this->assertSame('Sécurité', $presenter->label($notification));
        $this->assertSame('Alerte sécurité', $presenter->title($notification));
    }
}
