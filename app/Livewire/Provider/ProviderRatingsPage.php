<?php

namespace App\Livewire\Provider;

use App\Models\Feedback;
use App\Models\RatingReport;
use App\Services\Rating\ProviderResponseService;
use App\Services\Rating\RatingModerationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class ProviderRatingsPage extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $filter = 'all';

    public ?int $replyingTo = null;
    public string $responseText = '';

    public ?int $reportingId = null;
    public string $reportReason = RatingReport::REASON_OTHER;
    public string $reportDetails = '';

    public function startReply(int $feedbackId): void
    {
        $feedback = Feedback::query()
            ->where('id', $feedbackId)
            ->forProvider(Auth::id())
            ->firstOrFail();

        $this->replyingTo = $feedbackId;
        $this->responseText = (string) $feedback->provider_response;
    }

    public function cancelReply(): void
    {
        $this->reset(['replyingTo', 'responseText']);
    }

    public function submitReply(): void
    {
        $this->validate([
            'responseText' => ['required', 'string', 'min:1', 'max:1000'],
        ]);

        $feedback = Feedback::findOrFail($this->replyingTo);

        try {
            app(ProviderResponseService::class)->reply($feedback, Auth::user(), $this->responseText);
            $this->reset(['replyingTo', 'responseText']);
            $this->dispatch('toast', 'Réponse publiée.', 'success');
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $msg) {
                    $this->addError($field, $msg);
                }
            }
        }
    }

    public function startReport(int $feedbackId): void
    {
        $this->reportingId = $feedbackId;
        $this->reportReason = RatingReport::REASON_OTHER;
        $this->reportDetails = '';
    }

    public function cancelReport(): void
    {
        $this->reset(['reportingId', 'reportReason', 'reportDetails']);
    }

    public function submitReport(): void
    {
        $this->validate([
            'reportReason' => ['required', 'string'],
            'reportDetails' => ['nullable', 'string', 'max:1000'],
        ]);

        $feedback = Feedback::query()
            ->where('id', $this->reportingId)
            ->forProvider(Auth::id())
            ->firstOrFail();

        try {
            app(RatingModerationService::class)->report(
                $feedback,
                Auth::user(),
                $this->reportReason,
                $this->reportDetails ?: null,
            );
            $this->reset(['reportingId', 'reportReason', 'reportDetails']);
            $this->dispatch('toast', 'Signalement envoyé. Nos modérateurs vont examiner cet avis.', 'success');
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $msg) {
                    $this->addError($field, $msg);
                }
            }
        }
    }

    public function render(): View
    {
        $providerId = Auth::id();
        $profile = Auth::user()->providerProfile;

        $query = Feedback::query()
            ->with(['client:id,name', 'rendezVous:id,booking_reference'])
            ->forProvider($providerId);

        $query = match ($this->filter) {
            'pending_response' => $query->where('status', Feedback::STATUS_PUBLISHED)
                ->whereNull('provider_responded_at'),
            'hidden' => $query->where('is_hidden', true),
            'low' => $query->where(function ($q) {
                $q->where('rating', '<=', 3)->orWhere('note', '<=', 3);
            }),
            default => $query,
        };

        $ratings = $query->latest('published_at')->latest('id')->paginate(15);

        return view('livewire.provider.provider-ratings-page', [
            'profile' => $profile,
            'ratings' => $ratings,
        ]);
    }
}
