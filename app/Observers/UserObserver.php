<?php

namespace App\Observers;

use App\Models\User;

/**
 * CHANGER DE NUMÉRO PERD LA VÉRIFICATION DU NUMÉRO.
 *
 * Mesuré le 2026-08-16 : un compte vérifie le numéro A par SMS à l'inscription, puis `PATCH
 * /api/profile` avec le numéro B — et `phone_verified_at` reste posé. Le compte est donc « vérifié »
 * sur un numéro que personne n'a prouvé posséder. Trois conséquences concrètes : `EnsurePhoneVerified`
 * laisse passer, les codes d'arrivée et de fin de mission partent vers ce numéro, et un support qui
 * rappelle « le numéro vérifié » appelle quelqu'un d'autre.
 *
 * Rien dans le dépôt ne remettait cette colonne à zéro — elle n'était que POSÉE, à trois endroits.
 * La règle vit ici, et pas dans les contrôleurs, parce qu'ils sont déjà QUATRE à écrire ce numéro :
 * le profil mobile client, le profil mobile générique, le profil client web, et l'administration. Une
 * garde recopiée dans quatre appelants est une garde qu'un cinquième oubliera — et l'oubli ne se voit
 * pas, puisqu'il produit un compte « vérifié » d'apparence normale.
 *
 * Le parcours de vérification, lui, écrit le numéro ET sa date dans le MÊME enregistrement : c'est
 * exactement ce que teste `isDirty('phone_verified_at')`. Sans cette condition, la vérification
 * effacerait ce qu'elle vient d'établir.
 *
 * Le pendant e-mail existait déjà (`UpdateUserProfileInformation` remet `email_verified_at` à null et
 * renvoie le lien) : cette règle-ci ne fait que rétablir la symétrie sur le téléphone.
 */
class UserObserver
{
    public function saving(User $user): void
    {
        if (! $user->exists) {
            return;
        }

        if (! $user->isDirty('phone')) {
            return;
        }

        if ($user->isDirty('phone_verified_at')) {
            return;
        }

        $user->phone_verified_at = null;
    }
}
