<?php

namespace App\Livewire\Client;

use App\Models\Referral;
use App\Models\ReferralReward;
use App\Services\Promotion\ReferralService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Component;

class ReferralProgramPage extends Component
{
    public string $inviteEmail = '';
    public string $inviteMessage = '';
    public ?string $copied = null;

    public function mount(): void
    {
        app(ReferralService::class)->ensureReferralCode(Auth::user());
    }

    public function copyCode(): void
    {
        $this->copied = $this->referralCode();
        $this->dispatch('toast', 'Code copié dans le presse-papier.', 'success');
    }

    public function sendInvitation(): void
    {
        $data = Validator::make(
            ['inviteEmail' => trim($this->inviteEmail)],
            ['inviteEmail' => ['required', 'email', 'max:255']],
            ['inviteEmail.required' => "L'adresse email est obligatoire.",
                'inviteEmail.email' => "Veuillez saisir un email valide."]
        )->validate();

        $code = $this->referralCode();
        $url = url('/register?ref=' . urlencode($code));

        try {
            Mail::raw(
                "Bonjour,\n\n".
                Auth::user()->name." vous invite à essayer CleanUx.\n\n".
                ($this->inviteMessage ? $this->inviteMessage."\n\n" : '').
                "Utilisez ce lien pour vous inscrire et obtenir un crédit de bienvenue :\n".$url."\n\n".
                "Ou votre code de parrainage : ".$code,
                function ($message) use ($data) {
                    $message->to($data['inviteEmail'])
                        ->subject('CleanUx · '.Auth::user()->name." vous invite");
                }
            );

            Referral::create([
                'referrer_user_id' => Auth::id(),
                'referee_email' => $data['inviteEmail'],
                'referral_code' => $code,
                'status' => Referral::STATUS_INVITED,
                'invited_at' => now(),
                'expires_at' => now()->addDays(ReferralService::REFERRAL_EXPIRY_DAYS),
                'currency' => 'EUR',
                'source_channel' => 'email_invite',
                'referrer_reward_amount' => ReferralService::DEFAULT_REFERRER_REWARD,
                'referee_reward_amount' => ReferralService::DEFAULT_REFEREE_REWARD,
            ]);

            $this->reset(['inviteEmail', 'inviteMessage']);
            $this->dispatch('toast', 'Invitation envoyée !', 'success');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', "Impossible d'envoyer l'invitation pour le moment.", 'error');
        }
    }

    public function referralCode(): string
    {
        return (string) (Auth::user()->referral_code ?? '');
    }

    public function render(): View
    {
        $user = Auth::user();
        $stats = app(ReferralService::class)->statsForUser($user);

        $referrals = Referral::query()
            ->forReferrer($user->id)
            ->with(['referee:id,name,email'])
            ->latest()
            ->limit(20)
            ->get();

        $rewards = ReferralReward::query()
            ->where('beneficiary_user_id', $user->id)
            ->where('role', ReferralReward::ROLE_REFERRER)
            ->latest()
            ->limit(20)
            ->get();

        return view('livewire.client.referral-program-page', [
            'stats' => $stats,
            'referrals' => $referrals,
            'rewards' => $rewards,
            'inviteUrl' => url('/register?ref=' . urlencode($this->referralCode())),
        ]);
    }
}
