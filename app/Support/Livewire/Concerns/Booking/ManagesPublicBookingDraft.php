<?php

namespace App\Support\Livewire\Concerns\Booking;

use Illuminate\Support\Facades\Auth;

trait ManagesPublicBookingDraft
{
        protected function restorePublicBookingDraft(): void
        {
            if (! Auth::check() || ! request()->boolean('resume')) {
                return;
            }

            $draft = session()->pull(self::PUBLIC_BOOKING_DRAFT_SESSION_KEY);

            if (! is_array($draft) || $draft === []) {
                return;
            }

            foreach ($this->bookingDraftFields() as $field) {
                if (array_key_exists($field, $draft)) {
                    $this->{$field} = $draft[$field];
                }
            }

            $this->step = max(1, min(5, (int) ($draft['step'] ?? 5)));

            session()->flash('info', 'Votre demande a été restaurée. Vous pouvez maintenant la confirmer.');
        }

        protected function persistPublicBookingDraft(): void
        {
            session([
                self::PUBLIC_BOOKING_DRAFT_SESSION_KEY => array_merge(
                    ['step' => max($this->step, 5)],
                    collect($this->bookingDraftFields())
                        ->mapWithKeys(fn (string $field) => [$field => $this->{$field}])
                        ->all(),
                ),
            ]);
        }

        protected function clearPublicBookingDraft(): void
        {
            session()->forget(self::PUBLIC_BOOKING_DRAFT_SESSION_KEY);
        }

        protected function bookingDraftFields(): array
        {
            return [
                'selected_service_identifier',
                'type_lieu',
                'frequence',
                'surface',
                'options_prestation',
                'zones_specifiques',
                'materiel_specifique',
                'commentaire_client',
                'presence_animaux',
                'acces_parking',
                'materiel_fournit',
                'adresse',
                'ville',
                'code_postal',
                'postal_code_input',
                'telephone_client',
                'priorite',
                'organization_site_id',
                'site_contact_name',
                'site_contact_phone',
                'purchase_order_reference',
                'cost_center',
                'site_instructions',
                'employe_id',
                'rdvDate',
                'rdvHeure',
                'is_recurrent',
                'recurrence_rule',
                'recurrence_frequency',
                'recurrence_interval',
                'recurrence_until',
                'recurrence_count',
                'recurrence_days',
                'is_favorite_slot',
            ];
        }
}
