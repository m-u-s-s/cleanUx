<?php

namespace App\Livewire\Admin\Promotions;

use App\Models\PromoCampaign;
use App\Models\PromoCode;
use App\Support\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class PromoCodesCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $search = '';
    public string $filterStatus = '';
    public ?int $filterCampaignId = null;

    public ?int $editingId = null;
    public string $code = '';
    public string $name = '';
    public string $description = '';
    public string $discount_type = PromoCode::TYPE_PERCENT;
    public float $discount_value = 10;
    public ?float $max_discount_amount = null;
    public ?float $min_booking_amount = null;
    public ?int $max_total_uses = null;
    public int $max_uses_per_user = 1;
    public ?string $valid_from = null;
    public ?string $valid_until = null;
    public bool $first_booking_only = false;
    public bool $stackable_with_credits = true;
    public string $audience_scope = PromoCode::SCOPE_ALL;
    public string $status = PromoCode::STATUS_DRAFT;
    public ?int $promo_campaign_id = null;

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[A-Z0-9_-]+$/i'],
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'discount_type' => ['required', 'in:percent,fixed_amount,free_first_booking'],
            'discount_value' => ['required', 'numeric', 'min:0', 'max:100000'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'min_booking_amount' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'max_total_uses' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_user' => ['required', 'integer', 'min:1', 'max:1000'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'first_booking_only' => ['boolean'],
            'stackable_with_credits' => ['boolean'],
            'audience_scope' => ['required', 'in:all,new_customers,returning_customers,b2b,specific_users'],
            'status' => ['required', 'in:draft,active,paused,expired,archived'],
            'promo_campaign_id' => ['nullable', 'integer', 'exists:promo_campaigns,id'],
        ];
    }

    public function save(): void
    {
        $this->code = strtoupper(trim($this->code));

        $this->validate(array_merge($this->rules(), [
            'code' => array_merge($this->rules()['code'], [
                $this->editingId
                    ? 'unique:promo_codes,code,' . $this->editingId
                    : 'unique:promo_codes,code',
            ]),
        ]));

        if ($this->discount_type === PromoCode::TYPE_PERCENT && $this->discount_value > 100) {
            $this->addError('discount_value', 'Une remise en pourcentage ne peut pas excéder 100 %.');
            return;
        }

        $payload = [
            'code' => $this->code,
            'name' => $this->name ?: null,
            'description' => $this->description ?: null,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'max_discount_amount' => $this->max_discount_amount,
            'min_booking_amount' => $this->min_booking_amount,
            'max_total_uses' => $this->max_total_uses,
            'max_uses_per_user' => $this->max_uses_per_user,
            'valid_from' => $this->valid_from ?: null,
            'valid_until' => $this->valid_until ?: null,
            'first_booking_only' => $this->first_booking_only,
            'stackable_with_credits' => $this->stackable_with_credits,
            'audience_scope' => $this->audience_scope,
            'status' => $this->status,
            'promo_campaign_id' => $this->promo_campaign_id,
            'source' => $this->promo_campaign_id ? PromoCode::SOURCE_CAMPAIGN : PromoCode::SOURCE_MANUAL,
            'created_by_user_id' => auth()->id(),
        ];

        if ($this->editingId) {
            $promo = PromoCode::findOrFail($this->editingId);
            $promo->update($payload);
            ActivityLogger::log('promo_code.updated', $promo, ['admin_user_id' => auth()->id()]);
            $this->dispatch('toast', 'Code promo mis à jour.', 'success');
        } else {
            $promo = PromoCode::create($payload);
            ActivityLogger::log('promo_code.created', $promo, ['admin_user_id' => auth()->id()]);
            $this->dispatch('toast', 'Code promo créé.', 'success');
        }

        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $promo = PromoCode::findOrFail($id);
        $this->editingId = $promo->id;
        $this->code = $promo->code;
        $this->name = (string) $promo->name;
        $this->description = (string) $promo->description;
        $this->discount_type = $promo->discount_type;
        $this->discount_value = (float) $promo->discount_value;
        $this->max_discount_amount = $promo->max_discount_amount !== null ? (float) $promo->max_discount_amount : null;
        $this->min_booking_amount = $promo->min_booking_amount !== null ? (float) $promo->min_booking_amount : null;
        $this->max_total_uses = $promo->max_total_uses;
        $this->max_uses_per_user = (int) $promo->max_uses_per_user;
        $this->valid_from = optional($promo->valid_from)->format('Y-m-d\TH:i');
        $this->valid_until = optional($promo->valid_until)->format('Y-m-d\TH:i');
        $this->first_booking_only = (bool) $promo->first_booking_only;
        $this->stackable_with_credits = (bool) $promo->stackable_with_credits;
        $this->audience_scope = $promo->audience_scope;
        $this->status = $promo->status;
        $this->promo_campaign_id = $promo->promo_campaign_id;
    }

    public function archive(int $id): void
    {
        $promo = PromoCode::findOrFail($id);
        $promo->update(['status' => PromoCode::STATUS_ARCHIVED]);
        ActivityLogger::log('promo_code.archived', $promo, ['admin_user_id' => auth()->id()]);
        $this->dispatch('toast', 'Code promo archivé.', 'success');
    }

    public function activate(int $id): void
    {
        $promo = PromoCode::findOrFail($id);
        $promo->update(['status' => PromoCode::STATUS_ACTIVE]);
        ActivityLogger::log('promo_code.activated', $promo, ['admin_user_id' => auth()->id()]);
        $this->dispatch('toast', 'Code promo activé.', 'success');
    }

    public function generateRandomCode(): void
    {
        $this->code = strtoupper(Str::random(8));
    }

    public function resetForm(): void
    {
        $this->reset([
            'editingId', 'code', 'name', 'description',
            'max_discount_amount', 'min_booking_amount',
            'max_total_uses', 'valid_from', 'valid_until',
            'promo_campaign_id',
        ]);

        $this->discount_type = PromoCode::TYPE_PERCENT;
        $this->discount_value = 10;
        $this->max_uses_per_user = 1;
        $this->first_booking_only = false;
        $this->stackable_with_credits = true;
        $this->audience_scope = PromoCode::SCOPE_ALL;
        $this->status = PromoCode::STATUS_DRAFT;
    }

    public function render(): View
    {
        $promos = PromoCode::query()
            ->with(['campaign'])
            ->when($this->search, function ($q) {
                $q->where(function ($inner) {
                    $inner->where('code', 'like', '%' . $this->search . '%')
                        ->orWhere('name', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterCampaignId, fn ($q) => $q->where('promo_campaign_id', $this->filterCampaignId))
            ->latest()
            ->paginate(15);

        return view('livewire.admin.promotions.promo-codes-center', [
            'promos' => $promos,
            'campaigns' => PromoCampaign::query()->orderBy('name')->get(),
        ]);
    }
}
