<?php

namespace App\Services\AccountingV2;

use App\Models\AccountingEntry;
use App\Models\AccountingExport;
use App\Services\AccountingV2\Exports\CsvExportBuilder;
use App\Services\AccountingV2\Exports\ExportBuilderContract;
use App\Services\AccountingV2\Exports\FecExportBuilder;
use App\Services\AccountingV2\Exports\QuickBooksIifExportBuilder;
use App\Services\AccountingV2\Exports\SageExportBuilder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ExportManager
{
    /**
     * @return AccountingExport
     */
    public function generate(string $format, int $year, ?int $month = null, ?int $userId = null): AccountingExport
    {
        if (! in_array($format, (array) config('accounting_v2.allowed_formats', []), true)) {
            throw ValidationException::withMessages(['format' => ['Format non supporté.']]);
        }
        $builder = $this->builderFor($format);

        $export = AccountingExport::query()->create([
            'code' => AccountingExport::generateCode(),
            'format' => $format,
            'period_year' => $year,
            'period_month' => $month,
            'status' => AccountingExport::STATUS_PENDING,
            'generated_by_user_id' => $userId,
        ]);

        try {
            $q = AccountingEntry::query();
            $q->forPeriod($year, $month);
            $built = $builder->build($q);

            $disk = (string) config('accounting_v2.export_storage_disk', 'local');
            $prefix = trim((string) config('accounting_v2.export_path_prefix', 'accounting_exports'), '/');
            $filename = sprintf(
                '%04d-%02d_%s_%s.%s',
                $year,
                $month ?? 0,
                $format,
                substr($export->code, 0, 8),
                ltrim($built['extension'], '.'),
            );
            $path = $prefix . '/' . date('Y/m') . '/' . $filename;
            Storage::disk($disk)->put($path, $built['content']);

            $retention = (int) config('accounting_v2.export_retention_days', 365);
            $export->update([
                'status' => AccountingExport::STATUS_READY,
                'file_path' => $path,
                'file_size_bytes' => strlen($built['content']),
                'file_hash' => hash('sha256', $built['content']),
                'row_count' => $built['row_count'],
                'generated_at' => now(),
                'expires_at' => $retention > 0 ? now()->addDays($retention) : null,
                'metadata' => ['mime' => $built['mime']],
            ]);
        } catch (\Throwable $e) {
            Log::error('[accounting_v2] export build failed', [
                'export_id' => $export->id, 'error' => $e->getMessage(),
            ]);
            $export->update([
                'status' => AccountingExport::STATUS_FAILED,
                'last_error' => mb_substr($e->getMessage(), 0, 500),
            ]);
        }

        return $export->fresh();
    }

    public function builderFor(string $format): ExportBuilderContract
    {
        return match ($format) {
            'csv' => new CsvExportBuilder(),
            'fec' => new FecExportBuilder(),
            'sage' => new SageExportBuilder(),
            'quickbooks_iif' => new QuickBooksIifExportBuilder(),
            default => throw ValidationException::withMessages(['format' => ['Format inconnu : ' . $format]]),
        };
    }
}
