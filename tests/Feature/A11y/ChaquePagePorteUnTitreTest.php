<?php

namespace Tests\Feature\A11y;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CHAQUE PAGE DOIT ANNONCER CE QU'ELLE EST.
 *
 * Un `<h1>` n'est pas un ornement : c'est la première chose qu'un lecteur d'écran énonce, et
 * le repère qui permet de sauter au contenu sans parcourir la navigation. Sans lui, chaque
 * page commence par le menu — identique d'un écran à l'autre.
 *
 * AUCUN DES QUATRE GABARITS N'EN FOURNIT : `grep -c "<h1" resources/views/layouts/*` rend zéro
 * partout. C'est donc à chaque page d'écrire le sien.
 *
 * ON MESURE LE RENDU, PAS LA SOURCE. Un titre peut venir d'un composant Livewire ou d'un
 * partiel ; chercher `<h1` dans la vue routée ne prouverait rien.
 *
 * UNE SEULE EXÉCUTION, TOUTES LES PAGES. Une assertion par page ferait payer la remise à zéro
 * de la base cent vingt fois, et n'en nommerait qu'une à chaque échec.
 */
class ChaquePagePorteUnTitreTest extends TestCase
{
    use RefreshDatabase;

    public function test_chaque_page_routee_annonce_ce_qu_elle_est(): void
    {
        $comptes = [
            'admin' => User::factory()->admin()->create(),
            'employe' => User::factory()->employe()->create(),
            'client' => User::factory()->client()->create(),
        ];

        $muettes = [];
        $vues = 0;

        foreach ($this->pagesRoutees() as $cle => [$chemin, $role]) {
            $utilisateur = $comptes[$role] ?? $comptes['client'];

            $reponse = $this->actingAs($utilisateur)->get($chemin);

            // Une page qui refuse, redirige ou n'existe pas ne dit rien sur les titres.
            if ($reponse->status() !== 200) {
                continue;
            }

            $vues++;

            if (preg_match('/<h1[\s>]/i', $reponse->getContent() ?: '') !== 1) {
                $muettes[] = $cle.'  '.$chemin;
            }
        }

        sort($muettes);

        /*
         * LE SEUIL EST BAS PARCE QUE LA BASE DE TEST EST VIERGE. Beaucoup de pages y
         * redirigent faute de donnée — une réservation, une organisation. Le relevé complet
         * s'est fait contre la base de développement, où 117 pages sur 122 rendent 200 ;
         * ici, on vérifie surtout qu'on mesure encore QUELQUE CHOSE.
         */
        $this->assertGreaterThan(15, $vues,
            'Presque aucune page n a répondu 200 : ce test ne prouverait plus rien.');

        $this->assertSame([], $muettes,
            'Ces pages n’annoncent pas ce qu’elles sont : un lecteur d’écran y commence par le menu, '
            .'identique d’une page à l’autre.');
    }

    /**
     * Les pages embarquees, lues dans la configuration versionnee — la meme source que
     * `parity:webview-manifest`, qui n'en fabrique qu'une copie pour le harnais visuel.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private function pagesRoutees(): array
    {
        $cas = [];

        /** @var array<int, array<string, mixed>> $modules */
        $modules = (array) config('parity.modules', []);

        foreach ($modules as $m) {
            if (($m['mobile'] ?? null) !== 'webview') {
                continue;
            }

            /** @var list<string> $roles */
            $roles = array_values((array) ($m['roles'] ?? []));
            $role = $roles[0] ?? 'client';

            $cas[(string) $m['key']] = [(string) $m['path'], $role === 'provider' ? 'employe' : $role];
        }

        return $cas;
    }
}
