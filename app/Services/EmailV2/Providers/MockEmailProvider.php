<?php

namespace App\Services\EmailV2\Providers;

use App\Models\EmailMessage;
use App\Services\EmailV2\Contracts\EmailProviderContract;
use App\Services\EmailV2\EmailSendResult;
use Illuminate\Support\Str;

/**
 * Mock provider — utilisé en CI/dev/staging. Toujours succès sauf si l'email
 * destinataire contient 'fail@' (pour tester scenario erreur).
 */
class MockEmailProvider implements EmailProviderContract
{
    public function name(): string
    {
        return 'mock';
    }

    public function send(EmailMessage $message): EmailSendResult
    {
        if (str_contains($message->to_email, 'fail@') || str_contains($message->to_email, 'bounce@')) {
            return new EmailSendResult(
                success: false,
                provider: 'mock',
                error: 'mock_forced_failure',
            );
        }
        return new EmailSendResult(
            success: true,
            provider: 'mock',
            providerMessageId: 'mock_msg_' . Str::lower(Str::random(20)),
        );
    }
}
