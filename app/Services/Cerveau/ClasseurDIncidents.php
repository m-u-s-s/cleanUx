<?php

namespace App\Services\Cerveau;

use App\Models\CodeIncident;

/**
 * CE QUE L'ERREUR VEUT DIRE, EN FRANÇAIS.
 *
 * Un outil générique dit « QueryException : no such column ». Il a raison et il ne sert à rien :
 * il ne dit ni ce qui casse, ni pour qui, ni quoi faire. Ce classeur-ci connaît les FAMILLES DE
 * DÉFAUTS PROPRES À CE DÉPÔT — celles qui reviennent, celles qui ont déjà coûté des jours.
 *
 * PAS D'INTELLIGENCE ARTIFICIELLE : une table de correspondances, relisible, qu'on complète
 * quand une nouvelle famille apparaît. Une explication qu'on peut vérifier vaut mieux qu'une
 * explication plausible.
 */
class ClasseurDIncidents
{
    public const FAMILLE_SCHEMA = 'schema';

    public const FAMILLE_DONNEE_ABSENTE = 'donnee_absente';

    public const FAMILLE_ACCES = 'acces';

    public const FAMILLE_NUL = 'valeur_nulle';

    public const FAMILLE_VUE = 'vue';

    public const FAMILLE_TIERS = 'service_tiers';

    public const FAMILLE_INCONNUE = 'inconnue';

    /** LA FAMILLE, depuis la classe d'exception et le message. */
    public function famille(string $classe, string $message): string
    {
        $court = class_basename($classe);
        $bas = mb_strtolower($message);

        return match (true) {
            // LE SCHÉMA A DÉRIVÉ : le code parle d'une colonne ou d'une table que la base n'a pas.
            // C'est la famille la plus coûteuse ici — un déploiement sans migration la produit.
            str_contains($bas, 'no such column')
                || str_contains($bas, 'no such table')
                || str_contains($bas, 'unknown column')
                || str_contains($bas, "doesn't exist") => self::FAMILLE_SCHEMA,

            $court === 'ModelNotFoundException'
                || str_contains($bas, 'no query results') => self::FAMILLE_DONNEE_ABSENTE,

            in_array($court, ['AuthorizationException', 'AccessDeniedHttpException'], true)
                || str_contains($bas, 'this action is unauthorized') => self::FAMILLE_ACCES,

            $court === 'TypeError' && str_contains($bas, 'null') => self::FAMILLE_NUL,
            str_contains($bas, 'call to a member function') && str_contains($bas, 'on null') => self::FAMILLE_NUL,

            $court === 'ViewException' || str_contains($bas, 'blade') => self::FAMILLE_VUE,

            in_array($court, ['ConnectionException', 'RequestException', 'ApiErrorException'], true)
                || str_contains($bas, 'curl error') => self::FAMILLE_TIERS,

            default => self::FAMILLE_INCONNUE,
        };
    }

    /**
     * L'EXPLICATION — ce qui s'est passé, ce que ça implique, ce qu'il faut regarder.
     *
     * @return array{titre: string, cause: string, implique: string, regarder: string}
     */
    public function expliquer(CodeIncident $incident): array
    {
        return match ($incident->famille) {
            self::FAMILLE_SCHEMA => [
                'titre' => 'Le code parle d’une colonne que la base n’a pas',
                'cause' => 'Presque toujours un déploiement dont la migration n’a pas été jouée — le code '
                    .'neuf tourne sur l’ancienne base. Plus rarement, une colonne supprimée dont un '
                    .'appelant a été oublié.',
                'implique' => 'CETTE PAGE EST MORTE, pas ralentie : chaque visite lève la même erreur. Si '
                    .'elle touche un chemin d’argent, aucun devis ne se calcule.',
                'regarder' => 'Comparez la migration la plus récente avec ce que la base porte vraiment '
                    .'(`php artisan migrate:status`). Si la migration est en attente, la jouer suffit.',
            ],

            self::FAMILLE_DONNEE_ABSENTE => [
                'titre' => 'Un enregistrement demandé n’existe pas',
                'cause' => 'Un identifiant dans l’URL qui ne correspond à rien : supprimé entre-temps, ou '
                    .'appartenant à quelqu’un d’autre. C’est parfois un lien périmé dans un e-mail.',
                'implique' => 'La personne voit une erreur au lieu d’un message. Rien n’est cassé côté '
                    .'données — c’est un défaut d’accueil, pas de moteur.',
                'regarder' => 'Si les occurrences viennent d’un seul compte, c’est un lien périmé. Si elles '
                    .'viennent de partout, quelque chose supprime des enregistrements encore référencés.',
            ],

            self::FAMILLE_ACCES => [
                'titre' => 'Une garde refuse le passage',
                'cause' => 'Souvent NORMAL : c’est une garde qui fait son travail. Anormal si la personne '
                    .'devrait avoir le droit — une capacité retirée par erreur, ou une garde posée sur le '
                    .'web et pas sur l’API.',
                'implique' => 'Personne ne perd de données. Mais si c’est un faux refus, quelqu’un ne peut '
                    .'plus travailler et ne comprend pas pourquoi.',
                'regarder' => 'Ouvrez `/admin/roles-et-permissions` sur le compte concerné : voyez ce qu’il '
                    .'détient, et ce que l’écran exige.',
            ],

            self::FAMILLE_NUL => [
                'titre' => 'Le code lit quelque chose qui n’est pas là',
                'cause' => 'Une relation vide, ou une colonne facultative traitée comme obligatoire. Le cas '
                    .'classique de ce dépôt : un écran partagé entre deux notions qui n’en connaît qu’une.',
                'implique' => 'La page plante entièrement — ce n’est pas un détail manquant, c’est un écran '
                    .'blanc.',
                'regarder' => 'Le fichier et la ligne donnent la lecture fautive. La question est de savoir '
                    .'quel CAS produit ce vide, pas comment le contourner : `?->` masquerait le symptôme.',
            ],

            self::FAMILLE_VUE => [
                'titre' => 'Un gabarit refuse de se compiler',
                'cause' => 'Une directive Blade mal fermée. Le piège maison : `@php(...)` en ligne et un '
                    .'bloc `@php … @endphp` dans le même fichier — le bloc ferme la forme en ligne.',
                'implique' => 'La page ne s’affiche pas du tout, et le cache de vues peut garder l’erreur '
                    .'après la correction.',
                'regarder' => 'Le fichier est nommé dans le message. Après correction, `php artisan '
                    .'view:clear` — sinon on croit que le correctif n’a rien fait.',
            ],

            self::FAMILLE_TIERS => [
                'titre' => 'Un service extérieur n’a pas répondu',
                'cause' => 'La banque, la carte, l’envoi d’e-mails ou la géolocalisation. Souvent passager.',
                'implique' => 'Le danger n’est pas la panne, c’est la SUITE : si le code a écrit avant '
                    .'l’appel et pas après, l’enregistrement reste à moitié fait.',
                'regarder' => 'Vérifiez si l’écriture locale est dans une transaction avec l’appel. Si les '
                    .'occurrences se concentrent sur une heure, c’était une panne du fournisseur.',
            ],

            default => [
                'titre' => 'Une erreur que le classeur ne reconnaît pas',
                'cause' => 'Aucune famille connue ne correspond. Ce n’est pas grave en soi : cela veut dire '
                    .'qu’elle est nouvelle ici.',
                'implique' => 'Impossible à dire sans regarder. Le nombre d’occurrences et de personnes '
                    .'touchées donne l’urgence.',
                'regarder' => 'Le fichier et la ligne. Si le motif revient, il mérite sa propre famille '
                    .'dans ce classeur.',
            ],
        };
    }

    /**
     * LE REMÈDE PROPOSÉ, quand il en existe un.
     *
     * AUCUN REMÈDE NE TOUCHE AU CODE. Un serveur qui réécrit son propre PHP en production est à
     * la fois une faille et une panne en puissance. Ce qu'on propose est OPÉRATIONNEL et
     * réversible : contenir le saignement pendant qu'un humain corrige.
     */
    public function remede(CodeIncident $incident): ?string
    {
        return match ($incident->famille) {
            self::FAMILLE_SCHEMA, self::FAMILLE_NUL, self::FAMILLE_VUE => $incident->saigneEncore()
                ? 'Marquer contenu — le temps qu’un correctif parte, et pour ne plus le voir remonter en tête.'
                : null,
            default => null,
        };
    }
}
