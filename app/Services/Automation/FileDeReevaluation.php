<?php

namespace App\Services\Automation;

use App\Models\AutomationReevaluation;
use Illuminate\Database\QueryException;

/** La SEULE porte de la file. Deux ecouteurs y ecrivent ; la commande y lit. */
class FileDeReevaluation
{
    /** SQLSTATE d'atteinte a l'integrite : rendu par PDO sur MySQL comme sur SQLite. */
    private const DOUBLON = '23000';

    /**
     * L'unicite est tenue par l'index : on tente, et un doublon rend `false` sans lever.
     *
     * On ne rattrape QUE le doublon. Un `catch (QueryException)` nu ferait taire une table
     * absente ou une colonne renommee : la file cesserait de se remplir sans que rien ne le dise.
     */
    public function deposer(string $evenement, string $entiteType, ?int $entiteId): bool
    {
        if ($entiteId === null) {
            return false;
        }

        try {
            AutomationReevaluation::create([
                'evenement' => $evenement,
                'entite_type' => $entiteType,
                'entite_id' => $entiteId,
                'depose_le' => now(),
            ]);
        } catch (QueryException $e) {
            if ((string) $e->getCode() !== self::DOUBLON) {
                throw $e;
            }

            return false;
        }

        return true;
    }

    /** @return array<string, array{entite: string, identifiants: list<int>, lignes: list<int>}> */
    public function parEvenement(): array
    {
        $groupes = [];

        foreach (AutomationReevaluation::query()->orderBy('id')->get() as $ligne) {
            $groupes[$ligne->evenement] ??= [
                'entite' => $ligne->entite_type,
                'identifiants' => [],
                'lignes' => [],
            ];

            $groupes[$ligne->evenement]['identifiants'][] = $ligne->entite_id;
            $groupes[$ligne->evenement]['lignes'][] = $ligne->id;
        }

        return $groupes;
    }

    /** @param list<int> $ids */
    public function purger(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        AutomationReevaluation::whereIn('id', $ids)->delete();
    }
}
