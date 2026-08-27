<?php

namespace App\Support\Disputes;

use App\Support\Validation\ImagesTeleversees;
use Illuminate\Http\UploadedFile;

/**
 * LES PREUVES D'UN LITIGE — un seul endroit qui décide de tout.
 *
 * `DisputeService` acceptait des pièces jointes depuis toujours, à l'ouverture comme sur chaque
 * message : `array $attachments = []`. Aucun des six appelants ne les fournissait, et aucun écran
 * ne les affichait. Les colonnes existaient, castées, avec même un type d'événement
 * `attachment_added` — et rien ne pouvait les remplir.
 *
 * DISQUE PRIVÉ, JAMAIS PUBLIC : une preuve de litige montre un logement, un dégât, parfois une
 * personne. Elle ne se sert que par une URL signée et à un compte authentifié.
 */
final class PreuvesDeLitige
{
    public const DISQUE = 'private';

    public const DOSSIER = 'disputes';

    public const NOMBRE_MAX = 5;

    public const TAILLE_MAX_KO = 5120;

    /**
     * La forme est celle que le module v1 emploie déjà : les deux affichages se rejoignent.
     *
     * @param  list<UploadedFile|mixed>  $fichiers
     * @return list<array{path: string, original_name: string}>
     */
    public static function stocker(array $fichiers): array
    {
        $stockees = [];

        foreach ($fichiers as $fichier) {
            // ON NE TRONQUE PAS ICI : c'est la validation qui plafonne, et une coupe silencieuse
            // cacherait un appelant qui a oublié ses règles.
            if (! $fichier instanceof UploadedFile) {
                continue;
            }

            $stockees[] = [
                'path' => (string) $fichier->store(self::DOSSIER, self::DISQUE),
                'original_name' => $fichier->getClientOriginalName(),
            ];
        }

        return $stockees;
    }

    /**
     * Les règles, écrites une fois pour les six points d'entrée.
     *
     * @return array<string, list<string>>
     */
    public static function regles(string $champ): array
    {
        return [
            $champ => ['nullable', 'array', 'max:'.self::NOMBRE_MAX],
            $champ.'.*' => ImagesTeleversees::regles(self::TAILLE_MAX_KO, obligatoire: false),
        ];
    }
}
