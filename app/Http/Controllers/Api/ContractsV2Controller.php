<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContractDocument;
use App\Models\ContractSignature;
use App\Models\ContractTemplate;
use App\Services\ContractsV2\ContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ContractsV2Controller extends Controller
{
    public function __construct(protected ContractService $svc)
    {
    }

    public function activeTemplates(Request $request): JsonResponse
    {
        $rows = ContractTemplate::query()
            ->active()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('role'), fn ($q) => $q->whereIn('role', [$request->string('role'), ContractTemplate::ROLE_ALL]))
            ->orderByDesc('version')
            ->get([
                'id', 'code', 'name', 'type', 'role', 'version', 'description',
            ]);

        return response()->json(['data' => $rows]);
    }

    public function renderDocument(Request $request): JsonResponse
    {
        $data = $request->validate([
            'template_code' => ['required', 'string', 'max:64'],
            'variables' => ['nullable', 'array'],
            'locale' => ['nullable', 'string', 'max:8'],
        ]);

        try {
            $doc = $this->svc->renderDocumentFor(
                templateCode: $data['template_code'],
                user: $request->user(),
                variables: (array) ($data['variables'] ?? []),
                locale: $data['locale'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return response()->json(['ok' => true, 'document' => $doc], 201);
    }

    public function showDocument(Request $request, ContractDocument $document): JsonResponse
    {
        if ($document->user_id && $document->user_id !== $request->user()->id) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        $this->svc->audit($document, 'opened', $request);

        return response()->json(['data' => $document->load('template:id,code,name,version,type,role')]);
    }

    public function signDocument(Request $request, ContractDocument $document): JsonResponse
    {
        if ($document->user_id && $document->user_id !== $request->user()->id) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        $data = $request->validate([
            'signature_data' => ['required', 'string', 'max:65536'],
            'signer_name' => ['required', 'string', 'max:191'],
            'terms_accepted' => ['required', 'boolean', 'accepted'],
            'country_code' => ['nullable', 'string', 'size:2'],
        ]);

        try {
            $sig = $this->svc->signDocument(
                document: $document,
                signer: $request->user(),
                signatureData: $data['signature_data'],
                signerName: $data['signer_name'],
                request: $request,
                extraMeta: array_filter(['country_code' => $data['country_code'] ?? null]),
            );
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return response()->json(['ok' => true, 'signature' => $sig], 201);
    }

    public function downloadPdf(Request $request, ContractDocument $document): Response
    {
        if ($document->user_id && $document->user_id !== $request->user()->id) {
            abort(403);
        }
        if (! $document->pdf_path) {
            abort(404, 'PDF non disponible.');
        }
        $disk = (string) config('contracts_v2.pdf_storage_disk', 'local');
        if (! Storage::disk($disk)->exists($document->pdf_path)) {
            abort(404);
        }

        $this->svc->audit($document, 'view', $request);

        return response(
            Storage::disk($disk)->get($document->pdf_path),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $document->code . '.pdf"',
            ],
        );
    }

    public function adminTemplates(Request $request): JsonResponse
    {
        $rows = ContractTemplate::query()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->orderBy('code')
            ->orderByDesc('version')
            ->limit((int) $request->integer('limit', 100))
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function adminDocuments(Request $request): JsonResponse
    {
        $rows = ContractDocument::query()
            ->with(['template:id,code,name', 'user:id,email,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('generated_at')
            ->limit((int) $request->integer('limit', 50))
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function adminInvalidateSignature(Request $request, ContractSignature $signature): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        try {
            $row = $this->svc->invalidateSignature($signature, $request->user(), $data['reason']);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return response()->json(['ok' => true, 'signature' => $row]);
    }
}
