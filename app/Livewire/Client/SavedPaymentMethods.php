<?php

namespace App\Livewire\Client;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Liste des cartes Stripe enregistrées du client.
 * Permet d'ajouter (via SetupIntent), supprimer, et set default.
 */
class SavedPaymentMethods extends Component
{
    public ?string $newCardSetupIntent = null;
    public string $error = '';

    public function startAdd(): void
    {
        $this->error = '';
        $user = Auth::user();
        if (! $user->stripe_id) {
            try {
                $user->createAsStripeCustomer();
            } catch (\Throwable $e) {
                $this->error = $e->getMessage();
                return;
            }
        }

        try {
            $setup = $user->createSetupIntent([
                'payment_method_types' => ['card'],
                'usage' => 'off_session',
            ]);
            $this->newCardSetupIntent = $setup->client_secret;
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function setDefault(string $paymentMethodId): void
    {
        $user = Auth::user();
        try {
            $user->updateDefaultPaymentMethod($paymentMethodId);
            $this->dispatch('toast', 'Carte par défaut mise à jour.', 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur : ' . $e->getMessage(), 'error');
        }
    }

    public function remove(string $paymentMethodId): void
    {
        $user = Auth::user();
        try {
            $pm = $user->findPaymentMethod($paymentMethodId);
            if ($pm) {
                $pm->delete();
                $this->dispatch('toast', 'Carte supprimée.', 'success');
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur : ' . $e->getMessage(), 'error');
        }
    }

    public function render(): View
    {
        $user = Auth::user();
        $methods = [];
        $defaultId = null;

        if ($user->stripe_id) {
            try {
                foreach ($user->paymentMethods() as $pm) {
                    $methods[] = [
                        'id' => $pm->id,
                        'brand' => $pm->card->brand ?? '',
                        'last4' => $pm->card->last4 ?? '',
                        'exp_month' => $pm->card->exp_month ?? null,
                        'exp_year' => $pm->card->exp_year ?? null,
                    ];
                }
                $default = $user->defaultPaymentMethod();
                $defaultId = $default?->id;
            } catch (\Throwable $e) {
                $this->error = $e->getMessage();
            }
        }

        return view('livewire.client.saved-payment-methods', [
            'methods' => $methods,
            'defaultId' => $defaultId,
            'stripeKey' => (string) config('services.stripe.key', ''),
        ])->layout('layouts.app');
    }
}
