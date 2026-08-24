<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** RENDRE À `missions` LES TROIS LIENS QUE TOUT LE CODE SUPPOSAIT DÉJÀ. */
return new class extends Migration
{
    /** @var array<string, string> colonne => table référencée */
    private const LIENS = [
        'enterprise_work_order_id' => 'enterprise_work_orders',
        'mission_batch_id' => 'mission_batches',
        'mission_task_segment_id' => 'mission_task_segments',
    ];

    public function up(): void
    {
        foreach (self::LIENS as $colonne => $cible) {
            if (Schema::hasColumn('missions', $colonne) || ! Schema::hasTable($cible)) {
                continue;
            }

            Schema::table('missions', function (Blueprint $table) use ($colonne, $cible) {
                $table->unsignedBigInteger($colonne)->nullable()->after('booking_id');
                $table->index($colonne);
                $table->foreign($colonne)->references('id')->on($cible)->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::LIENS) as $colonne) {
            if (! Schema::hasColumn('missions', $colonne)) {
                continue;
            }

            Schema::table('missions', function (Blueprint $table) use ($colonne) {
                // L'ordre compte : la contrainte s'appuie sur l'index, et MySQL refuse de retirer
                // l'index tant que la clé étrangère existe.
                $table->dropForeign([$colonne]);
                $table->dropIndex([$colonne]);
                $table->dropColumn($colonne);
            });
        }
    }
};
