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
 * LA FUITE ENTRE SOCIÉTÉS N'ÉTAIT FERMÉE QU'À MOITIÉ.
 *
 * La phase 0 a corrigé la LECTURE : `openChannel()` et le rendu vérifient désormais l'organisation
 * et l'appartenance. L'ÉCRITURE, elle, est restée ouverte — je ne l'avais pas regardée.
 *
 * `sendMessage()` fait `Channel::find($this->activeChannelId)` sans scoping ni contrôle de
 * politique, et `MessageService::send()` n'autorise rien de son côté. Comme `$activeChannelId` est
 * une propriété PUBLIQUE Livewire, donc pilotable depuis le navigateur, n'importe quel compte
 * pouvait publier un message dans le canal privé d'une société concurrente — sans jamais l'ouvrir.
 *
 * `ChannelPolicy::postMessage()` encode pourtant la règle exacte (membre, canal ni verrouillé ni
 * archivé) et n'était appelée nulle part. Cinquième garde déclarée sans consommateur rencontrée
 * dans ce programme.
 *
 * Découvert en construisant l'API des canaux : chaque point d'entrée oblige à relire une garde.
 */
class ChannelWriteGuardTest extends TestCase
{
    use RefreshDatabase;

    private function membreActif(OrganizationAccount $org, OrganizationRole $role): User
    {
        $user = User::factory()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return $user;
    }

    #[Test]
    public function on_ne_publie_pas_dans_le_canal_prive_d_une_autre_societe(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();
        $intrus = $this->membreActif($org, OrganizationRole::OWNER);

        $autreOrg = OrganizationAccount::factory()->providerCompany()->create();
        $concurrent = $this->membreActif($autreOrg, OrganizationRole::OWNER);

        $canalConcurrent = Channel::create([
            'organization_account_id' => $autreOrg->id,
            'name' => 'strategie-secrete',
            'type' => Channel::TYPE_TEAM,
            'is_private' => true,
            'created_by' => $concurrent->id,
        ]);
        $canalConcurrent->members()->attach($concurrent->id, ['role' => 'owner']);

        Livewire::actingAs($intrus)
            ->test(TeamChannels::class)
            // La propriété est publique : on l'écrit directement, sans passer par openChannel().
            ->set('activeChannelId', $canalConcurrent->id)
            ->set('messageInput', 'Bonjour depuis la concurrence.')
            ->call('sendMessage');

        $this->assertSame(
            0,
            Message::where('channel_id', $canalConcurrent->id)->count(),
            "Fermer la lecture ne suffit pas : l'écriture était une seconde porte, restée ouverte.",
        );
    }

    #[Test]
    public function un_membre_de_l_organisation_non_membre_du_canal_ne_publie_pas(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();

        $fondateur = $this->membreActif($org, OrganizationRole::OWNER);
        $collegue = $this->membreActif($org, OrganizationRole::WORKER);

        // Même société, mais canal privé dont le collègue ne fait pas partie.
        $canal = Channel::create([
            'organization_account_id' => $org->id,
            'name' => 'direction',
            'type' => Channel::TYPE_TEAM,
            'is_private' => true,
            'created_by' => $fondateur->id,
        ]);
        $canal->members()->attach($fondateur->id, ['role' => 'owner']);

        Livewire::actingAs($collegue)
            ->test(TeamChannels::class)
            ->set('activeChannelId', $canal->id)
            ->set('messageInput', 'Je ne devrais pas être ici.')
            ->call('sendMessage');

        $this->assertSame(
            0,
            Message::where('channel_id', $canal->id)->count(),
            'Appartenir à la société ne donne pas accès à tous ses canaux privés.',
        );
    }

    #[Test]
    public function un_membre_du_canal_publie_normalement(): void
    {
        $org = OrganizationAccount::factory()->providerCompany()->create();
        $patron = $this->membreActif($org, OrganizationRole::OWNER);

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
            ->set('activeChannelId', $canal->id)
            ->set('messageInput', 'Bonjour à toute l\'équipe.')
            ->call('sendMessage');

        $this->assertSame(1, Message::where('channel_id', $canal->id)->count());
    }
}
