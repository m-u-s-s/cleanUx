<?php

namespace App\Services\EmailV2\Webhooks;

use Illuminate\Http\Request;

/**
 * UN WEBHOOK EST UN ENDPOINT PUBLIC QUI ÉCRIT EN BASE.
 *
 * Sans signature vérifiée, n'importe qui peut déclarer qu'un e-mail a été ouvert, rejeté, ou
 * qu'un destinataire s'est plaint — et fausser durablement la mesure de la plateforme.
 *
 * Un fournisseur sans vérificateur est REFUSÉ, jamais accepté « en attendant » : un webhook qui
 * accepte des charges non vérifiées est pire que pas de webhook du tout.
 */
interface VerificateurDeSignature
{
    /** Le nom du fournisseur tel qu'il apparaît dans l'URL. */
    public function fournisseur(): string;

    /** La signature de cette requête est-elle authentique ? */
    public function verifie(Request $requete): bool;

    /**
     * Les événements portés par cette requête, normalisés.
     *
     * @return list<array{provider_event_id: string, provider_message_id: ?string, event_type: string, occurred_at: ?string, payload: array<string, mixed>}>
     */
    public function evenements(Request $requete): array;
}
