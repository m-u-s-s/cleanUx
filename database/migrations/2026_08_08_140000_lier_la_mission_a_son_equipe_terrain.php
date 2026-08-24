<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** UNE ÉQUIPE CRÉÉE DANS L'ESPACE SOCIÉTÉ NE POUVAIT PAS RECEVOIR DE MISSION. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('missions') && ! Schema::hasColumn('missions', 'field_team_id')) {
            Schema::table('missions', function (Blueprint $table) {
                // Nullable et SANS contrainte de clé étrangère déclarée ici.
                $table->unsignedBigInteger('field_team_id')->nullable()->after('provider_team_id');

                // Nom EXPLICITE et court : MySQL plafonne les identifiants à 64 caractères, limite
                // que SQLite ignore. La requête chaude est « les missions de cette équipe ».
                $table->index('field_team_id', 'missions_field_team_idx');
            });
        }

        if (! Schema::hasTable('mission_assignments')) {
            return;
        }

        Schema::table('mission_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_assignments', 'reassigned_by')) {
                $table->unsignedBigInteger('reassigned_by')->nullable();
            }

            if (! Schema::hasColumn('mission_assignments', 'reassignment_reason')) {
                $table->string('reassignment_reason', 255)->nullable();
            }
        });
    }

    /** `down()` NE FAIT RIEN, et c'est la règle de ce chantier. */
    public function down(): void
    {
        // Volontairement vide : migrations non destructives uniquement.
    }
};
