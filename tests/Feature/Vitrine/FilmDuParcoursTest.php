<?php

namespace Tests\Feature\Vitrine;

use App\Support\Vitrine\FilmDuParcours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le film de la home : ses 350 photogrammes, son repli, et ses quatorze légendes.
 *
 * Ce test garde surtout la CONCORDANCE entre le manifeste et les fichiers réellement présents :
 * un `frames.json` qui annonce 350 images alors que le dossier en contient 349 laisse le
 * décodeur attendre une image qui n'arrivera jamais, sans la moindre erreur visible.
 */
class FilmDuParcoursTest extends TestCase
{
    use RefreshDatabase;

    private const RACINE = 'images/journey-film';

    private const PLANS = 14;

    /** @return array{version:int,total:int,parPlan:int,plans:int,tailles:array<string,array{int,int}>,poster:string} */
    private function manifeste(): array
    {
        $chemin = public_path(self::RACINE.'/frames.json');
        $this->assertFileExists($chemin, 'Le manifeste du film est absent : lancez scripts/journey-film/construire-les-frames.sh.');

        /** @var array<string,mixed> $manifeste */
        $manifeste = json_decode((string) file_get_contents($chemin), true, 512, JSON_THROW_ON_ERROR);

        return $manifeste;
    }

    public function test_le_manifeste_compte_exactement_les_frames_presentes(): void
    {
        $manifeste = $this->manifeste();

        $this->assertSame(self::PLANS, $manifeste['plans']);
        $this->assertSame($manifeste['plans'] * $manifeste['parPlan'], $manifeste['total']);

        foreach (array_keys($manifeste['tailles']) as $taille) {
            $dossier = public_path(self::RACINE.'/'.$taille);
            $this->assertDirectoryExists($dossier, "Taille « {$taille} » annoncée mais absente du disque.");

            $presentes = glob($dossier.'/*.avif') ?: [];

            $this->assertCount(
                $manifeste['total'],
                $presentes,
                "Le manifeste annonce {$manifeste['total']} frames en « {$taille} », le disque en porte ".count($presentes).'.',
            );
        }
    }

    public function test_chaque_frame_annoncee_existe_a_son_index(): void
    {
        $manifeste = $this->manifeste();

        // Le decodeur construit ses URL avec un index sur trois chiffres : un trou dans la
        // numerotation le ferait attendre indefiniment, sans erreur.
        foreach (array_keys($manifeste['tailles']) as $taille) {
            $manquantes = [];

            for ($i = 0; $i < $manifeste['total']; $i++) {
                $nom = str_pad((string) $i, 3, '0', STR_PAD_LEFT).'.avif';
                if (! is_file(public_path(self::RACINE.'/'.$taille.'/'.$nom))) {
                    $manquantes[] = $nom;
                }
            }

            $this->assertSame([], $manquantes, "Frames manquantes en « {$taille} » : ".implode(', ', $manquantes));
        }
    }

    public function test_le_manifeste_porte_une_version_qui_change_a_chaque_fabrication(): void
    {
        $version = (string) ($this->manifeste()['version'] ?? '');

        // LE JETON EST CE QUI REND LE FILM REMPLACABLE. Les 700 fichiers gardent leurs noms d'un
        // tournage a l'autre : sans version dans l'URL, le navigateur d'un visiteur qui a deja vu
        // le film lui reservirait l'ancien. Mesure du 2026-09-11 : le film refait en plein jour
        // est reste nocturne apres deux rechargements.
        $this->assertMatchesRegularExpression('/^\d{14}$/', $version,
            'La version doit etre un horodatage de fabrication (AAAAMMJJhhmmss), pas une constante.');

        $this->assertSame($version, FilmDuParcours::version());
    }

    public function test_le_poster_de_la_home_porte_le_jeton_de_version(): void
    {
        $version = FilmDuParcours::version();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('journey-film/poster.jpg?v='.$version, false)
            ->assertSee('journey-film/poster.webp?v='.$version, false);
    }

    public function test_le_poster_existe_dans_les_deux_formats(): void
    {
        // Le poster est le visuel des navigateurs sans AVIF : il ne peut pas etre en AVIF.
        $this->assertFileExists(public_path(self::RACINE.'/poster.webp'));
        $this->assertFileExists(public_path(self::RACINE.'/poster.jpg'));
    }

    public function test_les_quatorze_legendes_existent_dans_les_langues_ecrites(): void
    {
        foreach (['fr', 'nl', 'en'] as $locale) {
            for ($i = 1; $i <= self::PLANS; $i++) {
                foreach (['titre', 'detail'] as $champ) {
                    $cle = "vitrine.film.plans.{$i}.{$champ}";
                    $valeur = trans($cle, [], $locale);

                    $this->assertNotSame($cle, $valeur, "Légende absente : {$cle} en {$locale}.");
                    $this->assertNotSame('', trim((string) $valeur), "Légende vide : {$cle} en {$locale}.");
                }
            }
        }
    }

    public function test_la_home_porte_le_repli_accessible_et_le_poster(): void
    {
        $reponse = $this->get(route('home'))->assertOk();

        // Le <ol> est le contenu accessible de la section : il est dans le HTML servi,
        // AVANT tout JavaScript. Sans lui, la section ne dit rien a un lecteur d'ecran.
        $reponse->assertSee('cx-film__etapes', false);
        $reponse->assertSee(trans('vitrine.film.plans.1.titre'), false);
        $reponse->assertSee(trans('vitrine.film.plans.14.titre'), false);

        $reponse->assertSee('journey-film/poster.jpg', false);
        $reponse->assertSee('data-cx-film', false);
    }
}
