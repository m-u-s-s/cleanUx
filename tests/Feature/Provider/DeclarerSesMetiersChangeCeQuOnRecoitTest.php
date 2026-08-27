<?php

namespace Tests\Feature\Provider;

use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use App\Services\Catalog\ProviderCoverageWriter;
use App\Services\Onboarding\ProviderOnboardingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DÉCLARER SES MÉTIERS NE CHANGEAIT RIEN À CE QU'ON POUVAIT RECEVOIR.
 *
 * L'étape « Compétences » de l'onboarding écrivait dans `provider_profiles.skills` — un champ lu
 * par DEUX endroits seulement : l'assistant lui-même, pour se re-remplir, et le compteur
 * d'avancement, pour un booléen `has_skills`.
 *
 * La répartition, elle, joint `trade_user` (`CandidateFinder`). Un prestataire pouvait donc
 * traverser tout le parcours, lire « ✅ Compétences enregistrées », afficher un dossier complet,
 * et rester invisible à toute mission.
 *
 * ON AJOUTE, ON NE REMPLACE PAS : aucun des deux écrans ne pré-cochait la couverture réelle, et
 * celui du natif dit lui-même servir « à confirmer, et à en ajouter ».
 */
class DeclarerSesMetiersChangeCeQuOnRecoitTest extends TestCase
{
    use RefreshDatabase;

    private function prestataire(): User
    {
        return User::factory()->employe()->create();
    }

    /**
     * La base de test est vierge : le catalogue s'y sème. On ne prend PAS le catalogue de
     * developpement, qui rendrait ces cas dependants d'un jeu de donnees.
     *
     * @return Collection<int, Trade>
     */
    private function metiers(int $combien = 1)
    {
        return Trade::factory()->count($combien)->create(['is_active' => true]);
    }

    private function zone(): ServiceZone
    {
        return ServiceZone::factory()->create(['status' => 'active']);
    }

    public function test_le_metier_declare_atterrit_dans_la_table_que_la_repartition_lit(): void
    {
        $user = $this->prestataire();
        $metier = $this->metiers()->first();

        app(ProviderOnboardingService::class)->setSkills($user, [$metier->slug]);

        $this->assertTrue(
            $user->fresh()->trades()->whereKey($metier->id)->exists(),
            'Le métier déclaré n’est pas dans `trade_user` : la répartition ne le verra jamais.'
        );
    }

    /**
     * LE TÉMOIN. Sans lui, le cas ci-dessus passerait au vert si la méthode rattachait TOUT le
     * catalogue : on ne mesurerait plus ce qui a été déclaré.
     */
    public function test_temoin_un_metier_non_declare_reste_dehors(): void
    {
        $user = $this->prestataire();
        $metiers = $this->metiers(2);

        app(ProviderOnboardingService::class)->setSkills($user, [$metiers[0]->slug]);

        $this->assertFalse(
            $user->fresh()->trades()->whereKey($metiers[1]->id)->exists(),
            'Un métier que personne n’a déclaré a été rattaché.'
        );
    }

    /** Les trois écritures acceptées, parce que le natif envoie des slugs et l'admin des identifiants. */
    public function test_le_slug_le_code_et_l_identifiant_menent_au_meme_metier(): void
    {
        $metier = $this->metiers()->first();

        foreach ([$metier->slug, $metier->code, (string) $metier->id] as $ecriture) {
            $user = $this->prestataire();

            app(ProviderOnboardingService::class)->setSkills($user, [$ecriture]);

            $this->assertTrue(
                $user->fresh()->trades()->whereKey($metier->id)->exists(),
                "L’écriture « {$ecriture} » n’a pas été reconnue."
            );
        }
    }

    /**
     * L'UNION. L'écran natif part d'une liste VIDE : un `sync` détachant effacerait les métiers
     * déclarés à l'inscription dès qu'on rouvre l'étape pour en ajouter un.
     */
    public function test_l_etape_n_efface_pas_ce_qui_a_ete_declare_a_l_inscription(): void
    {
        $user = $this->prestataire();
        $metiers = $this->metiers(2);

        app(ProviderCoverageWriter::class)->sync($user, [$metiers[0]->id], []);

        app(ProviderOnboardingService::class)->setSkills($user, [$metiers[1]->slug]);

        $couverture = $user->fresh()->trades()->pluck('trades.id')->all();

        $this->assertContains($metiers[0]->id, $couverture, 'Le métier de l’inscription a été effacé.');
        $this->assertContains($metiers[1]->id, $couverture, 'Le métier ajouté n’a pas été retenu.');
    }

    /** Un identifiant qui ne se résout pas ne doit rien détruire — ni la couverture, ni l'étape. */
    public function test_un_identifiant_inconnu_ne_vide_pas_la_couverture(): void
    {
        $user = $this->prestataire();
        $metier = $this->metiers()->first();

        app(ProviderCoverageWriter::class)->sync($user, [$metier->id], []);

        app(ProviderOnboardingService::class)->setSkills($user, ['cleaning_residential']);

        $this->assertTrue(
            $user->fresh()->trades()->whereKey($metier->id)->exists(),
            'Un code inconnu a fait perdre la couverture existante.'
        );

        // Ce qui a été déclaré reste visible dans le dossier, même non résolu.
        $this->assertContains('cleaning_residential', $user->providerProfile()->first()->skills);
    }

    /**
     * LA ZONE PRINCIPALE. L'étape posait les zones sans jamais écrire `primary_service_zone_id` —
     * et le commentaire du seul écrivain légitime dit qu'un prestataire sans elle reste invisible
     * aux rendez-vous.
     */
    public function test_declarer_une_zone_pose_enfin_la_zone_principale(): void
    {
        $user = $this->prestataire();
        $metier = $this->metiers()->first();
        $zone = $this->zone();

        app(ProviderOnboardingService::class)->setSkills($user, [$metier->slug], [$zone->id]);

        $this->assertSame($zone->id, $user->fresh()->primary_service_zone_id);
    }
}
