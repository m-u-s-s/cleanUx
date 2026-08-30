<?php

namespace App\Services\Automation\Actions;

use App\Models\User;
use App\Notifications\Automation\RegleDeclencheeNotification;
use App\Services\Automation\ActionResult;
use App\Services\Automation\Contracts\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

/** Previent les administrateurs actifs. N'ecrit rien dans le domaine. */
class NotifierLesAdmins implements Action
{
    public function cle(): string
    {
        return 'notifier.admins';
    }

    public function libelle(): string
    {
        return 'Notifier les administrateurs';
    }

    public function entitesSupportees(): array
    {
        return ['booking'];
    }

    public function champs(): array
    {
        return ['message' => 'texte'];
    }

    public function toucheAuDomaine(): bool
    {
        return false;
    }

    public function executer(Model $entite, array $parametres): ActionResult
    {
        $admins = User::query()->admins()->where('is_active', true)->get();

        // Sans destinataire, on ECHOUE : une action qui n'a prevenu personne n'a pas reussi.
        if ($admins->isEmpty()) {
            return ActionResult::echouee('Aucun administrateur actif à notifier.');
        }

        Notification::send($admins, new RegleDeclencheeNotification(
            (string) ($parametres['message'] ?? ''),
            $entite
        ));

        return ActionResult::reussie($admins->count().' administrateur(s) notifié(s).');
    }
}
