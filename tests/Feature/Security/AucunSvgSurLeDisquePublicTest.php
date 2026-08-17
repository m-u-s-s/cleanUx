<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Support\Validation\ImagesTeleversees;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * RIEN D'EXÉCUTABLE NE DOIT ATTERRIR SUR LE DISQUE PUBLIC.
 *
 * Le disque `public` est servi tel quel par le serveur web, sur le domaine de l'application. Ce
 * qui y est accepté s'ouvre donc dans NOTRE origine, avec la session de qui regarde — le client,
 * ou l'administrateur qui instruit le dossier d'un prestataire. Un SVG n'est pas une image
 * matricielle : c'est un document XML qui porte `<script>` et `onload=` sans rien perdre de sa
 * validité, et qui s'exécute lorsqu'il est servi en `image/svg+xml`.
 *
 * CE FICHIER GARDE DEUX CHOSES DISTINCTES, et il faut les deux.
 *
 * LE COMPORTEMENT : un SVG posté sur un point d'entrée réel est refusé, et un PNG passe. C'est la
 * seule preuve qui vaille — une liste de formats juste, appliquée nulle part, ne protège personne.
 *
 * LA STRUCTURE : aucun chemin qui écrit sur le disque public ne valide avec un `image` nu. C'est
 * ce que le commentaire de `ImagesTeleversees` annonce déjà — « quand la règle est recopiée à
 * chaque point d'entrée, elle diverge, et c'est toujours la copie la plus permissive qui décide ;
 * un attaquant n'utilise pas le formulaire qu'on a durci, il utilise l'autre ». La règle de
 * Laravel `image` ACCEPTE le svg ; s'y fier rouvrirait la porte sans que personne le remarque.
 *
 * Mesuré au moment de l'écriture : trois chemins écrivent sur ce disque — l'avatar client, la
 * photo d'onboarding prestataire, et les photos du parcours de commande. Les deux premiers passent
 * par la règle partagée ; le troisième porte sa propre liste, sûre mais recopiée. C'est
 * précisément cette divergence-là que le test structurel surveille.
 */
class AucunSvgSurLeDisquePublicTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Les fichiers qui écrivent sur le disque public, relevés dans le code.
     *
     * @return list<array{string}>
     */
    public static function ecrivainsDuDisquePublic(): array
    {
        return [
            ['app/Http/Controllers/Api/Client/ClientProfileController.php'],
            ['app/Services/Onboarding/ProviderOnboardingService.php'],
            ['app/Livewire/OrderEngine/OrderJourney.php'],
        ];
    }

    // ── Le comportement ──────────────────────────────────────────────────

    public function test_un_svg_est_refuse_a_lenvoi(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $charge = <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" onload="alert(document.cookie)"><script>1</script></svg>
            SVG;

        /*
         * LE FICHIER EST NOMME POUR CE QU'IL EST, et c'est une limite du HARNAIS, pas un choix de
         * confort. `UploadedFile::fake()` deduit le type MIME de l'EXTENSION : une charge SVG
         * baptisee `.png` se presente donc comme `image/png` et traverse `mimes:` quoi qu'il
         * arrive. Sur un vrai televersement, `mimes:` compare `guessExtension()`, derivee de
         * `finfo` SUR LE CONTENU -- renommer ne suffit pas.
         *
         * Ce que ce test prouve est donc exactement ce qui compte ici : un SVG correctement
         * identifie est REFUSE. La regle de Laravel `image`, elle, l'accepterait.
         */
        $this->postJson('/api/client/profile/avatar', [
            'avatar' => UploadedFile::fake()->createWithContent('attaque.svg', $charge),
        ])->assertStatus(422);
    }

    /**
     * TÉMOIN — une vraie image passe toujours.
     *
     * Sans lui, le test précédent serait vert sur un point d'entrée qui refuse TOUT, et on aurait
     * remplacé une faille par une panne.
     */
    public function test_temoin_une_image_matricielle_est_acceptee(): void
    {
        Sanctum::actingAs(User::factory()->create());

        // Un PNG minimal valide : `mimes:` compare le type devine par `finfo` SUR LE CONTENU, pas
        // l'extension annoncee. Un fichier vide renomme .png serait refuse, et le temoin ne
        // mesurerait plus rien.
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );

        $this->postJson('/api/client/profile/avatar', [
            'avatar' => UploadedFile::fake()->createWithContent('photo.png', $png),
        ])->assertSuccessful();
    }

    // ── La structure ─────────────────────────────────────────────────────

    #[DataProvider('ecrivainsDuDisquePublic')]
    public function test_aucun_ecrivain_public_ne_valide_avec_un_image_nu(string $chemin): void
    {
        $source = (string) file_get_contents(base_path($chemin));

        // Garde-fou du test : si le fichier a été déplacé, on ne mesure plus rien.
        $this->assertNotSame('', $source, "{$chemin} est vide ou introuvable.");

        /*
         * On cherche `'image'` employé SEUL comme règle. `ImagesTeleversees` et une liste
         * `mimes:` explicite sont les deux formes acceptables ; `'image'` tout court laisse passer
         * le svg, puisque c'est ce que fait la règle de Laravel.
         */
        $utiliseLaRegleCommune = str_contains($source, 'ImagesTeleversees');
        $utiliseUneListeExplicite = str_contains($source, 'mimes:');
        $imageNu = preg_match("/'image'\s*,/", $source) === 1;

        if (! $imageNu) {
            $this->assertTrue(true, 'Aucune règle `image` nue.');

            return;
        }

        $this->assertTrue(
            $utiliseLaRegleCommune || $utiliseUneListeExplicite,
            "{$chemin} valide un téléversement avec `image` sans restreindre les formats : la règle "
            .'de Laravel accepte le SVG, et ce fichier écrit sur le disque public.',
        );
    }

    /**
     * LA RÈGLE PARTAGÉE EXCLUT LE SVG — et le dire ici évite qu'on l'y remette « pour dépanner ».
     */
    public function test_la_regle_partagee_exclut_le_svg(): void
    {
        $regles = implode(' ', ImagesTeleversees::regles(tailleMaxKo: 5120));

        $this->assertStringNotContainsString('svg', $regles);
        // Témoin : la règle liste bien des formats, elle n'est pas vide.
        $this->assertStringContainsString('png', $regles);
    }
}
