<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

/**
 * L'INVENTAIRE DES PAGES, PAR ROLE — pour le tour de la plateforme.
 *
 * Il se DERIVE des routes, il ne se tient pas a la main : une liste ecrite a cote du routeur
 * vieillit des la premiere page ajoutee, et c'est exactement ce qu'on cherche a eviter.
 */
class ListerLesPagesParRole extends Command
{
    protected $signature = 'qa:pages-par-role
                            {--sortie=tools/visual-qa/out/routes-par-role.json : Ou ecrire l inventaire}';

    protected $description = 'Écrit l’inventaire des pages ouvrables, rangées par rôle, pour le balayage navigateur.';

    /** @var array<string, array{cred: string|null, prefixes: list<string>}> */
    private const ESPACES = [
        'public' => ['cred' => null, 'prefixes' => ['']],
        'client' => ['cred' => 'client', 'prefixes' => ['client.']],
        'entreprise' => ['cred' => 'entreprise', 'prefixes' => ['client-company.']],
        'provider' => ['cred' => 'provider', 'prefixes' => ['employe.']],
        'provider_company' => ['cred' => 'provider_company', 'prefixes' => ['provider-company.']],
        'admin' => ['cred' => 'admin', 'prefixes' => ['admin.']],
    ];

    /**
     * Ce qui n'est pas une page : exports, telechargements, deconnexion, passerelles.
     *
     * @var list<string>
     */
    private const PAS_UNE_PAGE = [
        'export', 'download', 'telecharger', 'logout', 'stripe-connect', '.csv', '.xlsx', '.pdf',
        'webhook', 'callback', 'redirect', 'impersonate',
        // `m/enter` echange un ticket a usage unique : sans ticket il rend 419, et c'est
        // exactement ce qu'il doit faire. Ce n'est pas une page, c'est une passerelle.
        'm/enter',
        // `sanctum/csrf-cookie` rend 204 sans corps : le navigateur abandonne la navigation.
        'sanctum',
    ];

    public function handle(): int
    {
        $inventaire = [];

        foreach (self::ESPACES as $role => $espace) {
            $chemins = [];

            foreach (Route::getRoutes()->getRoutes() as $route) {
                $nom = (string) $route->getName();
                $uri = $route->uri();

                if (! in_array('GET', $route->methods(), true)) {
                    continue;
                }

                // Une page a une adresse fixe : celles a parametre demandent une donnee
                // precise, et un balayage qui invente un identifiant mesure un 404.
                if (str_contains($uri, '{')) {
                    continue;
                }

                if ($nom === '' || $this->estUnTelechargement($nom, $uri)) {
                    continue;
                }

                $correspond = false;

                foreach ($espace['prefixes'] as $prefixe) {
                    if ($prefixe === '') {
                        // L'espace public : les pages de la vitrine, pas les tableaux de bord.
                        $correspond = ! str_starts_with($uri, 'dashboard')
                            && ! str_starts_with($uri, 'admin')
                            && ! str_starts_with($uri, 'api')
                            && ! str_starts_with($uri, '_')
                            && ! str_contains($uri, 'livewire');

                        break;
                    }

                    if (str_starts_with($nom, $prefixe)) {
                        $correspond = true;

                        break;
                    }
                }

                if ($correspond) {
                    $chemins[] = '/'.ltrim($uri, '/');
                }
            }

            // Les modules transversaux : chaque role connecte y a droit.
            if ($espace['cred'] !== null) {
                foreach (['peer.catalogue', 'peer.my-rentals', 'peer.owner.vehicles', 'notifications.index', 'profile.show'] as $transversal) {
                    if (Route::has($transversal)) {
                        $chemins[] = '/'.ltrim(parse_url(route($transversal, [], false), PHP_URL_PATH) ?: '', '/');
                    }
                }
            }

            $chemins = array_values(array_unique($chemins));
            sort($chemins);

            $inventaire[$role] = ['cred' => $espace['cred'], 'chemins' => $chemins];

            $this->line(sprintf('%-18s %d page(s)', $role, count($chemins)));
        }

        $sortie = (string) $this->option('sortie');
        @mkdir(dirname(base_path($sortie)), 0775, true);
        file_put_contents(base_path($sortie), json_encode($inventaire, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info('Inventaire écrit dans '.$sortie);

        return self::SUCCESS;
    }

    private function estUnTelechargement(string $nom, string $uri): bool
    {
        foreach (self::PAS_UNE_PAGE as $marqueur) {
            if (str_contains($nom, $marqueur) || str_contains($uri, $marqueur)) {
                return true;
            }
        }

        return false;
    }
}
