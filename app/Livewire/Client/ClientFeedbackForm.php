<?php

namespace App\Livewire\Client;

use App\Models\Booking;
use App\Models\Feedback;
use App\Services\Rating\RatingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ClientFeedbackForm extends Component
{
    public Booking $rendezVous;

    public int $rating = 5;
    public ?int $punctuality = null;
    public ?int $quality = null;
    public ?int $communication = null;
    public ?int $value = null;
    public string $comment = '';
    public bool $is_public = true;

    public ?Feedback $existingFeedback = null;
    public bool $submitted = false;
    public ?string $globalError = null;

    public function mount(Booking $rendezVous): void
    {
        abort_unless((int) $rendezVous->client_id === (int) Auth::id(), 403);

        $this->rendezVous = $rendezVous;

        $this->existingFeedback = Feedback::query()
            ->where('booking_id', $rendezVous->id)
            ->where('direction', Feedback::DIRECTION_CLIENT_TO_PROVIDER)
            ->first();

        if ($this->existingFeedback) {
            $this->rating = (int) ($this->existingFeedback->rating ?? $this->existingFeedback->note ?? 5);
            $this->punctuality = $this->existingFeedback->punctuality_score;
            $this->quality = $this->existingFeedback->quality_score;
            $this->communication = $this->existingFeedback->communication_score;
            $this->value = $this->existingFeedback->value_score;
            $this->comment = (string) $this->existingFeedback->effectiveComment();
            $this->is_public = (bool) $this->existingFeedback->is_public;
        }
    }

    public function setRating(int $rating): void
    {
        $this->rating = max(1, min(5, $rating));
    }

    public function setDimension(string $dim, int $value): void
    {
        $value = max(1, min(5, $value));
        match ($dim) {
            'punctuality' => $this->punctuality = $value,
            'quality' => $this->quality = $value,
            'communication' => $this->communication = $value,
            'value' => $this->value = $value,
            default => null,
        };
    }

    public function submit(): void
    {
        $this->globalError = null;

        $this->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'punctuality' => ['nullable', 'integer', 'min:1', 'max:5'],
            'quality' => ['nullable', 'integer', 'min:1', 'max:5'],
            'communication' => ['nullable', 'integer', 'min:1', 'max:5'],
            'value' => ['nullable', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'is_public' => ['boolean'],
        ]);

        try {
            app(RatingService::class)->submit(
                $this->rendezVous,
                Auth::user(),
                Feedback::DIRECTION_CLIENT_TO_PROVIDER,
                [
                    'rating' => $this->rating,
                    'punctuality' => $this->punctuality,
                    'quality' => $this->quality,
                    'communication' => $this->communication,
                    'value' => $this->value,
                    'comment' => $this->comment ?: null,
                    'is_public' => $this->is_public,
                ],
            );

            $this->submitted = true;
            $this->dispatch('toast', 'Merci pour votre avis !', 'success');
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $msg) {
                    $this->addError($field, $msg);
                }
            }
            $this->globalError = collect($e->errors())->flatten()->first();
        }
    }

    public function render(): View
    {
        return view('livewire.client.client-feedback-form');
    }
}
