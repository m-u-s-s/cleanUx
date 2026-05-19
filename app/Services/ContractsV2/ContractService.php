<?php

namespace App\Services\ContractsV2;

use App\Models\ContractDocument;
use App\Models\ContractSignature;
use App\Models\ContractSignatureAudit;
use App\Models\ContractTemplate;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * ContractService v2 — orchestre le cycle de vie d'un contrat signable.
 *
 *   - resolveActiveTemplate($code) : retourne le template le plus récent valide
 *   - renderDocumentFor($templateCode, $user, $variables[]) : ContractDocument pending
 *   - signDocument($document, $signer, $signatureData, $signerName, $request) : ContractSignature + status=signed
 *   - audit($document, $event, $request, $signature?) : trace immutable
 *   - invalidateSignature($signature, $admin, $reason)
 *
 * eIDAS-lite : la signature stocke `signature_hash` calculé via SHA256(body_html + signer_name + timestamp),
 * + ip_hash + user_agent_short + terms_version + geolocation optionnelle. Preuves contractuelles
 * pour litiges (le hash permet de prouver l'intégrité du body au moment de la signature).
 */
class ContractService
{
    public function __construct(
        protected ContractRenderer $renderer,
        protected ContractPdfGenerator $pdfGenerator,
    ) {}

    public function resolveActiveTemplate(string $code, ?string $type = null, ?string $role = null): ?ContractTemplate
    {
        $query = ContractTemplate::query()
            ->where('code', $code)
            ->active()
            ->orderByDesc('version');

        if ($type) {
            $query->where('type', $type);
        }
        if ($role) {
            $query->whereIn('role', [$role, ContractTemplate::ROLE_ALL]);
        }

        return $query->get()->first(fn (ContractTemplate $t) => $t->isWithinValidity());
    }

    public function renderDocumentFor(
        string $templateCode,
        User $user,
        array $variables = [],
        ?string $locale = null,
    ): ContractDocument {
        $template = ContractTemplate::query()->where('code', $templateCode)->active()->latest('version')->first();
        if (! $template || ! $template->isWithinValidity()) {
            throw ValidationException::withMessages(['template' => "Template '{$templateCode}' introuvable ou inactif."]);
        }

        $bodyHtml = $this->renderer->renderBody($template, $user, $variables, $locale ?? $user->preferredLocale() ?? null);

        $expiryDays = (int) Config::get('contracts_v2.document_expiry_days', 30);
        $expiresAt = $expiryDays > 0 ? now()->addDays($expiryDays) : null;

        $doc = ContractDocument::create([
            'template_id' => $template->id,
            'code' => 'doc_' . Str::lower(Str::random(16)),
            'user_id' => $user->id,
            'body_rendered_html' => $bodyHtml,
            'status' => ContractDocument::STATUS_PENDING_SIGNATURE,
            'generated_at' => now(),
            'expires_at' => $expiresAt,
            'metadata' => ['variables' => $variables, 'locale' => $locale],
        ]);

        $this->audit($doc, ContractSignatureAudit::EVENT_SENT, null, null);

        ActivityLogger::log('contract_v2.document_rendered', $doc, [
            'template_code' => $template->code,
            'user_id' => $user->id,
        ]);

        return $doc;
    }

    public function signDocument(
        ContractDocument $document,
        User $signer,
        string $signatureData,
        string $signerName,
        ?Request $request = null,
        array $extraMeta = [],
    ): ContractSignature {
        if ($document->status !== ContractDocument::STATUS_PENDING_SIGNATURE) {
            throw ValidationException::withMessages(['status' => "Document non signable (status: {$document->status})."]);
        }
        if ($document->user_id && $document->user_id !== $signer->id) {
            throw ValidationException::withMessages(['signer' => 'Document destiné à un autre utilisateur.']);
        }
        if ($document->expires_at && $document->expires_at->isPast()) {
            $document->forceFill(['status' => ContractDocument::STATUS_EXPIRED])->save();
            throw ValidationException::withMessages(['expired' => 'Document expiré.']);
        }
        if (Config::get('contracts_v2.signature_required', true) && empty($signatureData)) {
            throw ValidationException::withMessages(['signature' => 'Signature électronique requise.']);
        }

        return DB::transaction(function () use ($document, $signer, $signatureData, $signerName, $request, $extraMeta) {
            $signedAt = now();
            $signatureHash = $this->renderer->buildSignableHash(
                $document->body_rendered_html,
                $signerName,
                $signedAt->toIso8601String(),
            );

            $signatureExpiryDays = (int) Config::get('contracts_v2.signature_expiry_days', 0);
            $expiresAt = $signatureExpiryDays > 0 ? $signedAt->copy()->addDays($signatureExpiryDays) : null;

            $signature = ContractSignature::create([
                'document_id' => $document->id,
                'signer_user_id' => $signer->id,
                'signer_name' => mb_substr($signerName, 0, 191),
                'signer_email_hash' => $signer->email ? hash('sha256', $signer->email) : null,
                'signature_data' => $signatureData,
                'signature_hash' => $signatureHash,
                'ip_hash' => $request?->ip() ? hash('sha256', (string) $request->ip()) : null,
                'user_agent_short' => $request?->userAgent() ? Str::limit((string) $request->userAgent(), 191, '') : null,
                'terms_version' => $document->template?->version ?? (string) Config::get('contracts_v2.default_terms_version', '2026-05-v1'),
                'country_code' => $extraMeta['country_code'] ?? null,
                'geolocation' => $extraMeta['geolocation'] ?? null,
                'signed_at' => $signedAt,
                'expires_at' => $expiresAt,
                'is_invalidated' => false,
                'metadata' => $extraMeta,
            ]);

            $document->forceFill([
                'status' => ContractDocument::STATUS_SIGNED,
            ])->save();

            // Try generate PDF (soft-fail)
            try {
                $this->pdfGenerator->generate($document->fresh());
            } catch (\Throwable $e) {
                // already logged
            }

            $this->audit($document, ContractSignatureAudit::EVENT_SIGNED, $request, $signature);

            ActivityLogger::log('contract_v2.signed', $signature, [
                'document_id' => $document->id,
                'template_code' => $document->template?->code,
                'signer_id' => $signer->id,
            ]);

            return $signature->fresh();
        });
    }

    public function invalidateSignature(ContractSignature $signature, User $admin, string $reason): ContractSignature
    {
        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['reason' => 'Raison invalidation trop courte (10 chars min).']);
        }

        $signature->forceFill([
            'is_invalidated' => true,
            'invalidated_by_user_id' => $admin->id,
            'invalidated_at' => now(),
            'invalidation_reason' => mb_substr($reason, 0, 2000),
        ])->save();

        $this->audit($signature->document, ContractSignatureAudit::EVENT_INVALIDATED, null, $signature);

        ActivityLogger::log('contract_v2.signature_invalidated', $signature, [
            'admin_id' => $admin->id,
        ]);

        return $signature->fresh();
    }

    public function audit(
        ContractDocument $document,
        string $event,
        ?Request $request = null,
        ?ContractSignature $signature = null,
    ): ContractSignatureAudit {
        return ContractSignatureAudit::create([
            'signature_id' => $signature?->id,
            'document_id' => $document->id,
            'user_id' => $document->user_id,
            'event' => $event,
            'ip_hash' => $request?->ip() ? hash('sha256', (string) $request->ip()) : null,
            'user_agent_short' => $request?->userAgent() ? mb_substr((string) $request->userAgent(), 0, 191) : null,
            'occurred_at' => now(),
        ]);
    }

    /**
     * Helper for OnboardingV2 ContractSignValidator : retourne true si l'user a
     * une signature valide pour le template_code donné.
     */
    public function userHasValidSignatureFor(User $user, string $templateCode): bool
    {
        $template = ContractTemplate::query()->where('code', $templateCode)->active()->latest('version')->first();
        if (! $template) {
            return false;
        }

        return ContractSignature::query()
            ->valid()
            ->where('signer_user_id', $user->id)
            ->whereHas('document', fn ($q) => $q->where('template_id', $template->id))
            ->exists();
    }
}
