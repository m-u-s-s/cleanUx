<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * Une annotation `@return HasMany<X>` doit nommer la classe que la relation renvoie VRAIMENT.
 *
 * PHPStan croit l'annotation sur parole : une annotation fausse ne le fait pas echouer, elle
 * l'AVEUGLE sur tout ce que la relation traverse. Vingt-huit divergeaient dans treize modeles,
 * dont six de `Mission` qui annoncaient toutes `MissionTaskSegment` pour six relations distinctes.
 */
class UneAnnotationDeRelationDitVraiTest extends TestCase
{
    private const RELATIONS = 'HasMany|HasOne|BelongsTo|BelongsToMany|HasManyThrough|MorphMany|MorphOne';

    /**
     * Les divergences d'un morceau de code source : ligne => [annonce, renvoye].
     *
     * @return array<int, array{string, string}>
     */
    private function divergences(string $code): array
    {
        $lignes = preg_split('/\R/', $code) ?: [];
        $trouvees = [];

        foreach ($lignes as $i => $ligne) {
            if (preg_match('/@return (?:'.self::RELATIONS.')<([A-Za-z]+),/', $ligne, $annonce) !== 1) {
                continue;
            }

            // La relation est la premiere trouvee sous l'annotation : corps de methode compris.
            for ($j = $i + 1; $j < min($i + 6, count($lignes)); $j++) {
                $motif = '/->(?:hasMany|hasOne|belongsTo|belongsToMany|hasManyThrough|morphMany|morphOne)\(([A-Za-z]+)::class/';

                if (preg_match($motif, $lignes[$j], $renvoi) !== 1) {
                    continue;
                }

                if ($renvoi[1] !== $annonce[1]) {
                    $trouvees[$i + 1] = [$annonce[1], $renvoi[1]];
                }

                break;
            }
        }

        return $trouvees;
    }

    /** TEMOIN — le controle reconnait une divergence et epargne une annotation juste. */
    public function test_temoin_le_controle_repere_une_annotation_fausse(): void
    {
        $faux = <<<'CODE'
            /** @return HasMany<Feedback, $this> */
            public function missions(): HasMany
            {
                return $this->hasMany(Mission::class);
            }
            CODE;

        $juste = str_replace('HasMany<Feedback,', 'HasMany<Mission,', $faux);

        $this->assertSame([1 => ['Feedback', 'Mission']], $this->divergences($faux));
        $this->assertSame([], $this->divergences($juste), 'Une annotation juste est signalee a tort.');
    }

    public function test_aucune_annotation_de_relation_ne_diverge(): void
    {
        $racine = str_replace(chr(92), '/', app_path());
        $fautives = [];
        $vus = 0;

        $iterateur = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));

        foreach ($iterateur as $fichier) {
            if (! $fichier->isFile() || ! str_ends_with($fichier->getFilename(), '.php')) {
                continue;
            }

            $vus++;
            $chemin = ltrim(str_replace($racine, '', str_replace(chr(92), '/', $fichier->getPathname())), '/');

            foreach ($this->divergences((string) file_get_contents($fichier->getPathname())) as $ligne => [$dit, $rend]) {
                $fautives[] = "{$chemin}:{$ligne} annonce {$dit}, renvoie {$rend}";
            }
        }

        $this->assertGreaterThan(500, $vus, 'Le balayage ne voit presque aucun fichier.');
        $this->assertSame([], $fautives, 'PHPStan croit ces annotations sur parole.');
    }
}
