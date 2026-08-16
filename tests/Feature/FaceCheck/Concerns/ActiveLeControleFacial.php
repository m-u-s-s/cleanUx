<?php

namespace Tests\Feature\FaceCheck\Concerns;

use App\Models\PlatformModule;
use App\Models\ProviderProfile;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use App\Services\FaceCheck\FaceCheckRequirement;
use App\Services\FaceCheck\FaceCheckSettings;
use Illuminate\Support\Facades\DB;

/**
 * LES QUATRE CONDITIONS RÉUNIES — sans quoi le module est simplement éteint.
 *
 * Un prestataire n'est soumis au contrôle facial que si TOUT est vrai à la fois : le module est
 * allumé, sa zone est dans l'audience, un de ses métiers coche `requires_face_check`, et il a bien
 * un profil prestataire. Une fixture qui en oublie une décrit un module éteint — et un test de
 * blocage passerait alors au vert en mesurant l'inaction.
 *
 * C'est la même leçon que `OuvreLeCatalogue` : l'absence de configuration n'est pas un état
 * neutre, c'est un état FERMÉ, et il faut l'ouvrir explicitement.
 */
trait ActiveLeControleFacial
{
    protected ServiceZone $zoneDuControle;

    protected Trade $metierDuControle;

    protected function activerLeControleFacial(?ServiceZone $zone = null, ?Trade $metier = null): void
    {
        $this->zoneDuControle = $zone ?? ServiceZone::factory()->create();
        $this->metierDuControle = $metier ?? Trade::factory()->create();

        DB::table('trades')->where('id', $this->metierDuControle->id)
            ->update(['requires_face_check' => true]);

        PlatformModule::query()->updateOrCreate(
            ['key' => 'security.face_check'],
            [
                'name' => 'Vérification faciale des prestataires',
                'category' => 'ops',
                'rollout_strategy' => 'zone',
                'is_enabled' => true,
                'is_locked' => false,
                'sort_order' => 110,
                'settings' => ['allowed_zone_ids' => [$this->zoneDuControle->id]],
            ]
        );

        $this->oublierLesCachesDuControleFacial();
    }

    protected function eteindreLeControleFacial(): void
    {
        PlatformModule::query()->where('key', 'security.face_check')->update(['is_enabled' => false]);

        $this->oublierLesCachesDuControleFacial();
    }

    /**
     * Un prestataire dans le périmètre : profil, métier soumis, zone couverte.
     */
    protected function prestataireSoumis(array $attributs = []): User
    {
        $user = User::factory()->create(array_merge([
            'role' => 'employe',
            'account_type' => 'provider_independent',
            'is_active' => true,
            'status' => 'active',
        ], $attributs));

        ProviderProfile::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        DB::table('trade_user')->insert([
            'user_id' => $user->id,
            'trade_id' => $this->metierDuControle->id,
        ]);

        $user->forceFill(['primary_service_zone_id' => $this->zoneDuControle->id])->save();

        return $user->refresh();
    }

    /**
     * TÉMOIN NÉGATIF : un prestataire hors périmètre, pour prouver qu'on mesure bien la garde et
     * non une panne générale.
     */
    protected function prestataireHorsPerimetre(): User
    {
        $autreMetier = Trade::factory()->create();
        $autreZone = ServiceZone::factory()->create();

        $user = User::factory()->create([
            'role' => 'employe',
            'account_type' => 'provider_independent',
            'is_active' => true,
            'status' => 'active',
        ]);

        ProviderProfile::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        DB::table('trade_user')->insert([
            'user_id' => $user->id,
            'trade_id' => $autreMetier->id,
        ]);

        $user->forceFill(['primary_service_zone_id' => $autreZone->id])->save();

        return $user->refresh();
    }

    protected function oublierLesCachesDuControleFacial(): void
    {
        app(FaceCheckRequirement::class)->forget();
        app(FaceCheckSettings::class)->forget();
        app()->forgetInstance(FaceCheckRequirement::class);
        app()->forgetInstance(FaceCheckSettings::class);
    }
}
