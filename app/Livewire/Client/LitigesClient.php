<?php

namespace App\Livewire\Client;

use App\Models\ComplaintCase;
use App\Models\RendezVous;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class LitigesClient extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    public string $rendezVousId = '';
    public string $category = 'reclamation';
    public string $priority = 'normale';
    public string $subject = '';
    public string $description = '';
    public string $attachmentInput = '';
    public string $status = '';

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function getRendezVousOptionsProperty()
    {
        return RendezVous::query()
            ->with(['organizationAccount'])
            ->where('client_id', auth()->id())
            ->latest('date')
            ->limit(50)
            ->get();
    }

    protected function casesQuery(): Builder
    {
        return ComplaintCase::query()
            ->with(['rendezVous', 'organizationAccount', 'assignee'])
            ->where('client_id', auth()->id())
            ->when(filled($this->status), fn (Builder $q) => $q->where('status', $this->status));
    }

    protected function normalizeAttachments(): array
    {
        return collect(preg_split('/\r\n|\r|\n/', trim($this->attachmentInput)) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->take(5)
            ->map(fn (string $line, int $index) => [
                'label' => 'Pièce jointe '.($index + 1),
                'path' => $line,
            ])
            ->values()
            ->all();
    }

    protected function computeSla(string $priority): array
    {
        return match ($priority) {
            'critique' => ['policy' => '4h', 'due_at' => now()->addHours(4)],
            'haute' => ['policy' => '24h', 'due_at' => now()->addDay()],
            'faible' => ['policy' => '5j', 'due_at' => now()->addDays(5)],
            default => ['policy' => '72h', 'due_at' => now()->addDays(3)],
        };
    }

    public function save(): void
    {
        $data = $this->validate([
            'rendezVousId' => ['nullable', 'exists:rendez_vous,id'],
            'category' => ['required', 'string'],
            'priority' => ['required', 'string'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'attachmentInput' => ['nullable', 'string', 'max:4000'],
        ]);

        $rdv = filled($data['rendezVousId'])
            ? RendezVous::query()->where('client_id', auth()->id())->find($data['rendezVousId'])
            : null;

        $sla = $this->computeSla($data['priority']);

        $case = ComplaintCase::create([
            'rendez_vous_id' => $rdv?->id,
            'client_id' => auth()->id(),
            'organization_account_id' => $rdv?->organization_account_id,
            'category' => $data['category'],
            'priority' => $data['priority'],
            'sla_policy' => $sla['policy'],
            'status' => 'ouvert',
            'subject' => $data['subject'],
            'description' => $data['description'],
            'attachments' => $this->normalizeAttachments(),
            'due_at' => $sla['due_at'],
            'meta' => [
                'source' => 'client_portal',
                'booking_reference' => $rdv?->booking_reference,
            ],
        ]);

        ActivityLogger::log('complaint.created_by_client', $case, ['rendez_vous_id' => $rdv?->id]);

        $this->reset(['rendezVousId', 'subject', 'description', 'attachmentInput']);
        $this->category = 'reclamation';
        $this->priority = 'normale';
        session()->flash('success', 'Demande envoyée.');
    }

    public function render()
    {
        return view('livewire.client.litiges-client', [
            'cases' => $this->casesQuery()->latest()->paginate(10),
        ]);
    }
}
