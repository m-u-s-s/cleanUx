<?php

namespace App\Services\Assistant\Tools\Implementations;

use App\Models\ServiceCatalog;
use App\Models\Trade;
use App\Models\User;
use App\Services\Assistant\Tools\Contracts\AssistantTool;

/**
 * Donne au LLM la liste des services disponibles pour qu'il puisse :
 *   - répondre à "quels services proposez-vous ?"
 *   - choisir le bon service_catalog_id avant create_booking
 */
class ListServicesCatalogTool implements AssistantTool
{
    public function name(): string
    {
        return 'list_services_catalog';
    }

    public function description(): string
    {
        return "Liste les services proposés par CleanUx (filtrable par métier/trade). "
            . "Utile pour répondre aux questions sur l'offre, ou avant create_booking pour identifier "
            . "le bon service_catalog_id.";
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'trade_slug' => [
                    'type'        => 'string',
                    'description' => "Filtrer par métier. Ex: 'nettoyage', 'batiment', 'peinture', 'levage', 'jardinage'.",
                ],
                'is_b2b' => [
                    'type'        => 'boolean',
                    'description' => "Si true, ne renvoie que les services B2B. Si false, B2C uniquement.",
                ],
                'limit' => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'maximum'     => 30,
                    'description' => "Nombre maximum de résultats (défaut 15).",
                ],
            ],
            'required' => [],
        ];
    }

    public function authorize(User $user): bool
    {
        return true;
    }

    public function executesImmediately(): bool
    {
        return true;
    }

    public function execute(User $user, array $input): array
    {
        $limit = min((int) ($input['limit'] ?? 15), 30);

        $query = ServiceCatalog::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit($limit);

        // Filtre par trade (Phase 1)
        if (! empty($input['trade_slug'])) {
            $tradeId = Trade::where('slug', $input['trade_slug'])->value('id');
            if ($tradeId) {
                $query->where('trade_id', $tradeId);
            }
        }

        // Filtre B2B/B2C
        if (isset($input['is_b2b'])) {
            $query->where($input['is_b2b'] ? 'is_b2b_available' : 'is_personal_available', true);
        }

        $services = $query->with('trade:id,name,slug')->get();

        return [
            'count'    => $services->count(),
            'services' => $services->map(fn ($s) => [
                'id'                       => $s->id,
                'name'                     => $s->name,
                'slug'                     => $s->slug,
                'trade'                    => $s->trade?->name,
                'trade_slug'               => $s->trade?->slug,
                'description'              => $s->short_description ?? $s->description,
                'base_price'               => $s->base_price ? (float) $s->base_price : null,
                'currency'                 => $s->currency ?? 'EUR',
                'billing_unit'             => $s->billing_unit ?? 'hour',
                'default_duration_minutes' => $s->default_duration_minutes,
                'requires_quote'           => (bool) ($s->requires_quote ?? false),
                'requires_site_visit'      => (bool) ($s->requires_site_visit ?? false),
            ])->all(),
        ];
    }
}
