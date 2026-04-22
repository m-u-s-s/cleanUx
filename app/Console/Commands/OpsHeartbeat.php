<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class OpsHeartbeat extends Command
{
    protected $signature = 'app:ops-heartbeat {--json : Affiche le payload JSON}';

    protected $description = 'Écrit un heartbeat minimal pour la supervision et le monitoring externe';

    public function handle(): int
    {
        if (! config('operations.monitoring.heartbeat_enabled', true)) {
            $this->info('Heartbeat désactivé.');
            return self::SUCCESS;
        }

        $payload = [
            'app' => config('app.name'),
            'env' => config('app.env'),
            'at' => now()->toIso8601String(),
            'queue' => config('queue.default'),
            'cache' => config('cache.default'),
        ];

        $disk = (string) config('operations.monitoring.heartbeat_disk', 'local');
        $path = (string) config('operations.monitoring.heartbeat_path', 'ops/heartbeat.json');
        $cacheKey = (string) config('operations.monitoring.heartbeat_cache_key', 'cleanux:ops:heartbeat');

        Storage::disk($disk)->put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        Cache::put($cacheKey, $payload, now()->addMinutes(15));

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('Heartbeat écrit: ' . $path);
        }

        return self::SUCCESS;
    }
}
