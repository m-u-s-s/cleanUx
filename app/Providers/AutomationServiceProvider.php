<?php

namespace App\Providers;

use App\Services\Automation\Actions\Journaliser;
use App\Services\Automation\Actions\NotifierLesAdmins;
use App\Services\Automation\Descripteurs\AlerteDescriptor;
use App\Services\Automation\Descripteurs\BookingDescriptor;
use App\Services\Automation\Descripteurs\MissionDescriptor;
use App\Services\Automation\Registre\ActionRegistre;
use App\Services\Automation\Registre\EntiteRegistre;
use Illuminate\Support\ServiceProvider;

/** Le vocabulaire du moteur : ce qui existe, et rien d'autre. */
class AutomationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ActionRegistre::class, function (): ActionRegistre {
            $registre = new ActionRegistre;
            $registre->enregistrer(new Journaliser);
            $registre->enregistrer(new NotifierLesAdmins);

            return $registre;
        });

        $this->app->singleton(EntiteRegistre::class, function (): EntiteRegistre {
            $registre = new EntiteRegistre;
            $registre->enregistrer('booking', BookingDescriptor::class);
            $registre->enregistrer('alerte', AlerteDescriptor::class);
            $registre->enregistrer('mission', MissionDescriptor::class);

            return $registre;
        });
    }
}
