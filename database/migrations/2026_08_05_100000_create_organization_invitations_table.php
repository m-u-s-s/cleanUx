<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * INVITER UN EMPLOYÉ SANS COMPTE N'AVAIT NULLE PART OÙ S'ÉCRIRE.
 *
 * `TeamManagement::invite()` s'arrêtait sur un TODO pour cette branche : ni jeton, ni trace, ni
 * email. Cette table lui donne un support durable.
 *
 * POURQUOI PAS `team_invitations`. Cette table existe déjà — c'est un vestige de Jetstream : la
 * fonctionnalité `teams` est commentée dans `config/jetstream.php`, aucune ligne du dépôt ne la
 * référence, et elle porte un `team_id` sans rapport avec `organization_accounts`. La détourner
 * aurait lié nos invitations à un concept mort. On la laisse donc intacte.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Idempotente : rejouable sans erreur sur une base déjà migrée.
        if (Schema::hasTable('organization_invitations')) {
            return;
        }

        Schema::create('organization_invitations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_account_id')
                ->constrained('organization_accounts')
                ->cascadeOnDelete();

            $table->string('email');
            $table->string('role', 50);

            $table->foreignId('invited_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Le jeton voyage dans un lien d'email : unique et non devinable.
            $table->string('token', 64)->unique();

            $table->string('status', 20)->default('pending');   // pending | accepted | revoked
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();

            $table->timestamps();

            // On cherche toujours « les invitations en cours de cette organisation ».
            $table->index(['organization_account_id', 'status']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_invitations');
    }
};
