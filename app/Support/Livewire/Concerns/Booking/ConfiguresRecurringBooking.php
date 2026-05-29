<?php

namespace App\Support\Livewire\Concerns\Booking;

use Illuminate\Support\Carbon;

trait ConfiguresRecurringBooking
{
    public function getRecurringFrequencyOptionsProperty(): array
    {
        return [
            'daily' => 'Chaque jour',
            'weekly' => 'Chaque semaine',
            'monthly' => 'Chaque mois',
        ];
    }

    public function getRecurringDayOptionsProperty(): array
    {
        return [
            1 => 'Lun',
            2 => 'Mar',
            3 => 'Mer',
            4 => 'Jeu',
            5 => 'Ven',
            6 => 'Sam',
            7 => 'Dim',
        ];
    }

    protected function normalizeRecurringInputs(): void
    {
        if (! $this->is_recurrent) {
            $this->recurrence_frequency = null;
            $this->recurrence_interval = 1;
            $this->recurrence_until = null;
            $this->recurrence_count = null;
            $this->recurrence_days = [];
            $this->recurrence_rule = null;

            return;
        }

        $this->recurrence_frequency = filled($this->recurrence_frequency) ? (string) $this->recurrence_frequency : null;
        $this->recurrence_interval = filled($this->recurrence_interval) ? (int) $this->recurrence_interval : 1;
        $this->recurrence_until = filled($this->recurrence_until) ? (string) $this->recurrence_until : null;
        $this->recurrence_count = filled($this->recurrence_count) ? (int) $this->recurrence_count : null;
        $this->recurrence_days = array_values(array_map('intval', (array) $this->recurrence_days));
    }

    protected function validateRecurringConfiguration(): bool
    {
        if (! $this->is_recurrent) {
            return true;
        }

        if (! $this->recurrence_frequency) {
            $this->addError('recurrence_frequency', 'Choisissez une fréquence de récurrence.');

            return false;
        }

        if (! $this->recurrence_until && ! $this->recurrence_count) {
            $this->addError('recurrence_count', 'Indiquez une date de fin ou un nombre d’occurrences.');

            return false;
        }

        if ($this->recurrence_frequency === 'weekly' && empty($this->normalizedRecurrenceDays())) {
            $this->addError('recurrence_days', 'Choisissez au moins un jour de passage.');

            return false;
        }

        return true;
    }

    protected function normalizedRecurrenceDays(): array
    {
        if (! $this->is_recurrent || $this->recurrence_frequency !== 'weekly') {
            return [];
        }

        $days = collect($this->recurrence_days)
            ->map(fn ($day) => (int) $day)
            ->filter(fn ($day) => $day >= 1 && $day <= 7)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($days !== []) {
            return $days;
        }

        if ($this->rdvDate) {
            return [Carbon::parse($this->rdvDate)->isoWeekday()];
        }

        return [];
    }

    protected function recurringRuleLabel(): ?string
    {
        if (! $this->is_recurrent || ! $this->recurrence_frequency) {
            return null;
        }

        return match ($this->recurrence_frequency) {
            'daily' => 'daily',
            'weekly' => $this->recurrence_interval === 2 ? 'biweekly' : 'weekly',
            'monthly' => 'monthly',
            default => null,
        };
    }
}
