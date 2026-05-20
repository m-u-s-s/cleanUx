<?php

namespace App\Services\TenancyV2;

use App\Models\Tenant;

class TenantThemingService
{
    /**
     * Retourne la config theming résolue pour un tenant (overrides + defaults).
     * Si tenant=null → retourne defaults.
     */
    public function configFor(?Tenant $tenant): array
    {
        $defaults = (array) config('tenancy_v2.theming_defaults', []);
        if (! $tenant) {
            return $defaults;
        }
        $custom = (array) ($tenant->theming ?? []);
        if (! $tenant->hasFeature('custom_theming')) {
            // Plan ne permet pas custom theming → renvoie defaults uniquement
            return $defaults;
        }
        return array_replace($defaults, array_filter($custom, fn ($v) => $v !== null && $v !== ''));
    }

    /**
     * Met à jour le theming d'un tenant (validation des clés autorisées).
     */
    public function updateTheming(Tenant $tenant, array $values): Tenant
    {
        $allowed = ['logo_url', 'favicon_url', 'primary_color', 'secondary_color', 'accent_color', 'font_family', 'app_name', 'support_email', 'custom_css'];
        $clean = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $values)) {
                $clean[$key] = $values[$key];
            }
        }
        $tenant->update([
            'theming' => array_replace((array) $tenant->theming, $clean),
        ]);
        return $tenant->fresh();
    }
}
