<?php

namespace App\Services\Cerveau;

use App\Models\CodeIncident;
use App\Models\CodeIncidentVictim;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * ENREGISTRER CE QUI CASSE — sans jamais casser à son tour.
 *
 * C'est la règle qui commande tout ici : ce service tourne DANS le gestionnaire d'exceptions.
 * S'il lève lui-même, il remplace l'erreur d'origine par la sienne, et le défaut réel devient
 * invisible. Tout y est donc entouré d'un `try` qui avale — le seul endroit du dépôt où avaler
 * une exception est le comportement juste.
 *
 * L'EMPREINTE GROUPE : classe + fichier + ligne. Le message varie d'une occurrence à l'autre
 * (« utilisateur 42 introuvable ») et grouperait mal.
 */
class JournalDesIncidents
{
    /** Une seule vérification par requête : le chemin est appelé pendant une panne. */
    private static ?bool $tablePresente = null;

    public function enregistrer(Throwable $erreur, ?int $utilisateurId = null): ?CodeIncident
    {
        try {
            if (! $this->tableExiste()) {
                return null;
            }

            $empreinte = $this->empreinte($erreur);
            $famille = app(ClasseurDIncidents::class)->famille($erreur::class, $erreur->getMessage());

            $incident = CodeIncident::query()->where('fingerprint', $empreinte)->first();

            if ($incident === null) {
                $incident = CodeIncident::create([
                    'fingerprint' => $empreinte,
                    'exception_class' => $erreur::class,
                    'message' => mb_substr($erreur->getMessage(), 0, 2000),
                    'file' => mb_substr($erreur->getFile(), 0, 255),
                    'line' => $erreur->getLine(),
                    'route_name' => request()->route()?->getName(),
                    'path' => mb_substr((string) request()->path(), 0, 255),
                    'method' => request()->method(),
                    'famille' => $famille,
                    'occurrences' => 1,
                    'premiere_fois' => now(),
                    'derniere_fois' => now(),
                ]);
            } else {
                // UN INCREMENT ATOMIQUE, pas une lecture puis une écriture : cent requêtes en
                // parallèle sur la même erreur perdraient des occurrences.
                CodeIncident::query()->whereKey($incident->id)->update([
                    'occurrences' => DB::raw('occurrences + 1'),
                    'derniere_fois' => now(),
                    // UNE ERREUR QUI REVIENT APRÈS AVOIR ÉTÉ RÉSOLUE SE ROUVRE : la refermer
                    // d'office ferait disparaître une régression.
                    'statut' => $incident->statut === CodeIncident::RESOLU
                        ? CodeIncident::OUVERT
                        : $incident->statut,
                ]);
            }

            $this->compterLaVictime($incident, $utilisateurId);

            return $incident->refresh();
        } catch (Throwable) {
            // ON AVALE, ET C'EST VOULU : lever ici remplacerait le défaut réel par le nôtre.
            return null;
        }
    }

    /**
     * LES INCIDENTS QUI COMPTENT, les plus vifs en tête.
     *
     * @return Collection<int, CodeIncident>
     */
    public function ouverts(int $combien = 50): Collection
    {
        if (! $this->tableExiste()) {
            return collect();
        }

        return CodeIncident::query()
            ->whereIn('statut', [CodeIncident::OUVERT, CodeIncident::CONTENU])
            ->orderByDesc('derniere_fois')
            ->orderByDesc('occurrences')
            ->limit($combien)
            ->get();
    }

    /**
     * CE QUE LE CERVEAU EN DIT.
     *
     * @return list<Recommandation>
     */
    public function recommandations(): array
    {
        $classeur = app(ClasseurDIncidents::class);
        $recommandations = [];

        foreach ($this->ouverts(10) as $incident) {
            if ($incident->statut === CodeIncident::CONTENU) {
                continue;
            }

            $lecture = $classeur->expliquer($incident);

            $recommandations[] = new Recommandation(
                domaine: 'incident',
                // CE QUI SAIGNE ENCORE PASSE DEVANT. Une erreur d'il y a trois jours, déjà
                // corrigée sans qu'on l'ait refermée ici, n'a pas la même urgence.
                ton: $incident->saigneEncore() && $incident->occurrences >= 5
                    ? Recommandation::TON_DANGER
                    : Recommandation::TON_ATTENTION,
                titre: $lecture['titre'].' — '.$incident->courtCirconstance(),
                constat: $incident->occurrences.' fois, '.$incident->utilisateurs_touches.' personne(s) touchée(s), '
                    .'depuis le '.$incident->premiere_fois->translatedFormat('d/m à H:i').'. '
                    .($incident->route_name !== null ? 'Sur « '.$incident->route_name.' ». ' : '')
                    .$lecture['cause'],
                geste: $lecture['implique'].' — '.$lecture['regarder'],
                gesteApplicable: $classeur->remede($incident) === null ? null : RegistreDesGestes::CONTENIR_INCIDENT,
                arguments: ['id' => $incident->id],
            );
        }

        return $recommandations;
    }

    private function empreinte(Throwable $erreur): string
    {
        return hash('sha256', $erreur::class.'|'.$erreur->getFile().'|'.$erreur->getLine());
    }

    private function compterLaVictime(CodeIncident $incident, ?int $utilisateurId): void
    {
        if ($utilisateurId === null) {
            return;
        }

        $deja = CodeIncidentVictim::query()
            ->where('code_incident_id', $incident->id)
            ->where('user_id', $utilisateurId)
            ->exists();

        if ($deja) {
            return;
        }

        CodeIncidentVictim::create([
            'code_incident_id' => $incident->id,
            'user_id' => $utilisateurId,
        ]);

        CodeIncident::query()->whereKey($incident->id)->update([
            'utilisateurs_touches' => DB::raw('utilisateurs_touches + 1'),
        ]);
    }

    /**
     * LA TABLE PEUT NE PAS EXISTER.
     *
     * Entre la mise en ligne du code et l'exécution de la migration, elle est absente — et c'est
     * précisément le moment où des erreurs surviennent. Le journal doit alors se taire, pas
     * ajouter la sienne.
     */
    private function tableExiste(): bool
    {
        return self::$tablePresente ??= Schema::hasTable('code_incidents');
    }
}
