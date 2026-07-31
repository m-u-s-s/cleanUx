<?php

use App\Models\MissionMedia;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aligne les photos de terrain sur le vocabulaire que l'application lit réellement.
 *
 * Le contrôleur terrain posait `before`/`after` ; tous les lecteurs — récapitulatif client, photos
 * de site côté société, tableau d'exécution, score qualité, rapport PDF — filtrent sur
 * `before_photo`/`after_photo`. Les photos prises sur place étaient donc écrites dans le vide.
 *
 * Corriger l'écrivain ne suffit pas : les lignes déjà posées resteraient invisibles. Elles sont
 * renommées ici, ce qui les rend enfin lisibles par les écrans auxquelles elles étaient destinées.
 *
 * Sans danger : aucun lecteur n'interroge les valeurs courtes, vérifié sur l'ensemble de `app/` et
 * `resources/views/`. Rien ne peut donc dépendre de leur survie.
 */
return new class extends Migration
{
    /** Ancienne orthographe → orthographe lue par l'application. */
    private const RENAMES = [
        'before' => MissionMedia::TYPE_BEFORE_PHOTO,
        'after' => MissionMedia::TYPE_AFTER_PHOTO,
    ];

    public function up(): void
    {
        if (! Schema::hasTable('mission_media')) {
            return;
        }

        foreach (self::RENAMES as $legacy => $current) {
            DB::table('mission_media')
                ->where('media_type', $legacy)
                ->update(['media_type' => $current]);
        }
    }

    /**
     * Volontairement sans effet.
     *
     * Rétablir `before`/`after` rendrait ces photos invisibles à nouveau, et rien ne distingue une
     * ligne renommée par cette migration d'une ligne écrite correctement par le tableau d'exécution
     * — les rétablir toutes casserait donc aussi les secondes.
     */
    public function down(): void {}
};
