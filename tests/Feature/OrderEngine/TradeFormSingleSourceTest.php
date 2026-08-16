<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\Admin\OrderEngine\CatalogCenter;
use App\Livewire\Admin\Trades;
use App\Support\Livewire\Concerns\Admin\ManagesTradeForm;
use Tests\TestCase;

/**
 * Le formulaire d'un métier n'a qu'UNE définition.
 *
 * POURQUOI CE GARDE-FOU EXISTE. Le formulaire vit sur deux écrans. C'était le risque annoncé au
 * moment de choisir le formulaire complet plutôt qu'une création rapide : deux copies divergent au
 * premier champ ajouté, et l'écran oublié continue d'enregistrer des métiers INCOMPLETS sans lever
 * d'erreur — les colonnes absentes prennent leur valeur par défaut.
 *
 * Le défaut ne se verrait donc qu'à l'usage, longtemps après, quand un prestataire se demanderait
 * pourquoi son métier n'exige pas d'assurance. Ces tests le rendent impossible.
 */
class TradeFormSingleSourceTest extends TestCase
{
    public function test_les_deux_ecrans_emploient_le_meme_trait(): void
    {
        foreach ([Trades::class, CatalogCenter::class] as $composant) {
            $this->assertContains(
                ManagesTradeForm::class,
                class_uses_recursive($composant),
                "{$composant} doit employer ManagesTradeForm plutôt que sa propre copie du formulaire.",
            );
        }
    }

    public function test_aucun_ecran_ne_redeclare_les_champs_du_formulaire(): void
    {
        /*
         * On lit les FICHIERS et non les classes : une propriété redéclarée dans le composant
         * masquerait celle du trait sans que rien ne le signale à l'exécution.
         */
        $champs = ['emergency_multiplier', 'night_multiplier', 'requires_certification', 'booking_form_schema_json'];

        $fichiers = [
            'app/Livewire/Admin/Trades.php',
            'app/Livewire/Admin/OrderEngine/CatalogCenter.php',
        ];

        $fautifs = [];

        foreach ($fichiers as $fichier) {
            $source = file_get_contents(base_path($fichier));

            foreach ($champs as $champ) {
                if (preg_match('/public\s+[^\s]+\s+\$'.$champ.'\b/', (string) $source)) {
                    $fautifs[] = "{$fichier} redéclare \${$champ}";
                }
            }
        }

        $this->assertSame([], $fautifs);
    }

    public function test_les_deux_vues_incluent_la_meme_partial(): void
    {
        $vues = [
            'resources/views/livewire/admin/trades.blade.php',
            'resources/views/livewire/admin/order-engine/catalog-center.blade.php',
        ];

        foreach ($vues as $vue) {
            $this->assertStringContainsString(
                "@include('livewire.admin.partials.trade-form-fields')",
                (string) file_get_contents(base_path($vue)),
                "{$vue} doit inclure la partial partagée plutôt que recopier les champs.",
            );
        }
    }

    public function test_la_partial_couvre_bien_tous_les_champs(): void
    {
        /*
         * Une partial qui aurait perdu des champs rendrait les deux écrans cohérents… et tous deux
         * incomplets. Ce test est le seul qui distingue « une seule définition » de « une seule
         * définition JUSTE ».
         */
        $partial = (string) file_get_contents(
            base_path('resources/views/livewire/admin/partials/trade-form-fields.blade.php'),
        );

        /*
         * LA LISTE ÉTAIT EN RETARD DE DEUX CHAMPS.
         *
         * `requires_face_check` et `taxi_rules` vivaient dans la partial sans y figurer : ce test
         * ne vérifie pas l'exhaustivité, seulement que les champs listés sont présents. Un champ
         * oublié ici n'est donc jamais signalé — il faut l'ajouter à la main, et c'est ce qui n'a
         * pas été fait deux fois de suite. Les trois manquants sont réintégrés.
         */
        $attendus = [
            'name', 'slug', 'code', 'icon', 'color', 'sort_order',
            'short_description', 'description', 'default_hourly_rate',
            'emergency_multiplier', 'night_multiplier', 'weekend_multiplier',
            'quote_validity_days', 'sla_response_minutes', 'requires_quote_by_default',
            'booking_form_schema_json', 'is_active', 'requires_certification',
            'requires_insurance_proof', 'is_b2b_default', 'is_personal_default',
            'requires_face_check', 'taxi_rules', 'hourly_billing',
        ];

        /*
         * TOUTES LES VARIANTES DE `wire:model`, pas seulement deux.
         *
         * L'ancienne version n'acceptait que `wire:model=` et `wire:model.live.debounce.500ms=`.
         * Un champ parfaitement correct en `wire:model.live` — nécessaire dès qu'une case pilote un
         * affichage conditionnel — était donc compté comme manquant. Le test mesurait la forme de
         * la liaison, pas la présence du champ, ce qu'il est censé vérifier.
         */
        $manquants = array_values(array_filter(
            $attendus,
            fn (string $champ) => preg_match(
                '/wire:model(\.[a-z0-9.]+)?="'.preg_quote($champ, '/').'"/',
                $partial,
            ) !== 1,
        ));

        $this->assertSame([], $manquants);
    }
}
