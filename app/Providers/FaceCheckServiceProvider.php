<?php

namespace App\Providers;

use App\Services\FaceCheck\FaceCheckRequirement;
use App\Services\FaceCheck\FaceCheckSettings;
use App\Services\FaceCheck\FaceMatchProviderInterface;
use App\Services\FaceCheck\Providers\FaceMatchMockProvider;
use App\Services\FaceCheck\Providers\OnfidoFaceMatchProvider;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class FaceCheckServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FaceMatchProviderInterface::class, fn () => $this->resolveProvider());

        // Singletons volontaires : les deux mémoïsent une lecture de base — la ligne du module et la liste des métiers soumis.
        $this->app->singleton(FaceCheckSettings::class);
        $this->app->singleton(FaceCheckRequirement::class);
    }

    /** PAS DE BASCULE IMPLICITE, contrairement au module KYC. */
    protected function resolveProvider(): FaceMatchProviderInterface
    {
        $choisi = strtolower((string) config('face_check.provider', 'mock'));

        return match ($choisi) {
            'mock' => new FaceMatchMockProvider,
            'onfido' => new OnfidoFaceMatchProvider,
            default => throw new RuntimeException("Fournisseur de comparaison faciale inconnu : {$choisi}"),
        };
    }
}
