<?php

namespace App\Livewire\Client;

use App\Events\Gdpr\GdprExportReady;
use App\Events\Gdpr\GdprRequestCreated;
use App\Models\GdprDataRequest;
use App\Notifications\Gdpr\GdprExportReadyNotification;
use App\Notifications\Gdpr\GdprRequestCreatedNotification;
use App\Services\Gdpr\DataErasureService;
use App\Services\Gdpr\DataExportService;
use App\Support\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Component;

class GdprDataPage extends Component
{
    public string $erasureReason = '';
    public bool $confirmErasure = false;

    public function requestExport(): void
    {
        $existing = GdprDataRequest::query()
            ->where('user_id', Auth::id())
            ->ofType(GdprDataRequest::TYPE_EXPORT)
            ->where('status', GdprDataRequest::STATUS_PROCESSING)
            ->first();

        if ($existing) {
            $this->dispatch('toast', 'Un export est déjà en cours.', 'info');
            return;
        }

        $request = GdprDataRequest::create([
            'user_id' => Auth::id(),
            'type' => GdprDataRequest::TYPE_EXPORT,
            'status' => GdprDataRequest::STATUS_PROCESSING,
            'reference' => $this->generateReference(),
            'requested_at' => now(),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);

        ActivityLogger::log('gdpr.export_requested', $request, [
            'user_id' => Auth::id(),
        ]);

        GdprRequestCreated::dispatch($request);
        Auth::user()->notify(new GdprRequestCreatedNotification($request));

        try {
            app(DataExportService::class)->execute($request);
            $request->refresh();
            GdprExportReady::dispatch($request);
            Auth::user()->notify(new GdprExportReadyNotification($request));
            $this->dispatch('toast', 'Export prêt au téléchargement.', 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', "Erreur lors de l'export : " . $e->getMessage(), 'error');
        }
    }

    public function downloadExport(int $requestId): mixed
    {
        $request = GdprDataRequest::query()
            ->where('id', $requestId)
            ->where('user_id', Auth::id())
            ->where('status', GdprDataRequest::STATUS_FULFILLED)
            ->whereNotNull('export_file_path')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $request) {
            $this->dispatch('toast', 'Export indisponible ou expiré.', 'error');
            return null;
        }

        $disk = (string) config('gdpr.export_disk', 'local');

        ActivityLogger::log('gdpr.export_downloaded', $request, [
            'user_id' => Auth::id(),
        ]);

        return Storage::disk($disk)->download(
            $request->export_file_path,
            $request->reference . '.json',
        );
    }

    public function requestErasure(): void
    {
        $this->validate([
            'confirmErasure' => ['accepted'],
            'erasureReason' => ['nullable', 'string', 'max:2000'],
        ], [
            'confirmErasure.accepted' => 'Vous devez cocher la confirmation.',
        ]);

        $existing = GdprDataRequest::query()
            ->where('user_id', Auth::id())
            ->ofType(GdprDataRequest::TYPE_ERASURE)
            ->whereIn('status', [
                GdprDataRequest::STATUS_AWAITING_GRACE_PERIOD,
                GdprDataRequest::STATUS_AWAITING_CONFIRMATION,
                GdprDataRequest::STATUS_PROCESSING,
            ])
            ->first();

        if ($existing) {
            $this->dispatch('toast', 'Une demande de suppression est déjà active.', 'warning');
            return;
        }

        $request = app(DataErasureService::class)->schedule(Auth::user(), $this->erasureReason ?: null, [
            'ip' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);

        GdprRequestCreated::dispatch($request);
        Auth::user()->notify(new GdprRequestCreatedNotification($request));

        $this->reset(['erasureReason', 'confirmErasure']);

        $this->dispatch('toast',
            'Suppression programmée — exécutée le ' . $request->grace_period_ends_at?->format('d/m/Y') . '. Vous pouvez encore annuler avant cette date.',
            'success');
    }

    public function cancelErasure(int $requestId): void
    {
        $request = GdprDataRequest::query()
            ->where('id', $requestId)
            ->where('user_id', Auth::id())
            ->ofType(GdprDataRequest::TYPE_ERASURE)
            ->first();

        if (! $request) {
            return;
        }

        app(DataErasureService::class)->cancel($request, Auth::user(), 'Annulé par l\'utilisateur');
        $this->dispatch('toast', 'Demande de suppression annulée.', 'success');
    }

    public function render(): View
    {
        $userId = Auth::id();

        $requests = GdprDataRequest::query()
            ->where('user_id', $userId)
            ->latest('id')
            ->limit(20)
            ->get();

        $latestExport = $requests
            ->where('type', GdprDataRequest::TYPE_EXPORT)
            ->where('status', GdprDataRequest::STATUS_FULFILLED)
            ->where(fn ($r) => $r->expires_at === null || $r->expires_at->isFuture())
            ->first();

        $activeErasure = $requests
            ->where('type', GdprDataRequest::TYPE_ERASURE)
            ->whereIn('status', [
                GdprDataRequest::STATUS_AWAITING_GRACE_PERIOD,
                GdprDataRequest::STATUS_AWAITING_CONFIRMATION,
            ])
            ->first();

        return view('livewire.client.gdpr-data-page', [
            'requests' => $requests,
            'latestExport' => $latestExport,
            'activeErasure' => $activeErasure,
        ]);
    }

    protected function generateReference(): string
    {
        $prefix = (string) config('gdpr.reference_prefix', 'GDPR');
        do {
            $candidate = $prefix . '-' . strtoupper(\Illuminate\Support\Str::random(10));
        } while (GdprDataRequest::where('reference', $candidate)->exists());

        return $candidate;
    }
}
