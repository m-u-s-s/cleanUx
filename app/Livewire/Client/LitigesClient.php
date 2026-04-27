<?php

namespace App\Livewire\Client;

use App\Models\CustomerClaim;
use App\Models\RendezVous;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

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
            $rdv = RendezVous::where('client_id', Auth::id())
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

    public function render(): View
    {
        return view('livewire.client.litiges-client', [
            'claims' => CustomerClaim::query()
                ->with('rendezVous')
                ->where('client_id', Auth::id())
                ->when($this->filterStatus, fn ($query) => $query->where('status', $this->filterStatus))
                ->latest()
                ->paginate(8),

            'rendezVous' => RendezVous::query()
                ->where('client_id', Auth::id())
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }
}