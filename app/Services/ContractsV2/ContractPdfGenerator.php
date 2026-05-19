<?php

namespace App\Services\ContractsV2;

use App\Models\ContractDocument;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ContractPdfGenerator — render un PDF du document signé.
 *
 *   - Utilise Barryvdh\DomPDF si dispo et engine='dompdf' (default)
 *   - engine='disabled' → no-op (utile en CI/tests)
 *   - Soft-fail : si dompdf indispo, retourne null + Log warning
 */
class ContractPdfGenerator
{
    /**
     * Generate and persist PDF for the document. Returns the storage path.
     */
    public function generate(ContractDocument $document): ?string
    {
        $engine = (string) Config::get('contracts_v2.pdf_engine', 'dompdf');
        if ($engine === 'disabled') {
            return null;
        }

        if ($engine !== 'dompdf' || ! class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            Log::warning('ContractPdfGenerator: dompdf engine unavailable', ['engine' => $engine]);
            return null;
        }

        $disk = (string) Config::get('contracts_v2.pdf_storage_disk', 'local');
        $prefix = (string) Config::get('contracts_v2.pdf_path_prefix', 'contracts');

        $html = $this->wrapAsFullHtml($document);

        try {
            $pdfBinary = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->output();
        } catch (\Throwable $e) {
            Log::warning('ContractPdfGenerator: dompdf load failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        $path = sprintf('%s/%d/%s-%s.pdf',
            $prefix,
            $document->id,
            $document->code,
            Str::lower(Str::random(8)),
        );

        Storage::disk($disk)->put($path, $pdfBinary);

        $document->forceFill(['pdf_path' => $path])->save();

        return $path;
    }

    protected function wrapAsFullHtml(ContractDocument $document): string
    {
        $title = e($document->template?->name ?? 'Contrat');
        $body = $document->body_rendered_html;
        $signature = $document->activeSignature();
        $signatureBlock = '';
        if ($signature) {
            $signatureBlock = sprintf(
                '<hr><p><strong>Signé par :</strong> %s</p><p><strong>Date :</strong> %s</p><p><strong>Hash :</strong> %s</p>',
                e($signature->signer_name),
                $signature->signed_at?->format('Y-m-d H:i:s'),
                e($signature->signature_hash),
            );
        }

        return <<<HTML
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{$title}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; line-height: 1.4; }
        h1 { font-size: 18pt; }
        h2 { font-size: 14pt; }
        h3 { font-size: 12pt; }
        ul { margin: 4pt 0 4pt 20pt; }
    </style>
</head>
<body>
    <h1>{$title}</h1>
    {$body}
    {$signatureBlock}
</body>
</html>
HTML;
    }
}
