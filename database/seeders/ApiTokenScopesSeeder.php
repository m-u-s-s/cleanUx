<?php

namespace Database\Seeders;

use App\Models\ApiTokenScope;
use Illuminate\Database\Seeder;

class ApiTokenScopesSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            // bookings
            ['read:bookings', 'Lire les bookings', 'Accès lecture aux bookings (own + scope user)', 'read', null, false],
            ['write:bookings', 'Modifier les bookings', 'Créer / modifier / annuler des bookings', 'write', null, false],
            // providers
            ['read:providers', 'Lire les providers', 'Lister/voir profils provider', 'read', null, false],
            ['write:providers', 'Modifier les providers', 'Update profil provider', 'write', null, true],
            // clients
            ['read:clients', 'Lire les clients', 'Lister/voir profils client', 'read', null, false],
            // payments
            ['read:payments', 'Lire les paiements', 'Voir factures, refunds, transactions', 'read', null, false],
            ['write:payments', 'Initier paiements/refunds', 'Créer paiements, déclencher refunds Stripe', 'write', null, true],
            // contracts
            ['read:contracts', 'Lire les contrats', 'Lister contrats + signatures + PDFs', 'read', null, false],
            // analytics
            ['read:analytics', 'Lire les analytics', 'Funnels, cohorts, KPIs', 'read', null, false],
            // availability
            ['read:availability', 'Lire disponibilités', 'Lire les slots provider', 'read', null, false],
            ['write:availability', 'Modifier disponibilités', 'CRUD slots et exceptions', 'write', null, false],
            // invoices
            ['read:invoices', 'Lire les factures', 'Accès factures PDF + listing', 'read', null, false],
            // disputes
            ['read:disputes', 'Lire les litiges', 'Voir tickets SAV / disputes', 'read', null, false],
            ['write:disputes', 'Gérer les litiges', 'Créer / résoudre tickets SAV', 'write', null, false],
            // quality
            ['read:quality', 'Lire qualité', 'Inspections + checklists', 'read', null, false],
            // admin
            ['admin:webhooks', 'Gérer les webhooks B2B', 'CRUD endpoints + replay', 'admin', 'admin', true],
            ['admin:users', 'Gérer les utilisateurs', 'CRUD users + roles', 'admin', 'admin', true],
            ['admin:everything', 'Accès admin total', 'Wildcard — équivalent abilities=[*]', 'admin', 'admin', true],
        ];

        foreach ($catalog as [$code, $name, $description, $category, $requiredRole, $isDangerous]) {
            ApiTokenScope::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'description' => $description,
                    'category' => $category,
                    'required_role' => $requiredRole,
                    'is_active' => true,
                    'is_dangerous' => $isDangerous,
                ],
            );
        }
    }
}
