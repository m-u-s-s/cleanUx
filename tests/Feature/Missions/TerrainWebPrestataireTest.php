<?php

namespace Tests\Feature\Missions;

use App\Livewire\Employe\MissionFieldTools;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\MissionExtra;
use App\Models\MissionIncident;
use App\Models\MissionMedia;
use App\Models\MissionQuoteRevision;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\MissionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LE TERRAIN WEB DU PRESTATAIRE — la parité que le porteur demande.
 *
 * « Il doit pouvoir effectuer la mission même avec un ordinateur. » Le cycle de vie complet y était
 * déjà ; trois outils manquaient — fiche d'accès, imprévu, supplément — dont deux existaient en
 * composants web sur d'AUTRES pages. Un défaut de joignabilité, pas de fonctionnalité.
 */
class TerrainWebPrestataireTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $prestataire;

    private function mission(string $moteur = 'domicile', string $statut = MissionStatus::ARRIVED): Mission
    {
        $this->client = User::factory()->client()->create();
        $this->prestataire = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $this->prestataire->id, 'status' => 'active']);

        $booking = Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $this->client->id,
            'client_id' => $this->client->id,
            'employe_id' => $this->prestataire->id,
            'status' => BookingStatus::CONFIRME,
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
            'address' => 'Rue de la Loi 1, 1000 Bruxelles',
            'devis_estime' => 50.00,
        ] + ($moteur === 'vehicule' ? ['dropoff_lat' => 50.90, 'dropoff_lng' => 4.48] : []));

        $mission = Mission::create([
            'booking_id' => $booking->id,
            'lead_provider_user_id' => $this->prestataire->id,
            'status' => $statut,
        ]);

        MissionAssignment::factory()->accepted()->create([
            'mission_id' => $mission->id,
            'user_id' => $this->prestataire->id,
        ]);

        return $mission->fresh('booking');
    }

    public function test_le_prestataire_signale_un_imprevu_depuis_le_web(): void
    {
        $mission = $this->mission();

        Livewire::actingAs($this->prestataire)
            ->test(MissionFieldTools::class, ['mission' => $mission])
            ->set('incidentType', MissionIncident::TYPE_PREEXISTING_DAMAGE)
            ->set('incidentDescription', 'Trace d’humidité derrière le meuble.')
            ->call('signalerUnImprevu')
            ->assertSet('erreur', null);

        $this->assertSame(1, MissionIncident::query()->where('mission_id', $mission->id)->count());
    }

    public function test_le_prestataire_propose_un_supplement_depuis_le_web(): void
    {
        $mission = $this->mission();

        Livewire::actingAs($this->prestataire)
            ->test(MissionFieldTools::class, ['mission' => $mission])
            ->set('extraLabel', 'Nettoyage des vitres')
            ->set('extraPrix', '25')
            ->call('proposerUnSupplement')
            ->assertSet('erreur', null);

        $extra = MissionExtra::query()->where('mission_id', $mission->id)->firstOrFail();

        $this->assertSame('Nettoyage des vitres', $extra->label);
        // La saisie est en euros, l'envoi en centimes.
        $this->assertSame(2500, $extra->price_cents);
    }

    /** Rien à ajouter sur une course : le bloc n'est pas grisé, il n'est pas rendu. */
    public function test_le_supplement_n_est_pas_rendu_sur_une_course(): void
    {
        Livewire::actingAs($this->prestataire ?? User::factory()->employe()->create());

        $mission = $this->mission('vehicule');

        Livewire::actingAs($this->prestataire)
            ->test(MissionFieldTools::class, ['mission' => $mission])
            ->assertDontSee('Proposer un supplément');
    }

    /** LE TÉMOIN : sur une mission à domicile, il est bien là. */
    public function test_le_supplement_est_rendu_a_domicile(): void
    {
        $mission = $this->mission('domicile');

        Livewire::actingAs($this->prestataire)
            ->test(MissionFieldTools::class, ['mission' => $mission])
            ->assertSee('Proposer un supplément');
    }

    /**
     * LA FICHE D'ACCÈS SE REFUSE AVEC SON MOTIF, jamais avec une fiche vide : une fiche vide se lit
     * comme une donnée manquante et fait appeler le support pour rien.
     */
    public function test_la_fiche_d_acces_dit_pourquoi_elle_est_verrouillee(): void
    {
        $mission = $this->mission('domicile', MissionStatus::ASSIGNED);

        Livewire::actingAs($this->prestataire)
            ->test(MissionFieldTools::class, ['mission' => $mission])
            ->assertSee('Accéder au lieu')
            ->assertSee('arrivée', escape: false);
    }

    public function test_le_prestataire_propose_un_nouveau_devis_depuis_le_web(): void
    {
        $mission = $this->mission('domicile');

        // LA PREUVE EST OBLIGATOIRE : une photo « avant » doit exister, sinon le service refuse.
        MissionMedia::create([
            'mission_id' => $mission->id,
            'uploaded_by_user_id' => $this->prestataire->id,
            'media_type' => MissionMedia::TYPE_BEFORE_PHOTO,
            'path' => 'missions/avant.jpg',
        ]);

        Livewire::actingAs($this->prestataire)
            ->test(MissionFieldTools::class, ['mission' => $mission])
            ->set('revisionPrix', '300')
            ->set('revisionMotif', 'Deux cents mètres carrés annoncés vingt.')
            ->call('proposerUnNouveauDevis')
            ->assertSet('erreur', null);

        $revision = MissionQuoteRevision::query()->where('mission_id', $mission->id)->firstOrFail();

        $this->assertSame(30000, $revision->revised_total_cents);
        $this->assertSame(5000, $revision->original_total_cents);
    }

    /** LE TÉMOIN INVERSE : sans photo « avant », la proposition est refusée AVEC son motif. */
    public function test_sans_photo_le_nouveau_devis_est_refuse(): void
    {
        $mission = $this->mission('domicile');

        Livewire::actingAs($this->prestataire)
            ->test(MissionFieldTools::class, ['mission' => $mission])
            ->set('revisionPrix', '300')
            ->set('revisionMotif', 'Beaucoup plus grand.')
            ->call('proposerUnNouveauDevis')
            ->assertSet('erreur', 'Prenez d’abord une photo « avant » : sans preuve, le client doit vous croire sur parole.');

        $this->assertSame(0, MissionQuoteRevision::query()->where('mission_id', $mission->id)->count());
    }

    /** Le nouveau devis n'existe pas sur une course : le bloc n'est pas rendu. */
    public function test_le_nouveau_devis_n_est_pas_rendu_sur_une_course(): void
    {
        $mission = $this->mission('vehicule');

        Livewire::actingAs($this->prestataire)
            ->test(MissionFieldTools::class, ['mission' => $mission])
            ->assertDontSee('Nouveau devis');
    }

    /**
     * LES DEUX RÉPONSES AU MÊME CONSTAT vivent côte à côte. Le renfort se demande depuis le même
     * bloc que la révision : les séparer ferait choisir le premier trouvé, et le premier trouvé
     * serait la renégociation — celle qui met le client sous pression.
     */
    public function test_le_prestataire_demande_du_renfort_depuis_le_web(): void
    {
        $mission = $this->mission('domicile');

        Livewire::actingAs($this->prestataire)
            ->test(MissionFieldTools::class, ['mission' => $mission])
            ->set('revisionMotif', 'Deux cents mètres carrés à faire à deux.')
            ->call('demanderDuRenfort')
            ->assertSet('erreur', null);

        $this->assertSame(
            1,
            \App\Models\MissionReinforcementRequest::query()->where('mission_id', $mission->id)->count(),
        );
    }

    /** Un composant Livewire est une porte HTTP à part entière. */
    public function test_un_prestataire_etranger_est_refuse(): void
    {
        $mission = $this->mission();
        $autre = User::factory()->employe()->create();
        ProviderProfile::create(['user_id' => $autre->id, 'status' => 'active']);

        Livewire::actingAs($autre)
            ->test(MissionFieldTools::class, ['mission' => $mission])
            ->assertForbidden();
    }
}
