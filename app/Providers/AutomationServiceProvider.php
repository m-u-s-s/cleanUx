<?php

namespace App\Providers;

use App\Services\Automation\Actions\Journaliser;
use App\Services\Automation\Actions\NotifierLesAdmins;
use App\Services\Automation\Registre\ActionRegistre;
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
    }
}
