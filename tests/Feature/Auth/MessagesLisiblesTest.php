<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * PERSONNE NE DOIT LIRE « validation.unique » NI « auth.failed ».
 *
 * Mesuré le 2026-08-16, dans un navigateur et dans l'application : aucun dossier de `lang` ne
 * contenait `validation.php` ni `auth.php`, alors que `locale` et `fallback_locale` valent tous deux
 * `fr`. Laravel rendait donc la clé elle-même. Ce que voyaient les utilisateurs :
 *
 *   • connexion web, mauvais mot de passe   → « Whoops! Something went wrong. » puis « auth.failed »
 *   • inscription web, e-mail déjà pris     → « validation.unique », « validation.confirmed »
 *   • application mobile                    → {"message":"validation.required (and 4 more errors)"}
 *
 * CE FICHIER TESTE DEUX CHOSES DIFFÉRENTES, et les deux comptent : ce que rend l'écran (un vrai
 * POST, pas une lecture de fichier), et la COMPLÉTUDE du catalogue — sans quoi la prochaine règle de
 * validation employée dans le projet ressortirait en clé nue sans que rien ne le signale.
 */
class MessagesLisiblesTest extends TestCase
{
    use RefreshDatabase;

    // ─── Ce que rend réellement l'écran ──────────────────────────────────────────────────────

    public function test_la_connexion_web_explique_le_refus_en_francais(): void
    {
        $user = User::factory()->create(['password' => bcrypt('MotDePasse1!')]);

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'mauvais-mot-de-passe',
        ]);

        $page = $this->followingRedirects()->get('/login')->getContent();

        $this->assertStringNotContainsString('auth.failed', $page);
        $this->assertStringNotContainsString('Whoops', $page);
        $this->assertStringContainsString('Ces identifiants ne correspondent à aucun compte.', $page);
    }

    public function test_l_inscription_web_nomme_chaque_champ_fautif(): void
    {
        User::factory()->create(['email' => 'deja.pris@test.local']);

        $this->from('/register')->post('/register', [
            'name' => 'QA Doublon',
            'email' => 'deja.pris@test.local',
            'password' => 'MotDePasse1!',
            'password_confirmation' => 'AutreMdp2!',
            'account_type' => 'client_personal',
        ]);

        $page = $this->followingRedirects()->get('/register')->getContent();

        // Les trois fuites relevees ensemble : en corriger une puis relancer pour decouvrir la
        // suivante coute trois executions pour un seul ecran.
        $fuites = array_values(array_filter(
            ['validation.unique', 'validation.confirmed', 'Whoops'],
            fn (string $interdit) => str_contains($page, $interdit),
        ));

        $this->assertSame([], $fuites, 'Ces cles techniques restent affichees a l utilisateur.');

        // Le message par champ, plus utile que la phrase générique du framework.
        $this->assertStringContainsString('Un compte existe déjà avec cette adresse e-mail.', $page);
        $this->assertStringContainsString('confirmation', $page);
    }

    public function test_l_inscription_mobile_renvoie_des_phrases(): void
    {
        User::factory()->create(['email' => 'deja.pris@test.local']);

        $reponse = $this->postJson('/api/auth/register', [
            'name' => '',
            'email' => 'deja.pris@test.local',
            'password' => 'court',
            'password_confirmation' => 'autre',
        ])->assertStatus(422);

        $corps = $reponse->getContent();

        $this->assertStringNotContainsString(
            'validation.',
            $corps,
            "L'application affiche `firstFieldError` tel quel : une clé nue arrive à l'écran."
        );

        $reponse->assertJsonPath('errors.name.0', 'Le champ nom est obligatoire.');
    }

    public function test_le_message_de_trop_de_tentatives_est_lisible(): void
    {
        $this->assertNotSame(
            'auth.throttle',
            trans('auth.throttle', ['seconds' => 42]),
            'La phrase de blocage temporaire manque.'
        );
        $this->assertStringContainsString('42', trans('auth.throttle', ['seconds' => 42]));
    }

    // ─── La complétude du catalogue ──────────────────────────────────────────────────────────

    /**
     * TOUTES les règles de validation du framework doivent être traduites, pas seulement celles
     * qu'un formulaire déclenche aujourd'hui. Une règle ajoutée demain ressortirait sinon en clé
     * nue, et c'est exactement ainsi que ce défaut a vécu : `min:8` et `unique` n'avaient jamais
     * été traduits parce que personne n'avait regardé le formulaire après un refus.
     *
     * @dataProvider languesServies
     */
    public function test_chaque_regle_du_framework_a_sa_phrase(string $locale): void
    {
        app()->setLocale($locale);

        $referenceEn = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');

        // `custom` et `attributes` ne sont pas des messages : ce sont des tables de correspondance.
        $reference = Arr::except($referenceEn, ['custom', 'attributes']);

        $manquantes = [];

        foreach (Arr::dot($reference) as $cle => $_) {
            if (Lang::get('validation.'.$cle) === 'validation.'.$cle) {
                $manquantes[] = $cle;
            }
        }

        $this->assertSame([], $manquantes, "Règles sans traduction en « {$locale} » : ".implode(', ', $manquantes));
    }

    /**
     * @dataProvider languesServies
     */
    public function test_les_trois_fichiers_d_authentification_existent(string $locale): void
    {
        app()->setLocale($locale);

        // Les huit cles relevees ensemble : un fichier de langue absent les laisse TOUTES
        // intraduites, et une assertion par tour n'en nommerait qu'une.
        $intraduites = array_values(array_filter([
            'auth.failed',
            'auth.password',
            'auth.throttle',
            'passwords.reset',
            'passwords.sent',
            'passwords.throttled',
            'passwords.token',
            'passwords.user',
        ], fn (string $cle) => Lang::get($cle) === $cle));

        $this->assertSame([], $intraduites, "Ces cles ne sont pas traduites en « {$locale} » : l utilisateur verra la cle brute.");
    }

    /**
     * Les langues NON traduites doivent retomber sur le français, jamais sur une clé nue.
     *
     * `fallback_locale = fr` s'en charge — mais il faut que ce soit vérifié, sinon un nl_BE verrait
     * « validation.required » là où un fr_BE lit une phrase, et rien dans les tests ne le dirait.
     */
    public function test_une_langue_non_traduite_retombe_sur_le_francais(): void
    {
        app()->setLocale('nl');

        $this->assertSame('Le champ nom est obligatoire.', Lang::get('validation.required', ['attribute' => 'nom']));
        $this->assertNotSame('auth.failed', Lang::get('auth.failed'));
    }

    /** @return array<string, array{0: string}> */
    public static function languesServies(): array
    {
        return [
            'français' => ['fr'],
            'anglais' => ['en'],
        ];
    }
}
