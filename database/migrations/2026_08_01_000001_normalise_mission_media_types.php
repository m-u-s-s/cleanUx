<?php

use App\Models\MissionMedia;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Aligne les photos de terrain sur le vocabulaire que l'application lit réellement. */
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

    /** Volontairement sans effet. */
    public function down(): void {}
};
