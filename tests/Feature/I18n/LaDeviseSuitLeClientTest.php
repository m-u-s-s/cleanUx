<?php

namespace Tests\Feature\I18n;

use App\Livewire\OrderEngine\OrderJourney;
use App\Models\User;
use App\Services\Localization\Money;
use App\View\Components\Money as MoneyComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LA DEVISE SUIT LE CLIENT, PAS LE GABARIT.
 *
 * La plateforme sert la Belgique, la France et le Maroc : la devise suit la POSITION. Un
 * symbole écrit en dur dans une vue montre donc un faux montant à une partie du marché —
 * et un prix faux n'est pas un défaut de format, c'est un engagement qu'on ne tiendra pas.
 *
 * Ce qui se vérifie ici, ce sont les endroits où `<x-money>` ne pouvait PAS aller : le
 * parcours de commande anime son montant par un script, et le supplément du catalogue est
 * assemblé en PHP avant d'être affiché. Les deux lisaient l'euro en dur.
 */
class LaDeviseSuitLeClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_symbole_du_contexte_suit_la_devise_de_l_utilisateur(): void
    {
        $client = User::factory()->client()->create(['preferred_currency' => 'MAD']);
        $this->actingAs($client);

        $this->assertSame('MAD', MoneyComponent::deviseDuContexte());
        $this->assertSame('MAD', app(Money::class)->symbol('MAD'));
    }

    /** TÉMOIN — sans préférence, le contexte retombe sur la devise de base, pas sur un défaut arbitraire. */
    public function test_sans_preference_le_contexte_prend_la_devise_de_base(): void
    {
        $client = User::factory()->client()->create(['preferred_currency' => null]);
        $this->actingAs($client);

        $this->assertSame(
            strtoupper((string) config('fx.base_currency', 'EUR')),
            MoneyComponent::deviseDuContexte(),
        );
    }

    /**
     * LE PARCOURS DE COMMANDE N'ÉCRIT PLUS L'EURO EN DUR.
     *
     * Son montant est animé par un script — il ne peut donc pas passer par `<x-money>`, et le
     * symbole vit à côté du nombre. Il est désormais lu dans la devise du contexte.
     */
    public function test_le_parcours_de_commande_ne_porte_plus_l_euro_en_dur(): void
    {
        $client = User::factory()->client()->create(['preferred_currency' => 'MAD']);
        $this->actingAs($client);

        $rendu = Livewire::test(OrderJourney::class)->html();

        $this->assertStringNotContainsString('</span> €', $rendu);
    }

    /**
     * AUCUNE VUE NE COLLE PLUS UN EURO À UN `number_format`.
     *
     * Ce garde vise la FORME, pas un inventaire : il interdit celles qui ont un remplacement
     * direct. Les symboles qui restent portent une unité, une plage, ou vivent dans la page
     * d'accueil — chacun demande une décision, pas une substitution.
     */
    public function test_aucune_vue_ne_colle_un_euro_a_un_montant_formate(): void
    {
        $racine = str_replace(chr(92), '/', resource_path('views'));
        $fautives = [];

        $iterateur = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));

        foreach ($iterateur as $fichier) {
            if (! $fichier->isFile() || ! str_ends_with($fichier->getFilename(), '.blade.php')) {
                continue;
            }

            $relatif = ltrim(str_replace($racine, '', str_replace(chr(92), '/', $fichier->getPathname())), '/');

            // La vitrine et les documents sortent du périmètre : la première est figée par
            // décision, les seconds portent déjà `<x-money>`.
            if ($relatif === 'home.blade.php' || preg_match('#^(emails|mail|notifications|pdf|exports|scribe)/#', $relatif) === 1) {
                continue;
            }

            $code = (string) file_get_contents($fichier->getPathname());

            if (preg_match_all("/number_format\([^)]*\)\s*\.\s*'\s*\x{20AC}/u", $code, $m) > 0) {
                $fautives[] = $relatif.' : '.count($m[0]).' x';
            }
        }

        $this->assertSame([], $fautives, 'Le symbole doit venir de la devise du montant, pas du gabarit.');
    }
}
