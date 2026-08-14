<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\Sector;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Support\Domain\LocationRole;
use App\Support\Domain\PricingUnit;
use App\Support\Domain\QuestionType;
use Illuminate\Database\Seeder;

/**
 * UN MÉTIER DE COURSE, JOUABLE À LA MAIN DÈS LE PREMIER `migrate:fresh --seed`.
 *
 * Sans lui, tout ce chantier serait invisible : le parcours de trajet, le prix au kilomètre, la
 * mission sans code et les exigences de conduite n'existeraient qu'en tests. Le piège classique de
 * ce dépôt est précisément celui-là — un module complet dont personne ne crée les lignes, et qu'on
 * croit livré parce que la suite est verte.
 *
 * AJOUT PUR. Ce seeder ne touche à AUCUN métier existant : il en crée un, avec ses deux questions
 * de localisation et sa grille tarifaire. Les autres métiers gardent leur forfait, leurs codes de
 * début et de fin, et leur parcours terrain.
 *
 * IDEMPOTENT, comme les autres seeders de référentiel : rejouer la chaîne ne doit ni dupliquer le
 * métier ni écraser un tarif ajusté à la main en démonstration.
 */
class CourseCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $secteur = $this->secteur();
        $metier = $this->metier($secteur);

        $this->questionsDuTrajet($metier);
        $this->ouvrirDansLesZones($metier);

        $this->command?->info('✅ Métier de course semé : départ + arrivée, règles taxi, prix au kilomètre.');
    }

    private function secteur(): Sector
    {
        return Sector::firstOrCreate(
            ['slug' => 'mobilite'],
            [
                'name' => 'Mobilité',
                'tagline' => 'Se déplacer, faire déplacer',
                'icon' => 'car',
                'sort_order' => 90,
                'is_active' => true,
                'published_at' => now(),
            ],
        );
    }

    private function metier(Sector $secteur): Trade
    {
        return Trade::firstOrCreate(
            ['slug' => 'course-vtc'],
            [
                'code' => 'VTC',
                'name' => 'Course',
                'sector_id' => $secteur->id,
                'short_description' => 'Un chauffeur vous emmène d’un point à un autre.',
                'is_active' => true,
                /*
                 * L'immédiat est le mode NORMAL d'une course : personne ne réserve un taxi pour
                 * jeudi 14 h. Le rendez-vous reste ouvert — les transferts d'aéroport se planifient.
                 */
                'allows_asap' => true,
                'allows_scheduled' => true,
                'allows_bundle' => false,
                // Transport de personnes : véhicule récent, carte grise, assurance.
                'taxi_rules' => true,
                'pricing_unit' => PricingUnit::FIXED,
                // La prise en charge seule ; les kilomètres viennent de la grille de zone.
                'base_price_cents' => 0,
                'estimated_duration_min' => 20,
                'published_at' => now(),
            ],
        );
    }

    private function questionsDuTrajet(Trade $metier): void
    {
        $questions = [
            [
                'code' => 'depart',
                'label' => 'Où êtes-vous ?',
                'help_text' => 'Le chauffeur viendra vous chercher à cette adresse.',
                'role' => LocationRole::PICKUP,
                'sort_order' => 1,
            ],
            [
                'code' => 'arrivee',
                'label' => 'Où allez-vous ?',
                'help_text' => 'La distance et le prix sont calculés à partir de ces deux points.',
                'role' => LocationRole::DROPOFF,
                'sort_order' => 2,
            ],
        ];

        foreach ($questions as $question) {
            Question::updateOrCreate(
                ['trade_id' => $metier->id, 'code' => $question['code']],
                [
                    'label' => $question['label'],
                    'help_text' => $question['help_text'],
                    'type' => QuestionType::LOCATION,
                    'location_role' => $question['role'],
                    'is_required' => true,
                    'is_active' => true,
                    // Posées en mode immédiat AUSSI : sans elles, une course n'a ni départ ni
                    // arrivée, et le questionnaire raccourci de l'immédiat les écarterait.
                    'is_essential' => true,
                    // Aucune porte de sortie : « je ne sais pas où je vais » n'est pas une course.
                    'allows_unknown' => false,
                    'sort_order' => $question['sort_order'],
                ],
            );
        }
    }

    /**
     * Ouvre la course dans toutes les zones actives, avec un tarif au kilomètre.
     *
     * L'absence de ligne vaut FERMÉ : sans ce geste, le métier existerait au catalogue et ne serait
     * vendable nulle part — exactement le genre de module « livré » que personne ne peut essayer.
     */
    private function ouvrirDansLesZones(Trade $metier): void
    {
        ServiceZone::query()->where('status', 'active')->get()->each(function (ServiceZone $zone) use ($metier) {
            TradeZonePricing::updateOrCreate(
                ['trade_id' => $metier->id, 'service_zone_id' => $zone->id],
                [
                    'base_rate_cents' => 0,
                    'surge_multiplier' => '1.00',
                    'is_active' => true,
                    'asap_enabled' => true,
                    'distance_pricing_enabled' => true,
                    // 2,50 € de prise en charge, 1,40 €/km au-delà du premier kilomètre, 0,30 €/min.
                    // Des ordres de grandeur réalistes : un tarif fantaisiste rendrait la
                    // démonstration inutilisable pour juger du produit.
                    'pickup_fee_cents' => 250,
                    'price_per_km_cents' => 140,
                    'price_per_minute_cents' => 30,
                    'included_km' => 1,
                ],
            );
        });
    }
}
