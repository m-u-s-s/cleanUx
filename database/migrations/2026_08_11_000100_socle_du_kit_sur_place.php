<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le socle du kit « sur place » : une preuve qu'on peut opposer, et un imprévu qui se dit.
 *
 * `mission_media` existait déjà — le prestataire y déposait ses photos avant/après depuis le web.
 * Il lui manquait ce qui transforme une photo en PREUVE : une empreinte du fichier. Sans elle,
 * « voici la photo d'avant » et « voici une photo » sont la même phrase, et le jour où un client
 * conteste, rien ne distingue le cliché pris devant la porte de celui repris d'une mission
 * précédente. L'empreinte est calculée à l'enregistrement, à partir de l'octet reçu ; la
 * remplacer supposerait de remplacer aussi la ligne, ce qui laisse une trace.
 *
 * `client_visible` par défaut à VRAI parce que la photo d'état des lieux est faite POUR le client :
 * c'est elle qui le rassure quand il n'est pas là. La colonne existe pour l'exception — la photo
 * d'un incident qu'on documente avant de savoir quoi en dire.
 *
 * `mission_incidents` gagne les trois liens qui manquaient à un signalement pour être autre chose
 * qu'une note : la photo qui l'illustre, l'instant où le client a été prévenu, et le dossier de
 * litige qu'il a fini par ouvrir. Sans ce dernier, le litige repart de zéro et le prestataire
 * qui avait signalé le dégât AVANT de commencer se retrouve à devoir le prouver après.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mission_media')) {
            Schema::table('mission_media', function (Blueprint $table) {
                if (! Schema::hasColumn('mission_media', 'sha256')) {
                    // 64 caractères hexadécimaux, longueur fixe : `char` et non `string`.
                    $table->char('sha256', 64)->nullable()->after('path');
                }
                if (! Schema::hasColumn('mission_media', 'accuracy_m')) {
                    $table->float('accuracy_m')->nullable();
                }
                if (! Schema::hasColumn('mission_media', 'client_visible')) {
                    $table->boolean('client_visible')->default(true);
                }
            });

            // Nom court et explicite : MySQL refuse au-delà de 64 caractères, et le nom généré
            // par défaut sur cette table le frôle.
            $this->addIndexIfMissing('mission_media', ['mission_id', 'media_type'], 'mission_media_type_idx');
        }

        if (Schema::hasTable('mission_incidents')) {
            Schema::table('mission_incidents', function (Blueprint $table) {
                if (! Schema::hasColumn('mission_incidents', 'mission_media_id')) {
                    $table->unsignedBigInteger('mission_media_id')->nullable();
                }
                if (! Schema::hasColumn('mission_incidents', 'notified_at')) {
                    $table->timestamp('notified_at')->nullable();
                }
                if (! Schema::hasColumn('mission_incidents', 'complaint_case_id')) {
                    $table->unsignedBigInteger('complaint_case_id')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mission_media')) {
            Schema::table('mission_media', function (Blueprint $table) {
                foreach (['sha256', 'accuracy_m', 'client_visible'] as $column) {
                    if (Schema::hasColumn('mission_media', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('mission_incidents')) {
            Schema::table('mission_incidents', function (Blueprint $table) {
                foreach (['mission_media_id', 'notified_at', 'complaint_case_id'] as $column) {
                    if (Schema::hasColumn('mission_incidents', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function addIndexIfMissing(string $table, array $columns, string $name): void
    {
        $existing = collect(Schema::getIndexes($table))
            ->pluck('name')
            ->map(fn ($n) => strtolower((string) $n));

        if ($existing->contains(strtolower($name))) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
            $blueprint->index($columns, $name);
        });
    }
};
