<?php

namespace App\Providers;

use App\Http\Controllers\Webhooks\EmailWebhookController;
use App\Services\EmailV2\Contracts\EmailProviderContract;
use App\Services\EmailV2\Providers\MockEmailProvider;
use App\Services\EmailV2\Providers\SmtpEmailProvider;
use App\Services\EmailV2\Webhooks\MailgunVerificateur;
use Illuminate\Support\ServiceProvider;

class EmailV2ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EmailProviderContract::class, function () {
            return match ((string) config('email_v2.provider', 'mock')) {
                'smtp', 'mailgun', 'ses', 'sendgrid' => new SmtpEmailProvider,
                default => new MockEmailProvider,
            };
        });

        /*
         * LES VERIFICATEURS DE SIGNATURE, ET SEULEMENT CEUX QUI EXISTENT.
         *
         * Un fournisseur absent de cette liste voit son point d'entree repondre 404 : c'est
         * volontaire. Accepter une charge non verifiee « en attendant d'ecrire le verificateur »
         * ouvrirait a n'importe qui le droit de declarer qu'un e-mail a ete ouvert ou rejete.
         */
        $this->app->when(EmailWebhookController::class)
            ->needs('$verificateurs')
            ->give(fn () => [new MailgunVerificateur]);
    }
}
