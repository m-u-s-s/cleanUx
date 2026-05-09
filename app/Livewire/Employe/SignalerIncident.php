<?php

namespace App\Livewire\Employe;

use App\Models\IncidentReport;
use App\Models\Booking;
use App\Support\ActivityLogger;
use Livewire\Component;

class SignalerIncident extends Component
{
    public string $rendezVousId = '';
    public string $type = 'incident';
    public string $priority = 'normale';
    public string $title = '';
    public string $description = '';
    public string $locationNotes = '';
    public string $attachmentInput = '';

    public function getRendezVousOptionsProperty()
    {
        return Booking::query()
            ->with('client')
            ->where('employe_id', auth()->id())
            ->latest('date')
            ->limit(25)
            ->get();
    }

    protected function normalizeAttachments(): array
    {
        return collect(preg_split('/\r\n|\r|\n/', trim($this->attachmentInput)) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->take(5)
            ->map(fn (string $line, int $index) => [
                'label' => 'Preuve '.($index + 1),
                'path' => $line,
            ])
            ->values()
            ->all();
    }

    protected function computeSla(string $priority): array
    {
        return match ($priority) {
            'critique' => ['policy' => '1h', 'due_at' => now()->addHour(), 'severity' => 'critical'],
            'haute' => ['policy' => '8h', 'due_at' => now()->addHours(8), 'severity' => 'high'],
            'faible' => ['policy' => '72h', 'due_at' => now()->addDays(3), 'severity' => 'low'],
            default => ['policy' => '24h', 'due_at' => now()->addDay(), 'severity' => 'medium'],
        };
    }

    public function save(): void
    {
        $data = $this->validate([
            'rendezVousId' => ['nullable', 'exists:rendez_vous,id'],
            'type' => ['required', 'string'],
            'priority' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'locationNotes' => ['nullable', 'string', 'max:255'],
            'attachmentInput' => ['nullable', 'string', 'max:4000'],
        ]);

        $rdv = filled($data['rendezVousId']) ? Booking::with(['client', 'organizationAccount'])->find($data['rendezVousId']) : null;
        $sla = $this->computeSla($data['priority']);

        $incident = IncidentReport::create([
            'rendez_vous_id' => $rdv?->id,
            'employe_id' => auth()->id(),
            'client_id' => $rdv?->client_id,
            'organization_account_id' => $rdv?->organization_account_id,
            'type' => $data['type'],
            'priority' => $data['priority'],
            'sla_policy' => $sla['policy'],
            'severity' => $sla['severity'],
            'status' => 'ouvert',
            'title' => $data['title'],
            'description' => $data['description'],
            'location_notes' => $data['locationNotes'],
            'attachments' => $this->normalizeAttachments(),
            'due_at' => $sla['due_at'],
            'meta' => [
                'source' => 'employe_portal',
                'reported_at' => now()->toDateTimeString(),
            ],
        ]);

        ActivityLogger::log('incident.reported_by_employee', $incident, ['rendez_vous_id' => $rdv?->id]);

        $this->reset(['rendezVousId', 'title', 'description', 'locationNotes', 'attachmentInput']);
        $this->type = 'incident';
        $this->priority = 'normale';
        session()->flash('success', 'Incident signalé.');
    }

    public function render()
    {
        return view('livewire.employe.signaler-incident');
    }
}
