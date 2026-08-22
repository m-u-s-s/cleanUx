<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LES CONGÉS ET ABSENCES (E21).
 *
 * Un salarié pose ses congés, un responsable approuve, et la répartition en tient compte. Ce
 * dernier point est tout l'intérêt : une demande approuvée qui n'empêche pas l'assignation ne sert
 * qu'à faire un tableau. Le prestataire reçoit sa course le premier jour de ses vacances, refuse, et
 * le moteur cherche quelqu'un d'autre — après avoir perdu vingt secondes et une occasion.
 *
 * TROIS ÉTATS, ET C'EST ASSEZ. `pending` attend, `approved` bloque le planning, `rejected` se
 * conserve — un refus qu'on efface, c'est une conversation qui recommence deux mois plus tard.
 *
 * LES DATES SONT DES JOURS, PAS DES HORODATAGES. Un congé se pose à la journée : le stocker à la
 * seconde ferait dépendre le blocage de l'heure exacte de saisie, et un congé posé à 14 h laisserait
 * la matinée assignable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_requests')) {
            return;
        }

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('organization_account_id');
            $table->unsignedBigInteger('user_id');

            // `paid`, `unpaid`, `sick`, `other` — le type ne change pas le blocage du planning, mais
            // il change ce que la paie en fait.
            $table->string('type', 20)->default('paid');

            $table->date('starts_on');
            $table->date('ends_on');

            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();

            $table->unsignedBigInteger('decided_by_user_id')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            // Nom court : MySQL refuse au-delà de 64 caractères.
            $table->index(['user_id', 'starts_on'], 'leave_requests_user_start_idx');
            $table->index(['organization_account_id', 'status'], 'leave_requests_org_status_idx');

            $table->foreign('organization_account_id')->references('id')->on('organization_accounts')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
