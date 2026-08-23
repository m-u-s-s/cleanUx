<?php

namespace App\Services\Organizations;

/** POURQUOI UNE ACTION SUR UN MEMBRE A ÉTÉ REFUSÉE. LE SERVICE DÉCIDE, LA SURFACE RÉPOND. */
enum MotifDeRefus: string
{
    /** L'identifiant ne désigne aucun membre de CETTE organisation. */
    case INTROUVABLE = 'introuvable';

    /** L'acteur n'a pas la clé qui gouverne cette action. */
    case PERMISSION = 'permission';

    /** L'acteur ne dépasse pas en rang la personne visée. */
    case HIERARCHIE = 'hierarchie';

    /** On ne promeut personne à son propre rang ou au-dessus. */
    case PROMOTION_TROP_HAUTE = 'promotion_trop_haute';

    /** Retirer, suspendre ou déclasser le dernier propriétaire actif enfermerait la société. */
    case DERNIER_PROPRIETAIRE = 'dernier_proprietaire';

    /** On ne se suspend ni ne se retire soi-même. */
    case SOI_MEME = 'soi_meme';

    /** Le rôle demandé n'existe pas, ou pas pour ce type d'organisation. */
    case ROLE_INCONNU = 'role_inconnu';

    /** Le message rendu à un humain. */
    public function message(): string
    {
        return match ($this) {
            self::INTROUVABLE => 'Ce membre est introuvable dans votre société.',
            self::PERMISSION => "Vous n'avez pas le droit d'effectuer cette action.",
            self::HIERARCHIE => "Vous ne pouvez pas agir sur un membre d'un rang supérieur ou égal au vôtre.",
            self::PROMOTION_TROP_HAUTE => 'Vous ne pouvez pas attribuer un rôle égal ou supérieur au vôtre.',
            self::DERNIER_PROPRIETAIRE => 'La société doit conserver au moins un propriétaire actif.',
            self::SOI_MEME => 'Vous ne pouvez pas appliquer cette action à votre propre compte.',
            self::ROLE_INCONNU => "Ce rôle n'existe pas.",
        };
    }

    /** Le code HTTP qui convient, pour les surfaces qui répondent en HTTP. */
    public function codeHttp(): int
    {
        return match ($this) {
            self::INTROUVABLE => 404,
            self::PERMISSION, self::HIERARCHIE, self::PROMOTION_TROP_HAUTE => 403,
            self::DERNIER_PROPRIETAIRE, self::SOI_MEME, self::ROLE_INCONNU => 422,
        };
    }
}
