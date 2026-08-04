<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Régénère la liste des pages embarquées que balaie le harnais de QA visuelle.
 *
 * `storage/app/parity_webview.json` était produit à la main. Il avait donc DÉRIVÉ du registre :
 * 120 modules d'un côté, un registre qui en comptait un de plus de l'autre. Un balayage qui passe
 * au vert sur une liste périmée ne dit rien de la page qu'on vient d'ajouter — il dit seulement
 * qu'on ne l'a pas regardée.
 *
 * La commande est idempotente et sans effet de bord : on la relance après chaque ajout au registre.
 */
class ParityWebviewManifest extends Command
{
    protected $signature = 'parity:webview-manifest';

    protected $description = 'Régénère storage/app/parity_webview.json depuis le registre de parité.';

    public function handle(): int
    {
        /** @var array<int, array<string, mixed>> $declares */
        $declares = (array) config('parity.modules', []);

        $modules = collect($declares)
            ->filter(fn (array $m) => ($m['mobile'] ?? null) === 'webview')
            ->map(fn (array $m) => [
                'key' => $m['key'],
                'path' => $m['path'],
                'roles' => array_values($m['roles'] ?? []),
            ])
            ->values()
            ->all();

        Storage::disk('local')->put(
            'parity_webview.json',
            json_encode($modules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        $this->info(sprintf('%d pages embarquées écrites dans storage/app/parity_webview.json.', count($modules)));

        return self::SUCCESS;
    }
}
