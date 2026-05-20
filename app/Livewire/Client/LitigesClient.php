<?php

namespace App\Livewire\Client;

use App\Models\CustomerClaim;
use App\Models\Booking;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use App\Models\ComplaintCase;

class LitigesClient extends Component
{
    use WithPagination;
    use WithFileUploads;

    public ?int $rendez_vous_id = null;
    public string $category = 'quality';
    public string $priority = 'normal';
    public string $title = '';
    public string $description = '';
    public array $photos = [];

    public string $filterStatus = '';
    public string $subject = '';
    public string $attachmentInput = '';

    protected $paginationTheme = 'tailwind';

    public function rules(): array
    {
        return [
            'rendez_vous_id' => ['nullable', 'exists:rendez_vous,id'],
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
                'path' => $photo->store('claims', 'public'),
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
            ->map(fn($value) => [
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
            'claims' => CustomerClaim::query()
                ->with('rendezVous')
                ->where('client_id', Auth::id())
                ->when($this->filterStatus, fn($query) => $query->where('status', $this->filterStatus))
                ->latest()
                ->paginate(8),

            'rendezVous' => Booking::query()
                ->where('client_id', Auth::id())
                ->latest()
                ->limit(20)
                ->get(),

            // Selection state expected by the view's detail panel (@if($selected)).
            // This component does not yet implement claim selection — values are null
            // so the panel block is skipped entirely.
            'selected' => null,
            'selectedId' => null,
        ]);
    }
}
