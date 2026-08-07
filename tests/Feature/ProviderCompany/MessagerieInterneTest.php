<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Enums\ProviderType;
use App\Livewire\ProviderCompany\ProviderDashboard;
use App\Livewire\ProviderCompany\TeamChannels;
use App\Models\Channel;
use App\Models\Message;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LES DEUX TROUS QUI RESTAIENT DANS LA MESSAGERIE D'ÉQUIPE.
 *
 * Le reste fonctionne depuis le 2026-08-05 : gestion des membres d'un canal, option « embarquer
 * toute l'équipe » à la création, et autorisation Reverb qui vérifie l'appartenance réelle plutôt
 * qu'un accesseur fragile. Vérifié dans le code avant d'écrire quoi que ce soit.
 *
 * Restaient :
 *
 *   1. LE COMPTEUR DE NON-LUS DU TABLEAU DE BORD, écrit `0` EN DUR — « calculé via Channel si
 *      Reverb actif », disait le commentaire. Un zéro se lit comme un fait : le gérant en concluait
 *      que personne ne lui écrivait, alors que `TeamChannels` savait déjà compter.
 *
 *   2. LA CONVERSATION À DEUX. Le type `private` existait, et rien ne permettait d'en ouvrir une :
 *      pour dire un mot à un collègue, il fallait créer un canal nommé, ce que personne ne fait —
 *      les gens passaient donc par WhatsApp, hors de toute trace.
 */
class MessagerieInterneTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: OrganizationAccount, 1: User} */
    private function societeAvecPatron(): array
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();

        $patron = User::factory()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $patron->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        ProviderProfile::factory()->create([
            'user_id' => $patron->id,
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
        ]);

        return [$org, $patron];
    }

    private function employe(OrganizationAccount $org, string $nom): User
    {
        // Les colonnes d'organisation sont posées SUR L'UTILISATEUR, pas seulement l'adhésion :
        // `TeamChannels` résout son organisation depuis le compte, et un employé qui ne la porte
        // pas ne peut pas ouvrir l'écran. C'est le même oubli que celui corrigé côté seeder.
        $user = User::factory()->create([
            'name' => $nom,
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::WORKER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return $user;
    }

    // ──────────────────────────────────────────────────────
    // Le compteur de non-lus
    // ──────────────────────────────────────────────────────

    #[Test]
    public function le_tableau_de_bord_compte_les_messages_reellement_non_lus(): void
    {
        [$org, $patron] = $this->societeAvecPatron();
        $ana = $this->employe($org, 'Ana Silva');

        $canal = Channel::create([
            'organization_account_id' => $org->id,
            'name' => 'Général',
            'type' => 'team',
            'created_by' => $patron->id,
        ]);
        $canal->members()->attach([$patron->id => ['role' => 'owner'], $ana->id => ['role' => 'member']]);

        Message::create([
            'channel_id' => $canal->id,
            'user_id' => $ana->id,
            'content' => 'Le client du 3e a rappelé',
            'type' => 'text',
        ]);

        Livewire::actingAs($patron)
            ->test(ProviderDashboard::class)
            ->assertViewHas('kpis', fn (array $kpis) => $kpis['unread_messages'] === 1);
    }

    #[Test]
    public function ses_propres_messages_ne_comptent_pas_comme_non_lus(): void
    {
        [$org, $patron] = $this->societeAvecPatron();

        $canal = Channel::create([
            'organization_account_id' => $org->id,
            'name' => 'Général',
            'type' => 'team',
            'created_by' => $patron->id,
        ]);
        $canal->members()->attach($patron->id, ['role' => 'owner']);

        Message::create([
            'channel_id' => $canal->id,
            'user_id' => $patron->id,
            'content' => 'Bonjour à tous',
            'type' => 'text',
        ]);

        Livewire::actingAs($patron)
            ->test(ProviderDashboard::class)
            ->assertViewHas('kpis', fn (array $kpis) => $kpis['unread_messages'] === 0);
    }

    #[Test]
    public function un_canal_dont_on_n_est_pas_membre_ne_compte_pas(): void
    {
        [$org, $patron] = $this->societeAvecPatron();
        $ana = $this->employe($org, 'Ana Silva');

        // Canal auquel le patron n'appartient pas : ses messages ne sont pas « non lus » pour lui,
        // ils ne le regardent simplement pas.
        $canal = Channel::create([
            'organization_account_id' => $org->id,
            'name' => 'Entre nous',
            'type' => 'private',
            'created_by' => $ana->id,
        ]);
        $canal->members()->attach($ana->id, ['role' => 'owner']);

        Message::create([
            'channel_id' => $canal->id,
            'user_id' => $ana->id,
            'content' => 'Message privé',
            'type' => 'text',
        ]);

        Livewire::actingAs($patron)
            ->test(ProviderDashboard::class)
            ->assertViewHas('kpis', fn (array $kpis) => $kpis['unread_messages'] === 0);
    }

    // ──────────────────────────────────────────────────────
    // La conversation à deux
    // ──────────────────────────────────────────────────────

    #[Test]
    public function ouvrir_une_conversation_avec_un_collegue_la_cree(): void
    {
        [$org, $patron] = $this->societeAvecPatron();
        $ana = $this->employe($org, 'Ana Silva');

        Livewire::actingAs($patron)
            ->test(TeamChannels::class)
            ->call('ouvrirConversationDirecte', $ana->id);

        $canal = Channel::where('organization_account_id', $org->id)
            ->where('type', 'private')
            ->first();

        $this->assertNotNull($canal);
        $this->assertEqualsCanonicalizing(
            [$patron->id, $ana->id],
            $canal->members()->pluck('users.id')->all()
        );
    }

    #[Test]
    public function rouvrir_la_meme_conversation_ne_la_duplique_pas(): void
    {
        /*
         * Sans cette recherche préalable, chaque clic créerait un canal de plus : la messagerie se
         * remplirait de conversations vides portant le même nom, et l'historique se disperserait
         * entre elles — ce qui est pire que pas de messagerie du tout.
         */
        [$org, $patron] = $this->societeAvecPatron();
        $ana = $this->employe($org, 'Ana Silva');

        Livewire::actingAs($patron)
            ->test(TeamChannels::class)
            ->call('ouvrirConversationDirecte', $ana->id)
            ->call('ouvrirConversationDirecte', $ana->id);

        $this->assertSame(
            1,
            Channel::where('organization_account_id', $org->id)->where('type', 'private')->count()
        );
    }

    #[Test]
    public function la_conversation_se_retrouve_depuis_les_deux_cotes(): void
    {
        [$org, $patron] = $this->societeAvecPatron();
        $ana = $this->employe($org, 'Ana Silva');

        Livewire::actingAs($patron)
            ->test(TeamChannels::class)
            ->call('ouvrirConversationDirecte', $ana->id);

        // Ana ouvre la conversation de son côté : c'est la MÊME, sinon chacun parlerait dans son
        // propre canal en croyant s'adresser à l'autre.
        Livewire::actingAs($ana)
            ->test(TeamChannels::class)
            ->call('ouvrirConversationDirecte', $patron->id);

        $this->assertSame(
            1,
            Channel::where('organization_account_id', $org->id)->where('type', 'private')->count()
        );
    }

    #[Test]
    public function on_n_ouvre_pas_de_conversation_avec_quelqu_un_d_une_autre_societe(): void
    {
        [$org, $patron] = $this->societeAvecPatron();

        $concurrente = OrganizationAccount::factory()->providerCompany()->create();
        $etranger = $this->employe($concurrente, 'Employé Concurrent');

        Livewire::actingAs($patron)
            ->test(TeamChannels::class)
            ->call('ouvrirConversationDirecte', $etranger->id);

        $this->assertSame(
            0,
            Channel::where('organization_account_id', $org->id)->where('type', 'private')->count()
        );
    }
}
