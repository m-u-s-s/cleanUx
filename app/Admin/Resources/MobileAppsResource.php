<?php

namespace App\Admin\Resources;

use App\Admin\Console\Action;
use App\Admin\Console\Column;
use App\Admin\Console\EloquentResource;
use App\Admin\Console\Field;
use App\Models\Parametre;
use App\Support\ActivityLogger;
use App\Support\Apps\LiensApplications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Les liens de téléchargement des applications, côté console mobile.
 *
 * Le même geste que le web, sur les mêmes lignes de `parametres` : un lien corrigé depuis un
 * téléphone doit s'afficher sur la page d'accueil aussi sûrement que depuis un bureau.
 *
 * @extends EloquentResource<Parametre>
 */
class MobileAppsResource extends EloquentResource
{
    public function key(): string
    {
        return 'applications-mobiles';
    }

    protected function model(): string
    {
        return Parametre::class;
    }

    /** Seules les clefs du module : `parametres` porte aussi des réglages sans rapport. */
    public function query(): Builder
    {
        return parent::query()->whereIn('cle', LiensApplications::toutesLesCles());
    }

    protected function columnSpec(): array
    {
        return [
            'cle' => ['Réglage', Column::TYPE_BADGE],
            'valeur' => ['Valeur publiée', Column::TYPE_TEXT],
            'updated_at' => ['Modifiée le', Column::TYPE_DATETIME],
        ];
    }

    protected function searchable(): array
    {
        return ['cle', 'valeur'];
    }

    protected function searchLabel(): string
    {
        return 'Réglage ou valeur';
    }

    /**
     * UN LIEN SE VIDE, IL NE SE SUPPRIME PAS.
     *
     * Vider la valeur masque proprement le bouton ; effacer la LIGNE ferait retomber la clef sur
     * son défaut — et `apps_client_visible` vaut « 1 » par défaut, donc supprimer rallumerait une
     * carte que l'admin venait d'éteindre.
     */
    public function reasonsToRefuseDelete(Model $model): array
    {
        return ['Un lien se vide depuis /admin/applications-mobiles ; supprimer la ligne le remettrait a son defaut.'];
    }

    public function actions(): array
    {
        return [
            Action::make('modifier', 'Corriger le réglage', function (Parametre $parametre, array $valeurs) {
                $cle = (string) $parametre->cle;
                $valeur = trim((string) ($valeurs['valeur'] ?? ''));

                // Meme garde que l'ecran web : seul `https` publie, le vide masque le bouton.
                $estInterrupteur = in_array($cle, [
                    LiensApplications::cleVisibilite(LiensApplications::CLIENT),
                    LiensApplications::cleVisibilite(LiensApplications::PRESTATAIRE),
                    LiensApplications::cleQr(),
                ], true);

                if ($estInterrupteur) {
                    $valeur = in_array(strtolower($valeur), ['1', 'oui', 'true', 'on'], true) ? '1' : '0';
                } elseif ($valeur !== '' && ! LiensApplications::estPubliable($valeur)) {
                    return ['ok' => false, 'message' => 'Un lien doit commencer par https:// ou rester vide.'];
                }

                Parametre::setValeur($cle, $valeur);

                ActivityLogger::log('platform.mobile_apps_updated', $parametre, [
                    'domain' => 'plateforme',
                    'cle' => $cle,
                    'source' => 'console-mobile',
                ]);

                return ['ok' => true];
            })->requires([
                Field::make('valeur', 'Nouvelle valeur', Field::TYPE_TEXT)
                    ->rules(['nullable', 'string', 'max:500']),
            ]),
        ];
    }
}
