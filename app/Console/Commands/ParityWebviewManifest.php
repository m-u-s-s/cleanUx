<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Régénère la liste des pages embarquées que balaie le harnais de QA visuelle. */
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
