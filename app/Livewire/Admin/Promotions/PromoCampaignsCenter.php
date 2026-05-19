<?php

namespace App\Livewire\Admin\Promotions;

use App\Models\PromoCampaign;
use App\Support\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class PromoCampaignsCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public ?int $editingId = null;
    public string $name = '';
    public string $slug = '';
    public string $description = '';
    public string $status = PromoCampaign::STATUS_DRAFT;
    public ?string $starts_at = null;
    public ?string $ends_at = null;
    public ?float $budget_cap = null;
    public string $target_audience = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:draft,scheduled,active,paused,archived'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'budget_cap' => ['nullable', 'numeric', 'min:0'],
            'target_audience' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function updatedName(string $value): void
    {
        if (! $this->editingId && empty($this->slug)) {
            $this->slug = Str::slug($value);
        }
    }

    public function save(): void
    {
        $this->validate(array_merge($this->rules(), [
            'slug' => array_merge($this->rules()['slug'], [
                $this->editingId
                    ? 'unique:promo_campaigns,slug,' . $this->editingId
                    : 'unique:promo_campaigns,slug',
            ]),
        ]));

        $payload = [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description ?: null,
            'status' => $this->status,
            'starts_at' => $this->starts_at ?: null,
            'ends_at' => $this->ends_at ?: null,
            'budget_cap' => $this->budget_cap,
            'target_audience' => $this->target_audience ?: null,
            'created_by_user_id' => auth()->id(),
        ];

        if ($this->editingId) {
            $campaign = PromoCampaign::findOrFail($this->editingId);
            $campaign->update($payload);
            ActivityLogger::log('promo_campaign.updated', $campaign, ['admin_user_id' => auth()->id()]);
            $this->dispatch('toast', 'Campagne mise à jour.', 'success');
        } else {
            $campaign = PromoCampaign::create($payload);
            ActivityLogger::log('promo_campaign.created', $campaign, ['admin_user_id' => auth()->id()]);
            $this->dispatch('toast', 'Campagne créée.', 'success');
        }

        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $c = PromoCampaign::findOrFail($id);
        $this->editingId = $c->id;
        $this->name = $c->name;
        $this->slug = $c->slug;
        $this->description = (string) $c->description;
        $this->status = $c->status;
        $this->starts_at = optional($c->starts_at)->format('Y-m-d\TH:i');
        $this->ends_at = optional($c->ends_at)->format('Y-m-d\TH:i');
        $this->budget_cap = $c->budget_cap !== null ? (float) $c->budget_cap : null;
        $this->target_audience = (string) $c->target_audience;
    }

    public function pause(int $id): void
    {
        $c = PromoCampaign::findOrFail($id);
        $c->update(['status' => PromoCampaign::STATUS_PAUSED]);
        ActivityLogger::log('promo_campaign.paused', $c, ['admin_user_id' => auth()->id()]);
        $this->dispatch('toast', 'Campagne en pause.', 'success');
    }

    public function activate(int $id): void
    {
        $c = PromoCampaign::findOrFail($id);
        $c->update(['status' => PromoCampaign::STATUS_ACTIVE]);
        ActivityLogger::log('promo_campaign.activated', $c, ['admin_user_id' => auth()->id()]);
        $this->dispatch('toast', 'Campagne activée.', 'success');
    }

    public function resetForm(): void
    {
        $this->reset([
            'editingId', 'name', 'slug', 'description',
            'starts_at', 'ends_at', 'budget_cap', 'target_audience',
        ]);
        $this->status = PromoCampaign::STATUS_DRAFT;
    }

    public function render(): View
    {
        return view('livewire.admin.promotions.promo-campaigns-center', [
            'campaigns' => PromoCampaign::query()
                ->withCount('promoCodes')
                ->latest()
                ->paginate(15),
        ]);
    }
}
