<?php

namespace App\Livewire\Admin\Badges;

use App\Models\ProviderBadge;
use App\Models\ProviderBadgeAward;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class BadgesCenter extends Component
{
    use EnforcesAdminAccess;
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'catalog';

    public string $search = '';

    public bool $showForm = false;

    public ?int $editBadgeId = null;

    public string $form_code = '';

    public string $form_name = '';

    public string $form_description = '';

    public string $form_icon = '🏆';

    public string $form_tier = 'bronze';

    public string $form_criterion_type = 'missions_count';

    public int $form_threshold = 10;

    public bool $form_is_active = true;

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->reset(['editBadgeId']);
        $this->form_code = 'badge_'.substr(uniqid(), -6);
        $this->form_name = '';
        $this->form_description = '';
        $this->form_icon = '🏆';
        $this->form_tier = 'bronze';
        $this->form_criterion_type = 'missions_count';
        $this->form_threshold = 10;
        $this->form_is_active = true;
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $b = ProviderBadge::findOrFail($id);
        $this->editBadgeId = $b->id;
        $this->form_code = $b->code;
        $this->form_name = $b->name;
        $this->form_description = $b->description ?? '';
        $this->form_icon = $b->icon ?? '🏆';
        $this->form_tier = $b->tier;
        $this->form_criterion_type = $b->criterion_type;
        $this->form_threshold = (int) $b->threshold;
        $this->form_is_active = (bool) $b->is_active;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->reset(['showForm', 'editBadgeId']);
    }

    public function save(): void
    {
        $this->validate([
            'form_code' => ['required', 'string', 'max:64'],
            'form_name' => ['required', 'string', 'max:128'],
            'form_description' => ['nullable', 'string', 'max:500'],
            'form_icon' => ['nullable', 'string', 'max:32'],
            'form_tier' => ['required', 'in:bronze,silver,gold,platinum'],
            'form_criterion_type' => ['required', 'in:missions_count,rating_avg,tips_received,tenure_days,loyalty_points,streak_5stars'],
            'form_threshold' => ['required', 'integer', 'min:1'],
        ]);

        $payload = [
            'code' => $this->form_code,
            'name' => $this->form_name,
            'description' => $this->form_description ?: null,
            'icon' => $this->form_icon ?: null,
            'tier' => $this->form_tier,
            'criterion_type' => $this->form_criterion_type,
            'threshold' => $this->form_threshold,
            'is_active' => $this->form_is_active,
        ];

        if ($this->editBadgeId) {
            ProviderBadge::findOrFail($this->editBadgeId)->update($payload);
        } else {
            ProviderBadge::create($payload);
        }

        $this->closeForm();
        $this->dispatch('toast', 'Badge enregistré.', 'success');
    }

    public function toggleActive(int $id): void
    {
        $b = ProviderBadge::findOrFail($id);
        $b->update(['is_active' => ! $b->is_active]);
        $this->dispatch('toast', 'État mis à jour.', 'success');
    }

    public function render(): View
    {
        $badges = ProviderBadge::query()
            ->when($this->search, function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($w) use ($term) {
                    $w->where('name', 'like', $term)->orWhere('code', 'like', $term);
                });
            })
            ->orderBy('criterion_type')
            ->orderBy('threshold')
            ->paginate(20, ['*'], 'catalogPage');

        $awards = ProviderBadgeAward::query()
            ->with(['provider:id,name,email', 'badge:id,code,name,icon,tier'])
            ->orderByDesc('awarded_at')
            ->paginate(20, ['*'], 'awardsPage');

        $stats = [
            'total_badges' => ProviderBadge::query()->count(),
            'active_badges' => ProviderBadge::query()->where('is_active', true)->count(),
            'total_awards' => ProviderBadgeAward::query()->count(),
            'awards_last_30d' => ProviderBadgeAward::query()->where('awarded_at', '>=', now()->subDays(30))->count(),
        ];

        return view('livewire.admin.badges.badges-center', [
            'badges' => $badges,
            'awards' => $awards,
            'stats' => $stats,
        ]);
    }
}
