<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\Admin\OrderEngine\CatalogCenter;
use App\Livewire\Admin\Trades;
use App\Support\Livewire\Concerns\Admin\ManagesTradeForm;
use Tests\TestCase;

/** Le formulaire d'un métier n'a qu'UNE définition. POURQUOI CE GARDE-FOU EXISTE. */
class TradeFormSingleSourceTest extends TestCase
{
    public function test_les_deux_ecrans_emploient_le_meme_trait(): void
    {
        $sansTrait = array_values(array_filter(
            [Trades::class, CatalogCenter::class],
            fn (string $composant) => ! in_array(ManagesTradeForm::class, class_uses_recursive($composant), true),
        ));

        $this->assertSame(
            [],
            $sansTrait,
            'Ces composants doivent employer ManagesTradeForm plutôt que leur propre copie du formulaire.',
        );
    }

    public function test_aucun_ecran_ne_redeclare_les_champs_du_formulaire(): void
    {
        // On lit les FICHIERS et non les classes : une propriété redéclarée dans le composant masquerait celle du trait sans que rien ne le signale à l'exécution.
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

        // Toutes les vues qui recopient d'un coup : une duplication de formulaire se propage, et
        // c'est le nombre de copies qui dit s'il faut refactoriser ou corriger.
        $copieuses = array_values(array_filter(
            $vues,
            fn (string $vue) => ! str_contains(
                (string) file_get_contents(base_path($vue)),
                "@include('livewire.admin.partials.trade-form-fields')",
            ),
        ));

        $this->assertSame([], $copieuses, 'Ces vues recopient les champs au lieu d inclure la partial partagee.');
    }

    public function test_la_partial_couvre_bien_tous_les_champs(): void
    {
        // Une partial qui aurait perdu des champs rendrait les deux écrans cohérents… et tous deux incomplets.
        $partial = (string) file_get_contents(
            base_path('resources/views/livewire/admin/partials/trade-form-fields.blade.php'),
        );

        // LA LISTE ÉTAIT EN RETARD DE DEUX CHAMPS.
        $attendus = [
            'name', 'slug', 'code', 'icon', 'color', 'sort_order',
            'short_description', 'description', 'default_hourly_rate',
            'emergency_multiplier', 'night_multiplier', 'weekend_multiplier',
            'quote_validity_days', 'sla_response_minutes', 'requires_quote_by_default',
            'booking_form_schema_json', 'is_active', 'requires_certification',
            'requires_insurance_proof', 'is_b2b_default', 'is_personal_default',
            'requires_face_check', 'taxi_rules', 'hourly_billing',
        ];

        // TOUTES LES VARIANTES DE `wire:model`, pas seulement deux.
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
