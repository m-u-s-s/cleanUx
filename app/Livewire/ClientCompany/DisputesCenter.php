<?php

namespace App\Livewire\ClientCompany;

use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Services\Disputes\DisputeService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

/** Espace société B2B P2 — centre de litiges de l'entreprise cliente. */
class DisputesCenter extends Component
{
    use EnforcesActiveOrgMembership;
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public bool $showForm = false;

    public string $bookingId = '';

    public string $subject = '';

    public string $description = '';

    public string $category = 'quality';

    public function openDispute(): void
    {
        $this->validate([
            'bookingId' => ['required', 'integer'],
            'subject' => ['required', 'string', 'max:191'],
            'description' => ['required', 'string', 'min:5', 'max:2000'],
            'category' => ['required', 'string'],
        ]);

        $orgId = Auth::user()->current_organization_id;

        // Anti-IDOR : le booking doit appartenir à l'organisation active.
        $booking = Booking::query()
            ->where('id', (int) $this->bookingId)
            ->where('customer_organization_id', $orgId)
            ->first();

        if (! $booking) {
            $this->addError('bookingId', 'Réservation introuvable pour votre organisation.');

            return;
        }

        try {
            app(DisputeService::class)->open(Auth::user(), [
                'booking_id' => $booking->id,
                'subject' => $this->subject,
                'description' => $this->description,
                'category' => $this->category,
            ]);
            $this->reset(['bookingId', 'subject', 'description', 'showForm']);
            $this->category = 'quality';
            $this->dispatch('toast', 'Litige ouvert.', 'success');
        } catch (ValidationException $e) {
            $this->addError('bookingId', collect($e->errors())->flatten()->first() ?? 'Échec.');
        }
    }

    public function render(): View
    {
        $orgId = Auth::user()->current_organization_id;

        $disputes = ComplaintCase::query()
            ->where('organization_account_id', $orgId)
            ->with(['booking:id,booking_reference', 'client:id,name'])
            ->latest('last_activity_at')
            ->latest('id')
            ->paginate(10);

        $orgBookings = Booking::query()
            ->where('customer_organization_id', $orgId)
            ->latest('id')
            ->limit(50)
            ->get(['id', 'booking_reference']);

        return view('livewire.client-company.disputes-center', [
            'disputes' => $disputes,
            'orgBookings' => $orgBookings,
        ])->layout('layouts.client-company');
    }
}
