<?php

namespace App\Livewire\Client;

use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Models\CustomerClaim;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class LitigesClient extends Component
{
    use WithFileUploads;
    use WithPagination;

    public ?int $rendez_vous_id = null;

    public string $category = 'quality';

    public string $priority = 'normal';

    public string $title = '';

    public string $description = '';

    public array $photos = [];

    public string $filterStatus = '';

    public string $subject = '';

    public string $attachmentInput = '';

    // SECURITY/UX : panel detail expected by the view (@if($selected)).
    // Currently no selection logic implemented — values stay null so panel is skipped.
    /** Le corps de la réponse en cours de saisie — `wire:model` de la zone de texte. */
    public string $replyBody = '';

    public ?int $selectedId = null;

    public mixed $selected = null;

    protected $paginationTheme = 'tailwind';

    public function rules(): array
    {
        return [
            // La colonne garde son nom hérité, mais la réservation qu'elle désigne vit dans
            // `bookings` : la valider contre le miroir faisait refuser une réservation bien réelle
            // dès que sa recopie avait échoué — ce qui arrivait sans un mot.
            'rendez_vous_id' => ['nullable', 'exists:bookings,id'],
            'category' => ['required', 'string'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'photos.*' => ['nullable', 'image', 'max:4096'],
        ];
    }

    public function createClaim(): void
    {
        $this->validate();

        if ($this->rendez_vous_id) {
            $rdv = Booking::where('client_id', Auth::id())
                ->whereKey($this->rendez_vous_id)
                ->firstOrFail();
        }

        $attachments = [];

        foreach ($this->photos as $photo) {
            $attachments[] = [
                'path' => $photo->store('claims', 'private'),
                'original_name' => $photo->getClientOriginalName(),
            ];
        }

        CustomerClaim::create([
            'client_id' => Auth::id(),
            'rendez_vous_id' => $this->rendez_vous_id,
            'category' => $this->category,
            'priority' => $this->priority,
            'status' => 'open',
            'title' => $this->title,
            'description' => $this->description,
            'attachments' => $attachments,
            'sla_due_at' => $this->calculateSlaDueAt(),
        ]);

        $this->reset([
            'rendez_vous_id',
            'category',
            'priority',
            'title',
            'description',
            'photos',
        ]);

        $this->category = 'quality';
        $this->priority = 'normal';

        $this->dispatch('toast', 'Votre litige a été envoyé au support.', 'success');
    }

    protected function calculateSlaDueAt()
    {
        return match ($this->priority) {
            'urgent' => now()->addHours(4),
            'high' => now()->addHours(12),
            'normal' => now()->addDay(),
            default => now()->addDays(3),
        };
    }

    /** OUVRIR UNE RÉCLAMATION — et seulement l'une des siennes. */
    public function select(int $claimId): void
    {
        $claim = CustomerClaim::query()
            ->where(fn ($q) => $q->where('client_id', Auth::id())
                ->orWhere('customer_user_id', Auth::id()))
            ->with(['events.author:id,name'])
            ->find($claimId);

        if (! $claim) {
            $this->selectedId = null;
            $this->selected = null;
            $this->dispatch('toast', __("Cette réclamation n'existe pas ou ne vous appartient pas."), 'error');

            return;
        }

        $this->selectedId = $claim->id;
        $this->selected = $claim;
        $this->replyBody = '';
    }

    /** RÉPONDRE À SA RÉCLAMATION. */
    public function postReply(): void
    {
        $this->validate([
            'replyBody' => ['required', 'string', 'min:2', 'max:2000'],
        ], [], ['replyBody' => __('réponse')]);

        $claim = CustomerClaim::query()
            ->where(fn ($q) => $q->where('client_id', Auth::id())
                ->orWhere('customer_user_id', Auth::id()))
            ->find($this->selectedId);

        if (! $claim) {
            $this->dispatch('toast', __('Réclamation introuvable.'), 'error');

            return;
        }

        if (in_array($claim->status, ['resolved', 'closed'], true)) {
            $this->dispatch('toast', __('Cette réclamation est clôturée : elle n’accepte plus de réponse.'), 'error');

            return;
        }

        $claim->events()->create([
            'author_role' => 'client',
            'author_user_id' => Auth::id(),
            'body' => trim($this->replyBody),
        ]);

        $this->replyBody = '';
        $this->selected = $claim->fresh(['events.author:id,name']);

        $this->dispatch('toast', __('Votre réponse a été envoyée.'), 'success');
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'subject' => ['required', 'string', 'min:3', 'max:120'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'priority' => ['required', 'string'],
            'attachmentInput' => ['nullable', 'string', 'max:4000'],
        ]);

        $priority = match ($this->priority) {
            'critique', 'critical', 'urgent' => 'urgent',
            'haute', 'high' => 'high',
            'basse', 'low' => 'low',
            default => 'normal',
        };

        $slaPolicy = match ($priority) {
            'urgent' => '4h',
            'high' => '24h',
            'normal' => '48h',
            default => '72h',
        };

        $dueAt = match ($priority) {
            'urgent' => now()->addHours(4),
            'high' => now()->addDay(),
            'normal' => now()->addDays(2),
            default => now()->addDays(3),
        };

        $attachments = collect(preg_split('/\R+/', trim($this->attachmentInput)))
            ->filter()
            ->values()
            ->map(fn ($value) => [
                'path' => $value,
                'original_name' => basename($value),
            ])
            ->all();

        ComplaintCase::create([
            'client_id' => Auth::id(),
            'category' => $this->category ?: 'quality',
            'priority' => $priority,
            'sla_policy' => $slaPolicy,
            'status' => 'open',
            'subject' => $validated['subject'],
            'description' => $validated['description'],
            'attachments' => $attachments,
            'due_at' => $dueAt,
        ]);

        $this->reset(['subject', 'description', 'attachmentInput']);
        $this->priority = 'normal';

        $this->dispatch('toast', 'Votre litige a été envoyé au support.', 'success');
    }

    public function render(): View
    {
        return view('livewire.client.litiges-client', [
            // LES DEUX COLONNES DE CLIENT, PARCE QUE LES DEUX EXISTENT.
            'claims' => CustomerClaim::query()
                ->with('rendezVous')
                ->where(fn ($q) => $q->where('client_id', Auth::id())
                    ->orWhere('customer_user_id', Auth::id()))
                ->when($this->filterStatus, fn ($query) => $query->where('status', $this->filterStatus))
                ->latest()
                ->paginate(8),

            'rendezVous' => Booking::query()
                ->where('client_id', Auth::id())
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }
}
