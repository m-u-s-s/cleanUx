<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\User;
use App\Services\Organizations\ProviderOrganisationResolver;
use App\Support\Domain\BookingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LA MISSION DOIT PORTER LA SOCIÉTÉ QUI L'EXÉCUTE.
 *
 * POURQUOI CE FICHIER EXISTE. `missions` est le modèle d'exécution, et `DispatchCenter` filtre sur
 * `provider_organization_id`. Or `MissionFromRendezVousSyncService` — déclenché par
 * `RendezVousObserver` sur `Booking::saved` en statut `confirme` — ne l'écrivait JAMAIS : zéro
 * occurrence de la colonne dans le service. Conséquence : tous les écrans de l'espace société
 * restaient vides sur le parcours nominal, alors que les missions existaient bien.
 *
 * LA SOURCE DE VÉRITÉ EST `provider_profiles.organization_account_id`. Ce n'est pas
 * `users.organization_account_id` : la société d'un SALARIÉ vit sur son profil prestataire, et
 * `routes/channels.php` s'est déjà trompé de colonne pour la même raison.
 */
class TracabiliteSocieteSurMissionTest extends TestCase
{
    use RefreshDatabase;

    private function societePrestataire(): OrganizationAccount
    {
        return OrganizationAccount::factory()->create([
            'type' => OrganizationType::PROVIDER_COMPANY->value,
            'status' => 'active',
        ]);
    }

    private function salarie(OrganizationAccount $org): User
    {
        $user = User::factory()->employe()->create();
        $user->providerProfile()->create([
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
            'status' => 'active',
        ]);

        return $user->fresh();
    }

    public function test_le_resolveur_lit_la_societe_sur_le_profil_prestataire(): void
    {
        $org = $this->societePrestataire();
        $salarie = $this->salarie($org);

        $this->assertSame($org->id, app(ProviderOrganisationResolver::class)->pourUtilisateur($salarie->id));
    }

    public function test_le_resolveur_rend_null_pour_un_independant(): void
    {
        // Un indépendant n'a pas de société : inventer la sienne ferait apparaître ses missions
        // dans le dispatch de quelqu'un d'autre.
        $independant = User::factory()->employe()->create();
        $independant->providerProfile()->create(['provider_type' => ProviderType::INDEPENDENT->value]);

        $this->assertNull(app(ProviderOrganisationResolver::class)->pourUtilisateur($independant->fresh()->id));
    }

    public function test_un_rendez_vous_confirme_avec_un_salarie_donne_une_mission_tracee(): void
    {
        $org = $this->societePrestataire();
        $salarie = $this->salarie($org);

        $booking = Booking::factory()->create([
            'employe_id' => $salarie->id,
            'status' => BookingStatus::CONFIRME,
        ]);

        $mission = Mission::where('rendez_vous_id', $booking->id)->first();

        $this->assertNotNull($mission, 'Aucune mission créée pour un rendez-vous confirmé.');
        $this->assertSame(
            $org->id,
            $mission->provider_organization_id,
            'La mission ne porte pas la société qui l’exécute : le dispatch société restera vide.'
        );
    }

    public function test_la_mission_d_un_independant_ne_porte_aucune_societe(): void
    {
        $independant = User::factory()->employe()->create();
        $independant->providerProfile()->create(['provider_type' => ProviderType::INDEPENDENT->value]);

        $booking = Booking::factory()->create([
            'employe_id' => $independant->fresh()->id,
            'status' => BookingStatus::CONFIRME,
        ]);

        $mission = Mission::where('rendez_vous_id', $booking->id)->first();

        $this->assertNotNull($mission);
        $this->assertNull($mission->provider_organization_id);
    }

    public function test_la_societe_du_booking_prime_sur_celle_du_salarie(): void
    {
        /*
         * Le booking porte parfois déjà `assigned_provider_organization_id` — posé par un dispatch
         * ou par un contrat-cadre. C'est une DÉCISION, là où le profil du salarié n'est qu'une
         * déduction : elle prime.
         */
        $societeDuBooking = $this->societePrestataire();
        $autreSociete = $this->societePrestataire();
        $salarie = $this->salarie($autreSociete);

        $booking = Booking::factory()->create([
            'employe_id' => $salarie->id,
            'assigned_provider_organization_id' => $societeDuBooking->id,
            'status' => BookingStatus::CONFIRME,
        ]);

        $mission = Mission::where('rendez_vous_id', $booking->id)->first();

        $this->assertSame($societeDuBooking->id, $mission?->provider_organization_id);
    }

    public function test_l_ecriture_ne_declenche_pas_de_boucle_d_observateur(): void
    {
        /*
         * Le service est déclenché par un observateur sur `Booking::saved`. Compléter le booking
         * avec `save()` relancerait l'observateur, donc le service, donc `save()` : boucle. Le test
         * vérifie qu'un rendez-vous confirmé produit UNE mission et une seule.
         */
        $org = $this->societePrestataire();
        $salarie = $this->salarie($org);

        $booking = Booking::factory()->create(['employe_id' => $salarie->id, 'status' => BookingStatus::CONFIRME]);
        $booking->update(['commentaire_client' => 'Modifié après coup']);

        $this->assertSame(1, Mission::where('rendez_vous_id', $booking->id)->count());
    }

    public function test_un_independant_qui_rejoint_une_societe_y_est_vraiment_rattache(): void
    {
        /*
         * `rattacher()` créait le profil prestataire avec `firstOrCreate` : un profil EXISTANT
         * n'était pas mis à jour. L'indépendant devenait membre de l'organisation sans que rien ne
         * le rattache côté prestataire — 403 sur tout l'espace société, et ses missions hors du
         * dispatch, `ProviderOrganisationResolver` lisant précisément cette colonne.
         */
        $org = $this->societePrestataire();
        $independant = User::factory()->employe()->create();
        $independant->providerProfile()->create([
            'provider_type' => ProviderType::INDEPENDENT->value,
            'status' => 'active',
        ]);

        app(\App\Services\Organizations\OrganizationMembershipService::class)->rattacher(
            $org,
            $independant->fresh(),
            \App\Enums\OrganizationRole::WORKER->value,
        );

        $profil = $independant->fresh()->providerProfile;

        $this->assertSame($org->id, $profil?->organization_account_id);
        $this->assertSame(
            $org->id,
            app(ProviderOrganisationResolver::class)->pourUtilisateur($independant->id),
        );
    }

    public function test_le_rattachement_ne_relance_pas_une_verification_deja_tranchee(): void
    {
        // Un dossier `pending` ne doit pas passer `active` du simple fait d'un rattachement.
        $org = $this->societePrestataire();
        $utilisateur = User::factory()->employe()->create();
        $utilisateur->providerProfile()->create([
            'provider_type' => ProviderType::INDEPENDENT->value,
            'status' => 'pending',
        ]);

        app(\App\Services\Organizations\OrganizationMembershipService::class)->rattacher(
            $org,
            $utilisateur->fresh(),
            \App\Enums\OrganizationRole::WORKER->value,
        );

        $this->assertSame('pending', $utilisateur->fresh()->providerProfile?->status);
    }
}
