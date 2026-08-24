<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** LES BUDGETS ET PLAFONDS PAR LOCAL (E7). CE QUI SE PASSE AUJOURD'HUI. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('organization_site_budgets')) {
            return;
        }

        Schema::create('organization_site_budgets', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_account_id');
            // Nullable : un budget qui ne vise aucun local est celui de TOUTE la société. C'est le
            // premier que la plupart poseront, avant de descendre au local.
            $table->unsignedBigInteger('organization_site_id')->nullable();

            // `monthly` ou `quarterly`. Le budget annuel se dit en mensuel divisé par douze, et
            // suivre douze mois d'écart d'un coup n'aide personne à réagir.
            $table->string('period', 20)->default('monthly');
            // Le premier jour de la période couverte — la clé qui rend une ligne unique.
            $table->date('period_start');

            $table->unsignedBigInteger('limit_cents');
            $table->string('currency', 3)->default('EUR');

            // LE SEUIL D'ALERTE, EN POURCENTAGE.
            $table->unsignedTinyInteger('alert_threshold_percent')->default(80);

            // Quand la dernière alerte est partie, pour ne pas la répéter à chaque réservation.
            $table->timestamp('alerted_at')->nullable();
            $table->unsignedTinyInteger('alerted_at_percent')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Noms courts : MySQL refuse un index au-delà de 64 caractères.
            $table->index(['organization_account_id', 'period_start'], 'site_budgets_org_period_idx');
            // UNE SEULE LIGNE PAR (société, local, période).
            $table->unique(
                ['organization_account_id', 'organization_site_id', 'period', 'period_start'],
                'site_budgets_unique_period',
            );

            $table->foreign('organization_account_id')->references('id')->on('organization_accounts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_site_budgets');
    }
};
