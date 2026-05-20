<?php

namespace App\Livewire\Employe;

use App\Models\Booking;
use App\Models\Feedback;
use App\Services\Rating\RatingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Attributes\Url;

/**
 * Provider rates the client (Uber-style reciprocal rating).
 * Accessible after mission completion.
 */
class EmployeeRateClient extends Component
{
    #[Url]
    public ?int $bookingId = null;

    public int $rating = 5;
    public string $comment = '';
    public int $punctuality = 5;
    public int $quality = 5;
    public int $communication = 5;

    public function mount(?int $bookingId = null): void
    {
        $this->bookingId = $bookingId;
    }

    public function submit(): void
    {
        $this->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'punctuality' => ['nullable', 'integer', 'min:1', 'max:5'],
            'quality' => ['nullable', 'integer', 'min:1', 'max:5'],
            'communication' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $booking = Booking::findOrFail($this->bookingId);
        $author = Auth::user();

        try {
            app(RatingService::class)->submit(
                booking: $booking,
                author: $author,
                direction: Feedback::DIRECTION_PROVIDER_TO_CLIENT,
                payload: [
                    'rating' => $this->rating,
                    'comment' => $this->comment ?: null,
                    'punctuality_score' => $this->punctuality,
                    'quality_score' => $this->quality,
                    'communication_score' => $this->communication,
                ],
            );

            $this->dispatch('toast', 'Merci pour votre évaluation.', 'success');
            $this->redirect(route('dashboard.employe'));
        } catch (ValidationException $e) {
            $first = collect($e->errors())->flatten()->first();
            $this->dispatch('toast', $first ?? 'Échec.', 'error');
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur : ' . $e->getMessage(), 'error');
        }
    }

    public function render(): View
    {
        $user = Auth::user();
        $booking = $this->bookingId
            ? Booking::query()
                ->where('id', $this->bookingId)
                ->where(function ($q) use ($user) {
                    $q->where('employe_id', $user->id)
                      ->orWhere('assigned_employee_id', $user->id);
                })
                ->first()
            : null;

        $existing = $booking
            ? Feedback::query()
                ->where('booking_id', $booking->id)
                ->where('direction', Feedback::DIRECTION_PROVIDER_TO_CLIENT)
                ->first()
            : null;

        return view('livewire.employe.employee-rate-client', [
            'booking' => $booking,
            'existing' => $existing,
        ])->layout('layouts.app');
    }
}
