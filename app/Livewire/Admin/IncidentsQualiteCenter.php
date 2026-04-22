<?php

namespace App\Livewire\Admin;

use App\Models\ComplaintCase;
use App\Models\IncidentReport;
use App\Models\QualityAudit;
use App\Models\RendezVous;
use App\Models\ServiceZone;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class IncidentsQualiteCenter extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    public string $tab = 'incidents';
    public string $search = '';
    public string $status = '';
    public string $priority = '';
    public string $category = '';
    public int $selectedId = 0;

    public string $complaintResponse = '';
    public string $resolutionNotes = '';
    public string $assignedTo = '';
    public string $resolutionCategory = '';

    public int $auditRendezVousId = 0;
    public string $auditZoneId = '';
    public string $auditEmployeId = '';
    public int $auditPunctuality = 4;
    public int $auditService = 4;
    public int $auditCommunication = 4;
    public bool $auditFollowUp = false;
    public string $auditStatus = 'publie';
    public string $auditNotes = '';
    public string $auditActionPlan = '';
    public string $auditAttachmentInput = '';

    protected $queryString = ['tab', 'search', 'status', 'priority', 'category', 'page'];

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatus(): void { $this->resetPage(); }
    public function updatingPriority(): void { $this->resetPage(); }
    public function updatingCategory(): void { $this->resetPage(); }
    public function updatingTab(): void { $this->resetPage(); $this->selectedId = 0; }

    public function getManagersProperty()
    {
        return User::query()->where('role', 'admin')->orderBy('name')->get();
    }

    public function getZonesProperty()
    {
        return ServiceZone::query()->orderBy('name')->get();
    }

    public function getEmployesProperty()
    {
        return User::query()->where('role', 'employe')->orderBy('name')->get();
    }

    public function getAuditableRendezVousProperty()
    {
        return RendezVous::query()
            ->with(['client', 'employe', 'serviceZone'])
            ->whereIn('status', ['termine', 'confirme'])
            ->latest('date')
            ->limit(50)
            ->get();
    }

    protected function incidentQuery(): Builder
    {
        return IncidentReport::query()
            ->with(['rendezVous.client', 'employe', 'client', 'organizationAccount', 'assignee'])
            ->when(filled($this->status), fn (Builder $q) => $q->where('status', $this->status))
            ->when(filled($this->priority), fn (Builder $q) => $q->where('priority', $this->priority))
            ->when(filled($this->category), fn (Builder $q) => $q->where('type', $this->category))
            ->when(filled($this->search), function (Builder $q) {
                $term = '%'.$this->search.'%';
                $q->where(function (Builder $sq) use ($term) {
                    $sq->where('title', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhereHas('rendezVous', fn (Builder $s) => $s->where('booking_reference', 'like', $term))
                        ->orWhereHas('client', fn (Builder $s) => $s->where('name', 'like', $term))
                        ->orWhereHas('employe', fn (Builder $s) => $s->where('name', 'like', $term));
                });
            });
    }

    protected function complaintQuery(): Builder
    {
        return ComplaintCase::query()
            ->with(['rendezVous.client', 'client', 'organizationAccount', 'assignee'])
            ->when(filled($this->status), fn (Builder $q) => $q->where('status', $this->status))
            ->when(filled($this->priority), fn (Builder $q) => $q->where('priority', $this->priority))
            ->when(filled($this->category), fn (Builder $q) => $q->where('category', $this->category))
            ->when(filled($this->search), function (Builder $q) {
                $term = '%'.$this->search.'%';
                $q->where(function (Builder $sq) use ($term) {
                    $sq->where('subject', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhereHas('rendezVous', fn (Builder $s) => $s->where('booking_reference', 'like', $term))
                        ->orWhereHas('client', fn (Builder $s) => $s->where('name', 'like', $term));
                });
            });
    }

    protected function auditQuery(): Builder
    {
        return QualityAudit::query()
            ->with(['rendezVous.client', 'employe', 'serviceZone', 'auditor'])
            ->when(filled($this->status), fn (Builder $q) => $q->where('status', $this->status))
            ->when(filled($this->search), function (Builder $q) {
                $term = '%'.$this->search.'%';
                $q->where(function (Builder $sq) use ($term) {
                    $sq->where('notes', 'like', $term)
                        ->orWhere('action_plan', 'like', $term)
                        ->orWhereHas('rendezVous', fn (Builder $s) => $s->where('booking_reference', 'like', $term))
                        ->orWhereHas('employe', fn (Builder $s) => $s->where('name', 'like', $term))
                        ->orWhereHas('serviceZone', fn (Builder $s) => $s->where('name', 'like', $term));
                });
            });
    }

    public function getRowsProperty()
    {
        return match ($this->tab) {
            'complaints' => $this->complaintQuery()->latest()->paginate(10),
            'quality' => $this->auditQuery()->latest('audited_at')->paginate(10),
            default => $this->incidentQuery()->latest()->paginate(10),
        };
    }

    public function getKpisProperty(): array
    {
        $incidents = IncidentReport::query()->get();
        $complaints = ComplaintCase::query()->get();
        $audits = QualityAudit::query()->get();

        return [
            'incidents_open' => $incidents->whereIn('status', ['ouvert', 'en_cours', 'en_attente_client'])->count(),
            'complaints_open' => $complaints->whereIn('status', ['ouvert', 'en_cours', 'en_attente_client'])->count(),
            'critical_open' => $incidents->where('priority', 'critique')->whereNotIn('status', ['resolu', 'ferme'])->count() + $complaints->where('priority', 'critique')->whereNotIn('status', ['resolu', 'ferme'])->count(),
            'quality_avg' => round((float) $audits->avg('score'), 1),
            'follow_up_count' => $audits->where('follow_up_required', true)->count(),
            'sla_overdue' => $incidents->filter->is_overdue->count() + $complaints->filter->is_overdue->count(),
        ];
    }

    protected function normalizeEvidence(string $input): array
    {
        return collect(preg_split('/\r\n|\r|\n/', trim($input)) ?: [])
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->take(5)
            ->map(fn (string $line, int $index) => ['label' => 'Preuve '.($index + 1), 'path' => $line])
            ->values()
            ->all();
    }

    public function selectRow(int $id): void
    {
        $this->selectedId = $id;

        if ($this->tab === 'complaints') {
            $row = ComplaintCase::find($id);
            $this->assignedTo = (string) ($row?->assigned_to ?? '');
            $this->complaintResponse = (string) ($row?->admin_response ?? '');
            $this->resolutionCategory = (string) ($row?->resolution_category ?? '');
            return;
        }

        if ($this->tab === 'incidents') {
            $row = IncidentReport::find($id);
            $this->assignedTo = (string) ($row?->assigned_to ?? '');
            $this->resolutionNotes = (string) ($row?->resolution_notes ?? '');
        }
    }

    public function assignSelected(): void
    {
        if (! $this->selectedId || ! filled($this->assignedTo)) {
            return;
        }

        if ($this->tab === 'complaints') {
            $row = ComplaintCase::findOrFail($this->selectedId);
            $row->update(['assigned_to' => $this->assignedTo]);
            ActivityLogger::log('complaint.assigned', $row, ['assigned_to' => $this->assignedTo]);
        } elseif ($this->tab === 'incidents') {
            $row = IncidentReport::findOrFail($this->selectedId);
            $row->update(['assigned_to' => $this->assignedTo]);
            ActivityLogger::log('incident.assigned', $row, ['assigned_to' => $this->assignedTo]);
        }

        session()->flash('success', 'Assignation enregistrée.');
    }

    public function updateSelectedStatus(string $status): void
    {
        if (! $this->selectedId) {
            return;
        }

        $resolvedAt = in_array($status, ['resolu'], true) ? now() : null;
        $closedAt = $status === 'ferme' ? now() : null;

        if ($this->tab === 'complaints') {
            $row = ComplaintCase::findOrFail($this->selectedId);
            $row->update([
                'status' => $status,
                'resolved_at' => $resolvedAt ?: $row->resolved_at,
                'closed_at' => $closedAt,
            ]);
            ActivityLogger::log('complaint.status_updated', $row, ['status' => $status]);
        } elseif ($this->tab === 'incidents') {
            $row = IncidentReport::findOrFail($this->selectedId);
            $row->update([
                'status' => $status,
                'resolved_at' => $resolvedAt ?: $row->resolved_at,
                'closed_at' => $closedAt,
            ]);
            ActivityLogger::log('incident.status_updated', $row, ['status' => $status]);
        }

        session()->flash('success', 'Statut mis à jour.');
    }

    public function saveComplaintResponse(): void
    {
        $row = ComplaintCase::findOrFail($this->selectedId);
        $row->update([
            'admin_response' => $this->complaintResponse,
            'assigned_to' => filled($this->assignedTo) ? $this->assignedTo : $row->assigned_to,
            'first_response_at' => $row->first_response_at ?? now(),
            'resolution_category' => filled($this->resolutionCategory) ? $this->resolutionCategory : $row->resolution_category,
        ]);

        ActivityLogger::log('complaint.response_saved', $row);
        session()->flash('success', 'Réponse enregistrée.');
    }

    public function saveIncidentResolution(): void
    {
        $row = IncidentReport::findOrFail($this->selectedId);
        $row->update([
            'resolution_notes' => $this->resolutionNotes,
            'assigned_to' => filled($this->assignedTo) ? $this->assignedTo : $row->assigned_to,
            'first_response_at' => $row->first_response_at ?? now(),
        ]);

        ActivityLogger::log('incident.resolution_saved', $row);
        session()->flash('success', 'Résolution enregistrée.');
    }

    public function createAudit(): void
    {
        $this->validate([
            'auditPunctuality' => ['required', 'integer', 'between:1,5'],
            'auditService' => ['required', 'integer', 'between:1,5'],
            'auditCommunication' => ['required', 'integer', 'between:1,5'],
            'auditStatus' => ['required', 'string'],
        ]);

        $rdv = $this->auditRendezVousId ? RendezVous::with(['employe', 'serviceZone'])->find($this->auditRendezVousId) : null;
        $score = (int) round(($this->auditPunctuality + $this->auditService + $this->auditCommunication) / 3);

        $audit = QualityAudit::create([
            'rendez_vous_id' => $rdv?->id,
            'employe_id' => $this->auditEmployeId ?: $rdv?->employe_id,
            'service_zone_id' => $this->auditZoneId ?: $rdv?->service_zone_id,
            'auditor_id' => auth()->id(),
            'score' => $score,
            'punctuality_score' => $this->auditPunctuality,
            'service_score' => $this->auditService,
            'communication_score' => $this->auditCommunication,
            'checklist' => [
                'ponctualite' => $this->auditPunctuality >= 4,
                'service' => $this->auditService >= 4,
                'communication' => $this->auditCommunication >= 4,
            ],
            'attachment_evidence' => $this->normalizeEvidence($this->auditAttachmentInput),
            'notes' => $this->auditNotes,
            'action_plan' => $this->auditActionPlan,
            'follow_up_required' => $this->auditFollowUp,
            'follow_up_due_at' => $this->auditFollowUp ? now()->addDays(7) : null,
            'status' => $this->auditStatus,
            'audited_at' => now(),
        ]);

        ActivityLogger::log('quality.audit_created', $audit, ['score' => $score]);

        $this->reset([
            'auditRendezVousId', 'auditZoneId', 'auditEmployeId', 'auditNotes', 'auditFollowUp', 'auditActionPlan', 'auditAttachmentInput',
        ]);
        $this->auditPunctuality = 4;
        $this->auditService = 4;
        $this->auditCommunication = 4;
        $this->auditStatus = 'publie';
        $this->tab = 'quality';
        session()->flash('success', 'Audit qualité créé.');
    }

    public function render()
    {
        return view('livewire.admin.incidents-qualite-center', [
            'rows' => $this->rows,
            'kpis' => $this->kpis,
            'selectedRow' => $this->selectedId
                ? match ($this->tab) {
                    'complaints' => ComplaintCase::with(['rendezVous.client', 'client', 'organizationAccount', 'assignee'])->find($this->selectedId),
                    'quality' => QualityAudit::with(['rendezVous.client', 'employe', 'serviceZone', 'auditor'])->find($this->selectedId),
                    default => IncidentReport::with(['rendezVous.client', 'client', 'employe', 'organizationAccount', 'assignee'])->find($this->selectedId),
                }
                : null,
        ]);
    }
}
