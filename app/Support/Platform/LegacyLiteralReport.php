<?php

namespace App\Support\Platform;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LegacyLiteralReport
{
    public function __construct(protected ?Filesystem $files = null)
    {
        $this->files ??= new Filesystem();
    }

    public function build(): array
    {
        $root = base_path();
        $scanPaths = ['app', 'routes', 'resources/views', 'tests'];

        $patterns = [
            'roles' => [
                'label' => 'Littéraux de rôles',
                'regexes' => ["/'admin'/", "/'client'/", "/'employe'/", "/'entreprise'/"],
            ],
            'booking_statuses' => [
                'label' => 'Littéraux de statuts booking',
                'regexes' => [
                    "/'en_attente'/",
                    "/'confirme'/",
                    "/'en_route'/",
                    "/'sur_place'/",
                    "/'termine'/",
                    "/'refuse'/",
                ],
            ],
            'mission_statuses' => [
                'label' => 'Littéraux de statuts mission',
                'regexes' => [
                    "/'planned'/",
                    "/'assigned'/",
                    "/'arrived'/",
                    "/'started'/",
                    "/'paused'/",
                    "/'completed'/",
                    "/'cancelled'/",
                ],
            ],
            'priority_literals' => [
                'label' => 'Littéraux de priorité',
                'regexes' => [
                    "/'normale'/",
                    "/'haute'/",
                    "/'urgente'/",
                    "/'critique'/",
                ],
            ],
            'todo_markers' => [
                'label' => 'TODO/FIXME/XXX',
                'regexes' => [
                    '/TODO/i',
                    '/FIXME/i',
                    '/XXX/',
                ],
            ],
        ];

        $ignored = [
            'app/Support/Domain/BookingStatus.php',
            'app/Support/Domain/MissionStatus.php',
            'app/Models/User.php',
            'vendor/',
            'node_modules/',
            'storage/',
            'bootstrap/cache/',
            '.git/',
        ];

        $files = collect($scanPaths)
            ->flatMap(fn (string $relativePath) => $this->files->allFiles($root . DIRECTORY_SEPARATOR . $relativePath))
            ->map(fn ($file) => str_replace('\\', '/', Str::after($file->getPathname(), $root . DIRECTORY_SEPARATOR)))
            ->filter(fn (string $path) => ! $this->shouldIgnore($path, $ignored))
            ->values();

        $results = collect($patterns)->map(function (array $config, string $key) use ($files, $root) {
            $matches = $files
                ->map(function (string $path) use ($config, $root) {
                    $content = $this->files->get($root . DIRECTORY_SEPARATOR . $path);
                    $count = 0;

                    foreach ($config['regexes'] as $regex) {
                        preg_match_all($regex, $content, $hits);
                        $count += count($hits[0] ?? []);
                    }

                    return $count > 0 ? ['file' => $path, 'count' => $count] : null;
                })
                ->filter()
                ->values();

            return [
                'key' => $key,
                'label' => $config['label'],
                'count' => $matches->sum('count'),
                'files' => $matches->take(12)->all(),
            ];
        })->values();

        return [
            'summary' => [
                'total_flags' => $results->sum('count'),
                'categories_with_flags' => $results->where('count', '>', 0)->count(),
            ],
            'checks' => $results->all(),
        ];
    }

    protected function shouldIgnore(string $path, array $ignored): bool
    {
        foreach ($ignored as $prefix) {
            if (str_starts_with($path, $prefix) || $path === $prefix) {
                return true;
            }
        }

        return false;
    }
}
