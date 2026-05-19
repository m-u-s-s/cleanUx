<?php

use Doctrine\DBAL\Types\Type;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bug fix — bookings.surface était défini en UNSIGNED INTEGER depuis la
 * migration initiale, mais le formulaire de réservation y écrit des
 * identifiants de tranche ("50_100", "100_150", "plus_250"…).
 *
 * MySQL en mode strict refuse l'insertion ("Data truncated for column
 * 'surface'"). SQLite (tests) acceptait silencieusement → bug invisible
 * en CI, fatal en prod.
 *
 * Migration : si la colonne existe et n'est pas déjà une string, on la
 * convertit en VARCHAR(255). Les valeurs entières existantes deviennent
 * leurs équivalents textuels (ex: 100 → "100"), sans perte.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bookings') || ! Schema::hasColumn('bookings', 'surface')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        // Détection du type actuel
        $isAlreadyString = $this->columnIsString('bookings', 'surface');
        if ($isAlreadyString) {
            return;
        }

        // SQLite ne supporte pas ALTER COLUMN nativement, mais Doctrine DBAL
        // (présent dans le projet) gère la transition via recréation de table.
        // On encadre dans try/catch pour ne pas bloquer le rejeu si le driver
        // ne le supporte pas (ex: vieux mariadb).
        try {
            Schema::table('bookings', function (Blueprint $table) {
                $table->string('surface', 255)->nullable()->change();
            });
        } catch (\Throwable $e) {
            // Fallback brut pour MySQL/MariaDB
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement('ALTER TABLE `bookings` MODIFY `surface` VARCHAR(255) NULL');
            } else {
                throw $e;
            }
        }
    }

    public function down(): void
    {
        // Pas de rollback : repasser en int risquerait de perdre les valeurs
        // non numériques. On laisse en string.
    }

    /**
     * Tente de détecter si une colonne est déjà de type string.
     * Tolère l'absence de Doctrine DBAL (fallback heuristique sur driver).
     */
    protected function columnIsString(string $table, string $column): bool
    {
        try {
            $type = Schema::getColumnType($table, $column);
            return in_array(strtolower($type), ['string', 'varchar', 'text', 'char'], true);
        } catch (\Throwable $e) {
            // Si on ne peut pas savoir, on laisse passer la conversion.
            return false;
        }
    }
};
