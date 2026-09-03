<?php

namespace App\Services\Email;

/**
 * CE QUI EST ARRIVÉ À UN ENVOI, ET POURQUOI.
 *
 * Un booléen aurait suffi à dire « parti / pas parti ». Il n'aurait pas dit si le refus vient du
 * plafond, d'un désabonnement ou d'un gabarit inactif — et c'est précisément ce qu'on veut lire
 * quand un e-mail attendu n'arrive pas.
 */
final class ResultatDEnvoi
{
    private function __construct(
        public readonly bool $parti,
        public readonly string $raison,
        public readonly ?int $messageId = null,
        public readonly ?string $objet = null,
    ) {}

    public static function parti(int $messageId, string $objet): self
    {
        return new self(true, 'Envoyé.', $messageId, $objet);
    }

    public static function refuse(string $raison): self
    {
        return new self(false, $raison);
    }
}
