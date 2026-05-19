<?php

namespace App\Livewire\Provider;

use App\Models\ProviderPayout;
use App\Models\ProviderWalletTransaction;
use App\Services\Payments\ProviderWalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class ProviderWalletPage extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public float $withdrawAmount = 0;
    public ?string $withdrawError = null;
    public ?string $withdrawSuccess = null;

    public function withdraw(): void
    {
        $this->withdrawError = null;
        $this->withdrawSuccess = null;

        $this->validate([
            'withdrawAmount' => ['required', 'numeric', 'min:10'],
        ]);

        try {
            $payout = app(ProviderWalletService::class)
                ->requestWithdraw(Auth::user(), (float) $this->withdrawAmount);

            $this->withdrawSuccess = sprintf(
                'Retrait de %.2f %s demandé (réf #%d). Stripe traite généralement sous 1-2 jours ouvrés.',
                (float) $payout->amount,
                $payout->currency,
                $payout->id,
            );
            $this->withdrawAmount = 0;
        } catch (ValidationException $e) {
            $this->withdrawError = collect($e->errors())->flatten()->first();
        } catch (\Throwable $e) {
            $this->withdrawError = 'Erreur : ' . $e->getMessage();
        }
    }

    public function render(): View
    {
        $userId = (int) Auth::id();
        $service = app(ProviderWalletService::class);

        $balance = $service->balance($userId);

        $transactions = ProviderWalletTransaction::query()
            ->forProvider($userId)
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(20);

        $recentPayouts = ProviderPayout::query()
            ->forProvider($userId)
            ->latest()
            ->limit(5)
            ->get();

        return view('livewire.provider.provider-wallet-page', [
            'balance' => $balance,
            'transactions' => $transactions,
            'recentPayouts' => $recentPayouts,
            'minWithdraw' => ProviderWalletService::MIN_WITHDRAW_AMOUNT,
        ]);
    }
}
