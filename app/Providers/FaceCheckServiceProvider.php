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

        /*
         * Singletons volontaires : les deux mémoïsent une lecture de base — la ligne du module et
         * la liste des métiers soumis. Le dispatch les interroge une fois par candidat ; en
         * instances neuves, une recherche de trente prestataires ferait soixante requêtes pour
         * deux réponses qui ne bougent pas dans la requête.
         */
        $this->app->singleton(FaceCheckSettings::class);
        $this->app->singleton(FaceCheckRequirement::class);
    }

    /**
     * PAS DE BASCULE IMPLICITE, contrairement au module KYC.
     *
     * `KycServiceProvider` passe tout seul du bouchon à Onfido dès qu'un `ONFIDO_API_TOKEN` traîne
     * dans l'environnement. C'est commode pour une montée en charge progressive, et c'est
     * exactement ce qu'il ne faut pas ici : poser une clé pour un autre module ferait basculer,
     * sans que personne le décide, un contrôle d'identité vers un service payant et distant — avec
     * des verdicts différés là où le bouchon répondait dans la seconde, et des prestataires
     * bloqués en attente. Le fournisseur se choisit, il ne se devine pas.
     */
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
