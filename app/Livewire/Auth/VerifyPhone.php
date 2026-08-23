<?php

namespace App\Livewire\Auth;

use App\Services\Sms\PhoneVerificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** OTP téléphone (web) : l'utilisateur saisit/confirme son numéro, reçoit un code SMS, puis le saisit pour vérifier. */
#[Layout('layouts.guest')]
class VerifyPhone extends Component
{
    public string $phone = '';

    public string $code = '';

    public bool $codeSent = false;

    public function mount(): void
    {
        $this->phone = (string) (Auth::user()?->phone ?? '');
    }

    public function sendCode(): void
    {
        $this->validate(['phone' => ['required', 'string', 'max:32']]);

        try {
            app(PhoneVerificationService::class)->sendCode(Auth::user(), $this->phone);
            $this->codeSent = true;
            $this->dispatch('toast', 'Code envoyé par SMS.', 'success');
        } catch (ValidationException $e) {
            $this->addError('phone', collect($e->errors())->flatten()->first() ?? 'Envoi impossible.');
        }
    }

    public function verifyCode()
    {
        $this->validate(['code' => ['required', 'string', 'min:4', 'max:8']]);

        try {
            app(PhoneVerificationService::class)->verify(Auth::user(), $this->code);
        } catch (ValidationException $e) {
            $this->addError('code', collect($e->errors())->flatten()->first() ?? 'Code incorrect.');

            return null;
        }

        return redirect()->intended(config('fortify.home', '/dashboard'));
    }

    public function render()
    {
        return view('livewire.auth.verify-phone');
    }
}
