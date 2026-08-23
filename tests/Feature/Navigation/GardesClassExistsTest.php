<?php

namespace Tests\Feature\Navigation;

use Tests\TestCase;

/** UNE ROUTE NE DOIT PAS POUVOIR DISPARAÎTRE EN SILENCE. */
class GardesClassExistsTest extends TestCase
{
    public function test_chaque_garde_class_exists_designe_une_classe_qui_existe(): void
    {
        $absentes = [];
        $total = 0;

        foreach (glob(base_path('routes').'/*.php') as $fichier) {
            $code = (string) file_get_contents($fichier);

            // `use A\B\C;` ET `use A\B\C as Alias;` — sans le second, un alias
            // passerait pour une classe absente.
            preg_match_all(
                '/^use\\s+([A-Za-z0-9_\\\\]+)(?:\s+as\s+([A-Za-z0-9_]+))?;/m',
                $code,
                $imports,
                PREG_SET_ORDER
            );

            $connues = [];
            foreach ($imports as $ligne) {
                $fqcn = $ligne[1];
                if (! empty($ligne[2])) {
                    $connues[$ligne[2]] = $fqcn;

                    continue;
                }
                $position = strrpos($fqcn, '\\');
                $connues[$position === false ? $fqcn : substr($fqcn, $position + 1)] = $fqcn;
            }

            preg_match_all('/class_exists\(([A-Za-z0-9_\\\\]+)::class\)/', $code, $gardes);

            foreach ($gardes[1] as $court) {
                $total++;
                $fqcn = $connues[$court] ?? $court;

                if (! class_exists($fqcn)) {
                    $absentes[] = basename($fichier).' → '.$fqcn;
                }
            }
        }

        // LE CONTRÔLE DOIT TROUVER DE QUOI CONTRÔLER.
        $this->assertGreaterThan(100, $total, 'Le contrôle ne lit plus les gardes : motif à revoir');
        $this->assertSame([], $absentes, sprintf(
            '%d garde(s) class_exists désignent une classe absente : la route correspondante '.
            "n'est plus déclarée, sans que rien ne le signale.\n%s",
            count($absentes),
            implode("\n", $absentes)
        ));
    }
}
