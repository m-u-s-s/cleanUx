<?php

namespace Tests\Feature\Architecture;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * MYSQL REFUSE UN IDENTIFIANT DE PLUS DE 64 CARACTÈRES. SQLITE S'EN MOQUE.
 *
 * POURQUOI CE FICHIER EXISTE. La migration `create_signing_appointments_table` déclarait
 * `$table->index(['organization_account_id', 'status', 'scheduled_at'])`. Laravel en dérive
 * `signing_appointments_organization_account_id_status_scheduled_at_index` — 70 caractères.
 *
 * La suite tournant sur SQLite, qui n'impose aucune limite de longueur, tout était vert en local :
 * 5478 tests, PHPStan, Pint. L'échec n'est apparu qu'à l'étape « Migrate (MySQL) » de la CI, sur
 * une base neuve. Une migration qui ne s'applique pas en production ne se voit nulle part ailleurs.
 *
 * Cette garde lit les migrations et recalcule le nom que Laravel produira, pour que la limite soit
 * franchie ici plutôt qu'au déploiement.
 *
 * LIMITES ASSUMÉES. Elle couvre les formes littérales `index([...])` / `unique([...])` sans nom
 * explicite, dans un bloc `Schema::create()` ou `Schema::table()` identifiable. Les index nommés à
 * la main sont ignorés — c'est précisément la façon de sortir du piège.
 */
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
