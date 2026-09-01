<?php

namespace App\Services\Automation\Actions;

use App\Models\Mission;
use App\Services\Automation\ActionResult;
use App\Services\Automation\Contracts\Action;
use App\Services\Missions\OnSite\MissionCheckInService;
use Illuminate\Database\Eloquent\Model;

/** Envoie le « tout va bien ? » au client. La regle du « une seule fois » reste au service. */
class EnvoyerLePingAuClient implements Action
{
    public function __construct(protected MissionCheckInService $checkin) {}

    public function cle(): string
    {
        return 'mission.ping_client';
    }

    public function libelle(): string
    {
        return 'Envoyer le « tout va bien ? » au client';
    }

    public function entitesSupportees(): array
    {
        // La mission SEULE : le service part d'elle pour retrouver la reservation et son client.
        return ['mission'];
    }

    public function champs(): array
    {
        return [];
    }

    public function toucheAuDomaine(): bool
    {
        return true;
    }

    public function executer(Model $entite, array $parametres): ActionResult
    {
        if (! $entite instanceof Mission) {
            return ActionResult::echouee('Cette action ne vise que les missions.');
        }

        // Les TROIS causes de `false`, dans l'ordre du service : deja parti, personne a joindre,
        // notification qui leve. En nommer deux enverrait chercher au mauvais endroit.
        return $this->checkin->envoyerLePing($entite)
            ? ActionResult::reussie('Ping de présence envoyé au client.')
            : ActionResult::echouee('Ping non envoyé : déjà parti, aucun client à joindre, ou notification en échec.');
    }
}
