<?php

namespace Tests\Feature\Client;

use App\Livewire\ClientDashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La salutation du tableau de bord suit l'heure du CLIENT, et se traduit.
 *
 * Elle s'écrivait `'Bonjour ' . Str::before($user->name, ' ')` — hors de `__()`, donc figée en
 * français sur une page qui, elle, est traduite. Et figée à « Bonjour » quelle que soit l'heure.
 */
class LaSalutationSuitLHeureDuClientTest extends TestCase
{
    use RefreshDatabase;

    private function client(string $nom, string $fuseau = 'Europe/Brussels', string $langue = 'fr'): User
    {
        return User::factory()->client()->create([
            'name' => $nom,
            'timezone' => $fuseau,
            'locale' => $langue,
            'email_verified_at' => now(),
        ]);
    }

    private function salutationA(string $heureUtc, User $utilisateur): string
    {
        Carbon::setTestNow(Carbon::parse($heureUtc, 'UTC'));

        $this->actingAs($utilisateur);

        return Livewire::test(ClientDashboard::class)->instance()->salutation();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_le_matin_elle_dit_bonjour(): void
    {
        // 08 h 00 UTC = 09 h 00 à Bruxelles.
        $this->assertSame('Bonjour Marie', $this->salutationA('2026-08-28 08:00:00', $this->client('Marie Dupont')));
    }

    public function test_l_apres_midi_elle_change(): void
    {
        $this->assertSame('Bon après-midi Marie', $this->salutationA('2026-08-28 13:00:00', $this->client('Marie Dupont')));
    }

    public function test_le_soir_elle_change_encore(): void
    {
        $this->assertSame('Bonsoir Marie', $this->salutationA('2026-08-28 19:00:00', $this->client('Marie Dupont')));
    }

    /**
     * LE POINT QUI COMPTE. La plateforme couvre le Maroc : à 17 h à Casablanca il fait encore
     * après-midi, pendant qu'il est 19 h à Bruxelles. Le serveur ne décide pas.
     */
    public function test_elle_suit_le_fuseau_du_client_pas_celui_du_serveur(): void
    {
        $marocain = $this->client('Youssef Alami', 'Africa/Casablanca');
        $belge = $this->client('Marie Dupont', 'Europe/Brussels');

        // 17 h 30 UTC = 18 h 30 à Casablanca (UTC+1) mais 19 h 30 à Bruxelles (UTC+2).
        $this->assertSame('Bonsoir Marie', $this->salutationA('2026-08-28 17:30:00', $belge));

        // 15 h 30 UTC = 16 h 30 à Casablanca, 17 h 30 à Bruxelles : les deux sont l'après-midi,
        // mais à 16 h 30 UTC ils divergent.
        $this->assertSame('Bon après-midi Youssef', $this->salutationA('2026-08-28 16:30:00', $marocain));
        $this->assertSame('Bonsoir Marie', $this->salutationA('2026-08-28 16:30:00', $belge));
    }

    /**
     * Elle se traduit — c'était le défaut, la formule vivait hors de `__()`.
     *
     * PAR UNE VRAIE REQUÊTE, et non par `Livewire::test()` : celui-ci ne traverse pas les
     * middlewares, donc `SetLocale` n'y court jamais et la langue resterait celle par défaut.
     * Le test mesurerait alors sa propre lacune.
     */
    public function test_elle_se_traduit(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-28 07:00:00', 'UTC'));

        $this->actingAs($this->client('Jan Janssens', 'Europe/Brussels', 'nl'))
            ->get('/dashboard/client')
            ->assertOk()
            ->assertSee('Goedemorgen Jan');
    }

    /** LE TÉMOIN DE LA TRADUCTION : en français, la même heure rend bien le français. */
    public function test_temoin_en_francais_elle_reste_francaise(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-28 07:00:00', 'UTC'));

        $this->actingAs($this->client('Jan Janssens'))
            ->get('/dashboard/client')
            ->assertOk()
            ->assertSee('Bonjour Jan');
    }

    /** Un compte sans nom salue quand même, plutôt que d'afficher « Bonjour  ». */
    public function test_un_compte_sans_nom_est_salue_sans_espace_orphelin(): void
    {
        $this->assertSame('Bonjour', $this->salutationA('2026-08-28 08:00:00', $this->client('')));
    }

    /** Et l'en-tête affiche bien ce que le composant calcule. */
    public function test_l_entete_affiche_la_salutation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-28 19:00:00', 'UTC'));

        $this->actingAs($this->client('Marie Dupont'));

        Livewire::test(ClientDashboard::class)
            ->assertSee('Bonsoir Marie')
            ->assertDontSee('Bonjour Marie');
    }
}
