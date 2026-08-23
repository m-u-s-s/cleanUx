<?php

namespace App\Console\Commands;

use App\Support\ActivityLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/** VerifyBackup — monthly verification that backups are present and non-empty. */
class VerifyBackup extends Command
{
    protected $signature = 'backup:verify
                            {--disk=local : Storage disk to inspect (default: local)}
                            {--min-bytes=1024 : Minimum acceptable file size in bytes}';

    protected $description = 'Verify that the latest backup exists and is non-empty';

    public function handle(): int
    {
        $disk = $this->option('disk');
        $minSize = (int) $this->option('min-bytes');

        $this->info("Verifying backups on disk [{$disk}]...");

        $result = $this->checkLatestBackup($disk, $minSize);

        $this->logOutcome($result);

        if ($result['status'] === 'ok') {
            $this->info('Backup verification passed: '.$result['message']);

            return self::SUCCESS;
        }

        $this->error('Backup verification FAILED: '.$result['message']);
        Log::error('backup.verify.failed', $result);

        return self::FAILURE;
    }

    /** @return array{status:string, message:string, file:string|null, size_bytes:int|null} */
    private function checkLatestBackup(string $disk, int $minSize): array
    {
        try {
            $files = Storage::disk($disk)->allFiles('Laravel');
        } catch (\Throwable $e) {
            return $this->buildFailure(null, "Cannot list backup directory: {$e->getMessage()}");
        }

        $zipFiles = array_filter($files, fn (string $f) => str_ends_with($f, '.zip'));

        if (empty($zipFiles)) {
            return $this->buildFailure(null, 'No backup archives found in backup disk.');
        }

        // Sort descending so the latest file is first.
        usort($zipFiles, fn ($a, $b) => strcmp($b, $a));
        $latest = reset($zipFiles);

        try {
            $size = Storage::disk($disk)->size($latest);
        } catch (\Throwable $e) {
            return $this->buildFailure($latest, "Cannot stat backup file: {$e->getMessage()}");
        }

        if ($size < $minSize) {
            return $this->buildFailure($latest, "Backup file is suspiciously small ({$size} bytes < {$minSize} minimum).");
        }

        return [
            'status' => 'ok',
            'message' => "Backup OK — {$latest} ({$size} bytes)",
            'file' => $latest,
            'size_bytes' => $size,
        ];
    }

    /** @return array{status:string, message:string, file:string|null, size_bytes:int|null} */
    private function buildFailure(?string $file, string $message): array
    {
        return ['status' => 'failed', 'message' => $message, 'file' => $file, 'size_bytes' => null];
    }

    private function logOutcome(array $result): void
    {
        ActivityLogger::system('backup.verify.'.$result['status'], null, [
            'file' => $result['file'],
            'size_bytes' => $result['size_bytes'],
            'message' => $result['message'],
        ]);
    }
}
