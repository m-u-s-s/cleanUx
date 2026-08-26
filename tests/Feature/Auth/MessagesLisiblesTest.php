<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/** PERSONNE NE DOIT LIRE « validation.unique » NI « auth.failed ». */
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
     * TOUTES les règles de validation du framework doivent être traduites, pas seulement celles qu'un formulaire déclenche aujourd'hui.
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
     * UNE LANGUE SANS FICHIERS RETOMBE SUR LE REPLI, JAMAIS SUR UNE CLE NUE.
     *
     * Ce test visait `nl`, qui n'avait alors aucun de ces fichiers. Il en a desormais, comme
     * l'allemand, l'espagnol et l'italien : l'exemple etait perime, l'invariant ne l'est pas.
     *
     * `pt` le porte maintenant. Il est declare dans `config/i18n.php` mais ETEINT, et n'a aucun
     * repertoire de langue — exactement le cas que cet invariant protege.
     */
    public function test_une_langue_sans_fichiers_retombe_sur_le_repli(): void
    {
        app()->setLocale('pt');

        $this->assertSame('Le champ nom est obligatoire.', Lang::get('validation.required', ['attribute' => 'nom']));
        $this->assertNotSame('auth.failed', Lang::get('auth.failed'));

        // TEMOIN : une langue qui a ses fichiers rend SA phrase, pas celle du repli.
        app()->setLocale('nl');

        $this->assertSame('Het naam veld is verplicht.', Lang::get('validation.required', ['attribute' => 'naam']));
    }

    /**
     * LES SIX LANGUES ACTIVES, PAS DEUX.
     *
     * Ce fournisseur n'en portait que deux, si bien que les deux tests ci-dessus ne disaient rien
     * du néerlandais, de l'allemand, de l'espagnol ni de l'italien — les quatre langues qui
     * n'avaient justement aucun de ces fichiers.
     *
     * @return array<string, array{0: string}>
     */
    public static function languesServies(): array
    {
        return [
            'français' => ['fr'],
            'anglais' => ['en'],
            'néerlandais' => ['nl'],
            'allemand' => ['de'],
            'espagnol' => ['es'],
            'italien' => ['it'],
        ];
    }
}
