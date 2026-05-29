<?php

namespace App\Livewire\Client;

use App\Models\Booking;
use App\Services\Nps\NpsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

class NpsSurvey extends Component
{
    #[Url]
    public string $survey = 'post_booking';

    #[Url]
    public ?int $bookingId = null;

    public ?int $score = null;

    public string $comment = '';

    public bool $submitted = false;

    public function selectScore(int $score): void
    {
        $this->score = max(0, min(10, $score));
    }

    public function submit(NpsService $service): void
    {
        if ($this->score === null) {
            $this->dispatch('toast', 'Sélectionnez une note de 0 à 10.', 'error');

            return;
        }

        $user = Auth::user();
        $booking = $this->bookingId ? Booking::find($this->bookingId) : null;

        try {
            $service->submit(
                user: $user,
                surveyCode: $this->survey,
                score: (int) $this->score,
                booking: $booking,
                comment: $this->comment ?: null,
            );
            $this->submitted = true;
            $this->dispatch('toast', 'Merci pour votre retour !', 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur : '.$e->getMessage(), 'error');
        }
    }

    public function render(): View
    {
        return view('livewire.client.nps-survey')->layout('layouts.app');
    }
}
