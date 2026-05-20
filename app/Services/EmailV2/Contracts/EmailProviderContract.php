<?php

namespace App\Services\EmailV2\Contracts;

use App\Models\EmailMessage;
use App\Services\EmailV2\EmailSendResult;

interface EmailProviderContract
{
    public function name(): string;

    /**
     * Envoie un email. Soft-fail systématique (jamais throw).
     */
    public function send(EmailMessage $message): EmailSendResult;
}
