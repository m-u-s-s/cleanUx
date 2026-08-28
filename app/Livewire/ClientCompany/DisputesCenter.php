<?php

namespace App\Livewire\ClientCompany;

use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Models\DisputeEvent;
use App\Services\Disputes\DisputeService;
use App\Support\Disputes\PreuvesDeLitige;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/** Espace société B2B P2 — centre de litiges de l'entreprise cliente. */
class DisputesCenter extends Component
{
    use EnforcesActiveOrgMembership;
    use WithFileUploads;
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public bool $showForm = false;

    // Verrouillee : le navigateur peut retourner une propriete publique par `$set`, et la
    // garde d'organisation ne tiendrait plus qu'a l'appel de `select()`.
    #[Locked]
    public ?int $selectedId = null;

    public string $responseBody = '';

    /**
     * Les preuves jointes a une reponse.
     *
     * @var list<UploadedFile>
     */
    public array $reponsePreuves = [];

    public string $bookingId = '';

    public string $subject = '';

    public string $description = '';

    public string $category = 'quality';

    /**
     * Les preuves jointes a l'ouverture du litige
     *
     * @var list<UploadedFile>
     */
    public array $preuves = [];

    public function openDispute(): void
    {
        $this->validate([
            'bookingId' => ['required', 'integer'],
            'subject' => ['required', 'string', 'max:191'],
            'description' => ['required', 'string', 'min:5', 'max:2000'],
            'category' => ['required', 'string'],
        ] + PreuvesDeLitige::regles('preuves'));

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
                'attachments' => PreuvesDeLitige::stocker($this->preuves),
            ]);
            $this->reset(['bookingId', 'subject', 'description', 'showForm', 'preuves']);
            $this->category = 'quality';
            $this->dispatch('toast', 'Litige ouvert.', 'success');
        } catch (ValidationException $e) {
            $this->addError('bookingId', collect($e->errors())->flatten()->first() ?? 'Échec.');
        }
    }

    /** Le litige demande, s'il appartient bien a l'organisation active. */
    public function select(int $id): void
    {
        $litige = $this->litigeDeLOrganisation($id);

        if (! $litige) {
            $this->selectedId = null;
            $this->dispatch('toast', 'Litige introuvable pour votre organisation.', 'error');

            return;
        }

        $this->selectedId = $litige->id;
        $this->reset(['responseBody', 'reponsePreuves']);
    }

    /** Repondre au support, preuves comprises — la societe en joint deja a l'ouverture. */
    public function postResponse(): void
    {
        $this->validate([
            'responseBody' => ['required', 'string', 'min:2', 'max:2000'],
        ] + PreuvesDeLitige::regles('reponsePreuves'));

        $litige = $this->litigeDeLOrganisation($this->selectedId);

        if (! $litige) {
            $this->dispatch('toast', 'Litige introuvable pour votre organisation.', 'error');

            return;
        }

        try {
            app(DisputeService::class)->addMessage(
                $litige,
                Auth::user(),
                DisputeEvent::ROLE_CLIENT,
                trim($this->responseBody),
                DisputeEvent::VISIBILITY_ALL,
                PreuvesDeLitige::stocker($this->reponsePreuves),
            );
            $this->reset(['responseBody', 'reponsePreuves']);
            $this->dispatch('toast', 'Reponse envoyee au support.', 'success');
        } catch (ValidationException $e) {
            $this->addError('responseBody', collect($e->errors())->flatten()->first() ?? 'Echec.');
        }
    }

    /** LA garde anti-IDOR, ecrite une fois : trois appelants s'en servent. */
    private function litigeDeLOrganisation(?int $id): ?ComplaintCase
    {
        if (! $id) {
            return null;
        }

        return ComplaintCase::query()
            ->where('organization_account_id', Auth::user()->current_organization_id)
            ->with([
                'booking:id,booking_reference',
                'events' => fn ($q) => $q->visibleTo(DisputeEvent::ROLE_CLIENT)->orderBy('created_at'),
                'events.author:id,name',
            ])
            ->find($id);
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
            // Rechargee a chaque rendu, et re-portee : la garde ne tient pas au seul `select()`.
            'selected' => $this->litigeDeLOrganisation($this->selectedId),
        ])->layout('layouts.client-company');
    }
}
