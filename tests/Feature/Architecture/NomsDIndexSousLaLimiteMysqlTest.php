<?php

namespace Tests\Feature\Architecture;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** MYSQL REFUSE UN IDENTIFIANT DE PLUS DE 64 CARACTÈRES. SQLITE S'EN MOQUE. */
class NomsDIndexSousLaLimiteMysqlTest extends TestCase
{
    /** MySQL : 64 caractères pour tout identifiant (table, colonne, index, contrainte). */
    private const LIMITE_MYSQL = 64;

    #[Test]
    public function aucun_nom_d_index_genere_ne_depasse_la_limite_mysql(): void
    {
        $fautes = [];
        $verifies = 0;

        foreach (glob(database_path('migrations').'/*.php') ?: [] as $fichier) {
            $lignes = file($fichier) ?: [];
            $tableCourante = null;

            foreach ($lignes as $numero => $ligne) {
                if (preg_match('/Schema::(?:create|table)\(\s*[\'"]([a-z0-9_]+)[\'"]/i', $ligne, $m) === 1) {
                    $tableCourante = $m[1];
                }

                if ($tableCourante === null) {
                    continue;
                }

                // `->index([...])` ou `->unique([...])`, tableau littéral, sans second argument.
                if (preg_match('/->(index|unique)\(\s*\[([^\]]*)\]\s*\)/', $ligne, $m) !== 1) {
                    continue;
                }

                $type = $m[1];
                preg_match_all('/[\'"]([a-z0-9_]+)[\'"]/i', $m[2], $colonnes);

                if ($colonnes[1] === []) {
                    continue;
                }

                $verifies++;
                $genere = $tableCourante.'_'.implode('_', $colonnes[1]).'_'.$type;

                if (strlen($genere) > self::LIMITE_MYSQL) {
                    $fautes[] = sprintf(
                        '%s:%d — « %s » fait %d caractères (limite %d). Nommez l\'index explicitement.',
                        basename($fichier),
                        $numero + 1,
                        $genere,
                        strlen($genere),
                        self::LIMITE_MYSQL
                    );
                }
            }
        }

        $this->assertGreaterThan(
            20,
            $verifies,
            'La garde ne trouve presque rien à vérifier : son expression de recherche ne mord plus.'
        );

        $this->assertSame(
            [],
            $fautes,
            "Des index dépasseront la limite MySQL et feront échouer la migration en production :\n"
                .implode("\n", $fautes)
        );
    }
}
