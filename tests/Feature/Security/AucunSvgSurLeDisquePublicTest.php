<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Support\Validation\ImagesTeleversees;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** RIEN D'EXÉCUTABLE NE DOIT ATTERRIR SUR LE DISQUE PUBLIC. */
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

        // LE FICHIER EST NOMME POUR CE QU'IL EST, et c'est une limite du HARNAIS, pas un choix de confort.
        $this->postJson('/api/client/profile/avatar', [
            'avatar' => UploadedFile::fake()->createWithContent('attaque.svg', $charge),
        ])->assertStatus(422);
    }

    /** TÉMOIN — une vraie image passe toujours. */
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

        // On cherche `'image'` employé SEUL comme règle.
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

    /** LE HEIC DES IPHONE PASSE — et il ne passait nulle part avant. */
    public function test_le_heic_des_iphone_est_accepte(): void
    {
        Sanctum::actingAs(User::factory()->create());

        // En-tete ISOBMFF de 24 octets, marque `heic` : c'est ce que `finfo` lit.
        $heic = (string) base64_decode('AAAAGGZ0eXBoZWljAAAAAGhlaWNtaWYx', true);

        $this->postJson('/api/client/profile/avatar', [
            'avatar' => UploadedFile::fake()->createWithContent('photo.heic', $heic),
        ])->assertSuccessful();
    }

    /** LA MARQUE GÉNÉRIQUE PASSE AUSSI, et c'est une entrée distincte de la précédente. */
    public function test_le_heif_a_marque_generique_est_accepte_lui_aussi(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $heif = (string) base64_decode('AAAAGGZ0eXBtaWYxAAAAAG1pZjFoZWlj', true);

        $this->postJson('/api/client/profile/avatar', [
            'avatar' => UploadedFile::fake()->createWithContent('photo.heif', $heif),
        ])->assertSuccessful();
    }

    /** LA RÈGLE PARTAGÉE EXCLUT LE SVG — et le dire ici évite qu'on l'y remette « pour dépanner ». */
    public function test_la_regle_partagee_exclut_le_svg(): void
    {
        $regles = implode(' ', ImagesTeleversees::regles(tailleMaxKo: 5120));

        $this->assertStringNotContainsString('svg', $regles);

        // Témoin : la règle liste bien des formats, elle n'est pas vide.
        $this->assertStringContainsString('png', $regles);
    }
}
