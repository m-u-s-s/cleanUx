<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LES BUDGETS ET PLAFONDS PAR LOCAL (E7).
 *
 * CE QUI SE PASSE AUJOURD'HUI. Une entreprise cliente donne à ses responsables de site le droit de
 * commander, et découvre le dépassement à la facture — un mois plus tard, quand plus rien n'est
 * annulable. Le plafond existe dans les têtes et dans un tableur ; nulle part dans l'outil.
 *
 * LE PLAFOND ALERTE, IL NE BLOQUE PAS. C'est la décision structurante de ce module, et elle se
 * défend : une intervention refusée parce qu'un budget mensuel est atteint, c'est une fuite d'eau
 * qu'on laisse couler pour une ligne comptable. L'alerte remonte à ceux qui peuvent arbitrer — et
 * c'est arbitré en connaissance de cause, pas en découvrant la facture.
 *
 * UNE PÉRIODE, UN LOCAL, UN PLAFOND. Pas de hiérarchie de budgets ni de reports d'un mois sur
 * l'autre : ces raffinements décrivent le contrôle de gestion d'un grand groupe, pas celui d'une
 * société de vingt personnes qui veut savoir si son agence de Liège dépense trop. Ce qu'on
 * n'utilise pas ne se remplit pas, et un budget à moitié rempli ment.
 */
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

            /*
             * LE SEUIL D'ALERTE, EN POURCENTAGE. Prévenir à 100 % revient à prévenir trop tard :
             * l'intervention est déjà commandée. 80 % laisse le temps d'arbitrer.
             */
            $table->unsignedTinyInteger('alert_threshold_percent')->default(80);

            // Quand la dernière alerte est partie, pour ne pas la répéter à chaque réservation.
            $table->timestamp('alerted_at')->nullable();
            $table->unsignedTinyInteger('alerted_at_percent')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Noms courts : MySQL refuse un index au-delà de 64 caractères.
            $table->index(['organization_account_id', 'period_start'], 'site_budgets_org_period_idx');
            /*
             * UNE SEULE LIGNE PAR (société, local, période). Deux budgets concurrents pour le même
             * mois rendraient le dépassement indécidable : lequel fait foi ? On le refuse en base
             * plutôt que d'arbitrer à la lecture.
             */
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
