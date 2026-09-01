<?php

namespace App\Providers;

use App\Services\Automation\Actions\EnvoyerLePingAuClient;
use App\Services\Automation\Actions\Journaliser;
use App\Services\Automation\Actions\NotifierLesAdmins;
use App\Services\Automation\Actions\RelancerLaRecherche;
use App\Services\Automation\Declencheurs\AlerteMetierDeclencheur;
use App\Services\Automation\Descripteurs\AlerteDescriptor;
use App\Services\Automation\Descripteurs\BookingDescriptor;
use App\Services\Automation\Descripteurs\MissionDescriptor;
use App\Services\Automation\Registre\ActionRegistre;
use App\Services\Automation\Registre\DeclencheurRegistre;
use App\Services\Automation\Registre\EntiteRegistre;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/** Le vocabulaire du moteur : ce qui existe, et rien d'autre. */
class AutomationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ActionRegistre::class, function (Application $app): ActionRegistre {
            $registre = new ActionRegistre;
            $registre->enregistrer(new Journaliser);
            $registre->enregistrer(new NotifierLesAdmins);
            // Celles-ci ecrivent dans le domaine : elles naissent « a valider », voir la spec.
            $registre->enregistrer($app->make(EnvoyerLePingAuClient::class));
            $registre->enregistrer($app->make(RelancerLaRecherche::class));

            return $registre;
        });

        $this->app->singleton(EntiteRegistre::class, function (): EntiteRegistre {
            $registre = new EntiteRegistre;
            $registre->enregistrer('booking', BookingDescriptor::class);
            $registre->enregistrer('alerte', AlerteDescriptor::class);
            $registre->enregistrer('mission', MissionDescriptor::class);

            return $registre;
        });

        $this->app->singleton(DeclencheurRegistre::class, function (): DeclencheurRegistre {
            $registre = new DeclencheurRegistre;
            $registre->enregistrer(new AlerteMetierDeclencheur('payment_capture_failed', 'La capture d\'un paiement a échoué'));
            $registre->enregistrer(new AlerteMetierDeclencheur('payout_failed', 'Un versement prestataire a échoué'));
            $registre->enregistrer(new AlerteMetierDeclencheur('webhook_backlog', 'La file de webhooks déborde'));
            $registre->enregistrer(new AlerteMetierDeclencheur('stuck_mission_holding_funds', 'Une mission bloquée retient des fonds'));
            $registre->enregistrer(new AlerteMetierDeclencheur('reconciliation_divergence', 'La réconciliation diverge'));

            return $registre;
        });
    }
}
