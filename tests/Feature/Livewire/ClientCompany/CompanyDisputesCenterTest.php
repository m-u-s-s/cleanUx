<?php

namespace Tests\Feature\Livewire\ClientCompany;

use App\Enums\OrganizationRole;
use App\Livewire\ClientCompany\DisputesCenter;
use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Models\DisputeEvent;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\Disputes\DisputeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/** Espace société B2B P2 — centre de litiges société : visibilité des litiges de l'organisation (par site/booking), strictement scopé à l'org du membre. */
class CompanyDisputesCenterTest extends TestCase
{
    use RefreshDatabase;

    private function memberOf(OrganizationAccount $org): User
    {
        $user = User::factory()->client()->create([
            'current_organization_id' => $org->id,
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ]);
        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return $user->fresh();
    }

    private function disputeForOrg(OrganizationAccount $org, User $member): ComplaintCase
    {
        $booking = Booking::factory()->create([
            'client_id' => $member->id,
            'customer_organization_id' => $org->id,
        ]);

        return app(DisputeService::class)->open($member, [
            'booking_id' => $booking->id,
            'subject' => 'Litige '.$org->id,
            'description' => 'Description du litige.',
            'category' => 'quality',
        ]);
    }

    public function test_lists_only_disputes_of_the_members_organization(): void
    {
        $orgA = OrganizationAccount::factory()->clientCompany()->create();
        $orgB = OrganizationAccount::factory()->clientCompany()->create();
        $memberA = $this->memberOf($orgA);

        $mine = $this->disputeForOrg($orgA, $memberA);
        $foreign = $this->disputeForOrg($orgB, $this->memberOf($orgB));

        $this->actingAs($memberA);

        Livewire::test(DisputesCenter::class)
            ->assertOk()
            ->assertViewHas('disputes', function ($disputes) use ($mine, $foreign) {
                $ids = collect($disputes->items())->pluck('id')->all();

                return in_array($mine->id, $ids, true) && ! in_array($foreign->id, $ids, true);
            });
    }

    public function test_member_opens_a_dispute_for_an_org_booking(): void
    {
        $org = OrganizationAccount::factory()->clientCompany()->create();
        $member = $this->memberOf($org);
        $booking = Booking::factory()->create([
            'client_id' => User::factory()->client()->create()->id,
            'customer_organization_id' => $org->id,
        ]);

        $this->actingAs($member);

        Livewire::test(DisputesCenter::class)
            ->set('bookingId', (string) $booking->id)
            ->set('subject', 'Prestation incomplète')
            ->set('description', 'Le site B n’a pas été traité.')
            ->set('category', 'quality')
            ->call('openDispute');

        $this->assertDatabaseHas('complaint_cases', [
            'booking_id' => $booking->id,
            'organization_account_id' => $org->id,
            'subject' => 'Prestation incomplète',
        ]);
    }

    /**
     * LE DÉFAUT MESURÉ : la société joignait des preuves à l'ouverture et ne les revoyait jamais.
     * L'écran n'avait aucun panneau de détail — ni ses pièces, ni le fil.
     */
    public function test_la_societe_revoit_les_preuves_qu_elle_a_jointes(): void
    {
        Storage::fake('private');

        $org = OrganizationAccount::factory()->clientCompany()->create();
        $membre = $this->memberOf($org);
        $booking = Booking::factory()->create([
            'client_id' => $membre->id,
            'customer_organization_id' => $org->id,
        ]);

        $this->actingAs($membre);

        Livewire::test(DisputesCenter::class)
            ->set('bookingId', (string) $booking->id)
            ->set('subject', 'Vitres non faites')
            ->set('description', 'Les vitres du site B sont restées sales.')
            ->set('category', 'quality')
            ->set('preuves', [UploadedFile::fake()->image('vitre.jpg')])
            ->call('openDispute');

        $litige = ComplaintCase::query()->where('booking_id', $booking->id)->firstOrFail();

        $this->assertNotEmpty($litige->attachments, 'La preuve ne s\'est pas enregistrée.');

        Livewire::test(DisputesCenter::class)
            ->call('select', $litige->id)
            ->assertSet('selectedId', $litige->id)
            ->assertViewHas('selected', fn ($s) => $s?->id === $litige->id)
            ->assertSee('Vitres non faites')
            ->assertSee('vitre.jpg');
    }

    /** LE TÉMOIN : sans litige choisi, aucun panneau — l'écran n'invente rien. */
    public function test_temoin_sans_selection_aucun_detail(): void
    {
        $org = OrganizationAccount::factory()->clientCompany()->create();
        $membre = $this->memberOf($org);
        $this->disputeForOrg($org, $membre);

        $this->actingAs($membre);

        Livewire::test(DisputesCenter::class)
            ->assertViewHas('selected', null)
            ->assertDontSee('Aucun message visible.');
    }

    /** LE POINT QUI COMPTE : le litige d'une AUTRE société ne s'ouvre pas. */
    public function test_le_litige_d_une_autre_societe_est_refuse(): void
    {
        $mien = OrganizationAccount::factory()->clientCompany()->create();
        $autre = OrganizationAccount::factory()->clientCompany()->create();

        $membre = $this->memberOf($mien);
        $etranger = $this->disputeForOrg($autre, $this->memberOf($autre));

        $this->actingAs($membre);

        Livewire::test(DisputesCenter::class)
            ->call('select', $etranger->id)
            ->assertSet('selectedId', null)
            ->assertViewHas('selected', null);
    }

    /** PREMIERE LIGNE : le navigateur ne peut pas retourner la propriete, elle est verrouillee. */
    public function test_le_navigateur_ne_peut_pas_forcer_l_identifiant(): void
    {
        $org = OrganizationAccount::factory()->clientCompany()->create();
        $membre = $this->memberOf($org);
        $litige = $this->disputeForOrg($org, $membre);

        $this->actingAs($membre);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(DisputesCenter::class)->set('selectedId', $litige->id);
    }

    /**
     * SECONDE LIGNE, celle qui compte vraiment : meme si le verrou tombait, `render()` re-porte la
     * condition d'organisation. On pose donc la valeur SUR L'INSTANCE, ce que le verrou n'empeche
     * pas, et le detail reste vide.
     */
    public function test_la_garde_tient_meme_si_le_verrou_tombait(): void
    {
        $mien = OrganizationAccount::factory()->clientCompany()->create();
        $autre = OrganizationAccount::factory()->clientCompany()->create();

        $membre = $this->memberOf($mien);
        $etranger = $this->disputeForOrg($autre, $this->memberOf($autre));

        $this->actingAs($membre);

        $composant = Livewire::test(DisputesCenter::class);
        $composant->instance()->selectedId = $etranger->id;

        $this->assertNull(
            $composant->instance()->render()->getData()['selected'],
            "Le detail d'une autre societe a fuite malgre la garde de render()."
        );
    }

    /** LE TEMOIN de la garde ci-dessus : son propre litige, lui, se rend bien. */
    public function test_temoin_son_propre_litige_se_rend(): void
    {
        $org = OrganizationAccount::factory()->clientCompany()->create();
        $membre = $this->memberOf($org);
        $mien = $this->disputeForOrg($org, $membre);

        $this->actingAs($membre);

        $composant = Livewire::test(DisputesCenter::class);
        $composant->instance()->selectedId = $mien->id;

        $this->assertSame(
            $mien->id,
            $composant->instance()->render()->getData()['selected']?->id
        );
    }

    /** Le fil ne montre que ce qui est visible du client : une note interne du support reste interne. */
    public function test_une_note_interne_du_support_reste_invisible(): void
    {
        $org = OrganizationAccount::factory()->clientCompany()->create();
        $membre = $this->memberOf($org);
        $litige = $this->disputeForOrg($org, $membre);

        $litige->events()->create([
            'type' => DisputeEvent::TYPE_MESSAGE,
            'author_role' => DisputeEvent::ROLE_ADMIN,
            'visibility' => DisputeEvent::VISIBILITY_PRIVATE,
            'body' => 'NOTE INTERNE A NE PAS MONTRER',
        ]);

        $litige->events()->create([
            'type' => DisputeEvent::TYPE_MESSAGE,
            'author_role' => DisputeEvent::ROLE_ADMIN,
            'visibility' => DisputeEvent::VISIBILITY_ALL,
            'body' => 'Message adresse a la societe',
        ]);

        $this->actingAs($membre);

        Livewire::test(DisputesCenter::class)
            ->call('select', $litige->id)
            ->assertSee('Message adresse a la societe')
            ->assertDontSee('NOTE INTERNE A NE PAS MONTRER');
    }

    /** La société répond, preuve comprise. */
    public function test_la_societe_repond_avec_une_preuve(): void
    {
        Storage::fake('private');

        $org = OrganizationAccount::factory()->clientCompany()->create();
        $membre = $this->memberOf($org);
        $litige = $this->disputeForOrg($org, $membre);

        $this->actingAs($membre);

        Livewire::test(DisputesCenter::class)
            ->call('select', $litige->id)
            ->set('responseBody', 'Voici la photo du site.')
            ->set('reponsePreuves', [UploadedFile::fake()->image('site.jpg')])
            ->call('postResponse')
            ->assertSet('responseBody', '');

        $evenement = $litige->events()
            ->where('author_role', DisputeEvent::ROLE_CLIENT)
            ->where('body', 'Voici la photo du site.')
            ->first();

        $this->assertNotNull($evenement, 'La réponse de la société n\'a pas été enregistrée.');
        $this->assertNotEmpty($evenement->attachments, 'La preuve jointe à la réponse est perdue.');
        Storage::disk('private')->assertExists($evenement->attachments[0]['path']);
    }

    /** On ne répond pas au litige d'une autre société, même en forçant l'identifiant. */
    public function test_repondre_au_litige_d_une_autre_societe_est_refuse(): void
    {
        $mien = OrganizationAccount::factory()->clientCompany()->create();
        $autre = OrganizationAccount::factory()->clientCompany()->create();

        $membre = $this->memberOf($mien);
        $etranger = $this->disputeForOrg($autre, $this->memberOf($autre));

        $this->actingAs($membre);

        $composant = Livewire::test(DisputesCenter::class);
        $composant->instance()->selectedId = $etranger->id;
        $composant->instance()->responseBody = 'Je ne devrais pas pouvoir ecrire ici.';
        $composant->instance()->postResponse();

        $this->assertDatabaseMissing('dispute_events', [
            'complaint_case_id' => $etranger->id,
            'body' => 'Je ne devrais pas pouvoir ecrire ici.',
        ]);
    }

    /** LE TÉMOIN DU REFUS : sur son propre litige, la même réponse passe. */
    public function test_temoin_la_reponse_passe_sur_son_propre_litige(): void
    {
        $org = OrganizationAccount::factory()->clientCompany()->create();
        $membre = $this->memberOf($org);
        $litige = $this->disputeForOrg($org, $membre);

        $this->actingAs($membre);

        Livewire::test(DisputesCenter::class)
            ->call('select', $litige->id)
            ->set('responseBody', 'Je ne devrais pas pouvoir ecrire ici.')
            ->call('postResponse');

        $this->assertDatabaseHas('dispute_events', [
            'complaint_case_id' => $litige->id,
            'body' => 'Je ne devrais pas pouvoir ecrire ici.',
        ]);
    }
}
