<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * M3 — move existing mission/dispute media from the PUBLIC disk to the PRIVATE disk, preserving the
 * relative path (so the stored path strings keep resolving, now served via the signed media route).
 * Idempotent: files already on private (or absent from public) are skipped. Public provider-profile
 * / avatar images are intentionally NOT moved.
 */
class MigrateMediaToPrivate extends Command
{
    protected $signature = 'media:migrate-to-private {--dry-run : List what would move without moving}';

    protected $description = 'Move mission/dispute media (rendezvous, missions, claims, reports, quality) from the public disk to the private disk';

    /** Path prefixes that hold private mission/dispute media. */
    private array $prefixes = ['rendezvous', 'missions', 'claims', 'reports', 'quality'];

    public function handle(): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk('private');
        $dry = (bool) $this->option('dry-run');

        $moved = 0;
        $skipped = 0;

        foreach ($this->prefixes as $prefix) {
            if (! $public->exists($prefix)) {
                continue;
            }

            foreach ($public->allFiles($prefix) as $path) {
                if ($private->exists($path)) {
                    $skipped++;

                    continue;
                }

                $this->line(($dry ? '[dry] ' : '').'move: '.$path);

                if (! $dry) {
                    $private->put($path, $public->get($path));
                    $public->delete($path);
                }
                $moved++;
            }
        }

        $this->info(($dry ? '[dry-run] ' : '')."Done. moved={$moved} skipped(existing)={$skipped}");

        return self::SUCCESS;
    }
}
