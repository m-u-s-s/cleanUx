<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Livewire\ProviderCompany\TeamChannels;
use App\Models\Channel;
use App\Models\Message;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * UNE MESSAGERIE D'ÉQUIPE OÙ PERSONNE NE PEUT ÊTRE AJOUTÉ N'EST PAS UNE MESSAGERIE.
 *
 * POURQUOI CE FICHIER EXISTE. `TeamChannels::createChannel()` n'attache que son créateur, et
 * c'est le SEUL `members()->attach` de tout le dépôt — vérifié par recherche exhaustive. Aucun
 * écran, aucun service, aucune commande ne sait ajouter quelqu'un à un canal. Chaque canal reste
 * donc un monologue, quel que soit son type.
 *
 * L'ironie tient dans `ChannelPolicy` : elle expose `kickMember()` et `changeRole()` — expulser
 * un membre et changer son rôle — pour une population qui ne peut jamais exister.
 *
 * Ces deux tests figent le minimum utilisable : ajouter un coéquipier, et créer un canal d'équipe
 * qui embarque l'équipe.
 */
class TeamChannelMembershipTest extends TestCase
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

        return [$org, $patron];
    }

    private function coequipier(OrganizationAccount $org): User
    {
        $user = User::factory()->create([
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

    #[Test]
    public function un_coequipier_peut_etre_ajoute_a_un_canal(): void
    {
        [$org, $patron] = $this->societeAvecPatron();
        $collegue = $this->coequipier($org);

        $canal = Channel::create([
            'organization_account_id' => $org->id,
            'name' => 'general',
            'type' => Channel::TYPE_TEAM,
            'is_private' => false,
            'created_by' => $patron->id,
        ]);
        $canal->members()->attach($patron->id, ['role' => 'owner']);

        Livewire::actingAs($patron)
            ->test(TeamChannels::class)
            ->call('addChannelMember', $canal->id, $collegue->id);

        $this->assertTrue(
            $canal->fresh()->members()->where('users.id', $collegue->id)->exists(),
            'Aucun code du dépôt ne sait ajouter un membre à un canal : la messagerie est un monologue.',
        );
    }

    #[Test]
    public function un_canal_d_equipe_peut_embarquer_toute_l_equipe(): void
    {
        [$org, $patron] = $this->societeAvecPatron();
        $unCollegue = $this->coequipier($org);
        $autreCollegue = $this->coequipier($org);

        Livewire::actingAs($patron)
            ->test(TeamChannels::class)
            ->set('newChannelName', 'general')
            ->set('newChannelType', Channel::TYPE_TEAM)
            ->set('inviteWholeTeam', true)
            ->call('createChannel');

        $canal = Channel::where('organization_account_id', $org->id)->firstOrFail();

        $this->assertEqualsCanonicalizing(
            [$patron->id, $unCollegue->id, $autreCollegue->id],
            $canal->members()->pluck('users.id')->all(),
            "Un canal d'équipe créé sans l'équipe oblige à ajouter chacun à la main — ce que rien ne permettait.",
        );
    }

    /**
     * IDOR : `activeChannelId` est une propriété PUBLIQUE, donc pilotable depuis le navigateur.
     * `openChannel()` ne vérifiait ni l'organisation ni l'appartenance : n'importe quel compte
     * société prestataire pouvait ouvrir le canal privé d'une AUTRE société et en lire les
     * messages et la liste des membres.
     *
     * `ChannelPolicy::view()` encode pourtant la bonne règle — être membre — mais aucun appelant
     * ne la consultait.
     */
    #[Test]
    public function on_n_ouvre_pas_le_canal_prive_d_une_autre_organisation(): void
    {
        [$org, $patron] = $this->societeAvecPatron();

        $autreOrg = OrganizationAccount::factory()->providerCompany()->create();
        $concurrent = $this->coequipier($autreOrg);

        $canalConcurrent = Channel::create([
            'organization_account_id' => $autreOrg->id,
            'name' => 'strategie-secrete',
            'type' => Channel::TYPE_TEAM,
            'is_private' => true,
            'created_by' => $concurrent->id,
        ]);
        $canalConcurrent->members()->attach($concurrent->id, ['role' => 'owner']);

        Message::create([
            'channel_id' => $canalConcurrent->id,
            'user_id' => $concurrent->id,
            'content' => 'Notre marge est de 42% sur ce chantier.',
            'type' => Message::TYPE_TEXT,
        ]);

        Livewire::actingAs($patron)
            ->test(TeamChannels::class)
            ->call('openChannel', $canalConcurrent->id)
            ->assertDontSee('Notre marge est de 42%')
            ->assertDontSee('strategie-secrete');
    }

    #[Test]
    public function on_n_ajoute_pas_un_membre_d_une_autre_organisation(): void
    {
        [$org, $patron] = $this->societeAvecPatron();

        $autreOrg = OrganizationAccount::factory()->providerCompany()->create();
        $etranger = $this->coequipier($autreOrg);

        $canal = Channel::create([
            'organization_account_id' => $org->id,
            'name' => 'general',
            'type' => Channel::TYPE_TEAM,
            'is_private' => false,
            'created_by' => $patron->id,
        ]);
        $canal->members()->attach($patron->id, ['role' => 'owner']);

        Livewire::actingAs($patron)
            ->test(TeamChannels::class)
            ->call('addChannelMember', $canal->id, $etranger->id);

        $this->assertFalse(
            $canal->fresh()->members()->where('users.id', $etranger->id)->exists(),
            "Ouvrir l'ajout de membres ne doit pas ouvrir les canaux aux autres sociétés.",
        );
    }
}
