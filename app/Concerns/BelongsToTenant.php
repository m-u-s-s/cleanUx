<?php

namespace App\Concerns;

use App\Models\Tenant;
use App\Services\TenancyV2\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait pour les models qui doivent être auto-scopés par tenant.
 *
 * Ajoute :
 *  - relation tenant()
 *  - global scope qui filtre par TenantContext::current()
 *  - hook creating qui set tenant_id auto si null
 *
 * REQUIS : la table du model doit avoir une colonne `tenant_id`.
 * Pour bypass volontairement le scope (e.g. admin platform-wide) :
 *   `$model::query()->withoutGlobalScope('tenant')->get()`
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $current = app(TenantContext::class)->current();
            if ($current) {
                $builder->where($builder->getModel()->getTable() . '.tenant_id', $current->id);
            }
            // Pas de filtre si pas de context (admin platform-wide ou CLI sans tenant set)
        });

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $current = app(TenantContext::class)->current();
                if ($current) {
                    $model->tenant_id = $current->id;
                }
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
