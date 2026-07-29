<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Donner à `users` les horodatages Stripe Connect que le service y écrit déjà.
 *
 * `StripeConnectService::syncAccountStatus()` écrit quatre champs sur l'utilisateur :
 * `stripe_connect_status`, puis les dates d'aboutissement, d'activation des encaissements et
 * d'activation des versements. Seul le premier existait sur `users` — les trois autres étaient
 * ignorés en silence, faute d'être assignables en masse, si bien qu'aucune erreur n'a jamais
 * signalé qu'ils n'étaient pas persistés.
 *
 * `stripe_connect_onboarded_at` n'existait que sur `provider_profiles`, où il avait été créé
 * avec ce module (2026_05_04_000003) — mais rien n'écrit jamais cette table pour Stripe. Les
 * deux emplacements coexistent donc désormais, et `canReceiveStripeConnectPayments()` lit les
 * deux, en privilégiant celui qui est réellement alimenté.
 *
 * Aucune valeur n'est reprise : la date d'aboutissement d'un compte existant n'est pas
 * reconstituable après coup, et l'inventer donnerait une piste d'audit fausse. Les comptes déjà
 * actifs restent reconnus par `stripe_connect_status`, qui, lui, a toujours été écrit.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private const COLUMNS = [
        'stripe_connect_onboarded_at',
        'stripe_connect_charges_enabled_at',
        'stripe_connect_payouts_enabled_at',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                // Défensif : `stripe_connect_onboarded_at` a pu être ajouté séparément sur
                // certains environnements, cette migration ne doit pas échouer sur eux.
                if (! Schema::hasColumn('users', $column)) {
                    $table->timestamp($column)->nullable()->after('stripe_connect_status');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
