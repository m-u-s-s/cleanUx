<?php

namespace App\Services\EmailV2\Providers;

use App\Models\EmailMessage;
use App\Services\EmailV2\Contracts\EmailProviderContract;
use App\Services\EmailV2\EmailSendResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Mailable;

/**
 * SMTP provider via Laravel Mail facade. Soft-fail.
 * Compatible avec drivers Laravel : smtp | mailgun | ses | postmark | sendgrid.
 */
class SmtpEmailProvider implements EmailProviderContract
{
    public function name(): string
    {
        return 'smtp';
    }

    public function send(EmailMessage $message): EmailSendResult
    {
        try {
            Mail::raw($message->body_text ?? strip_tags((string) $message->body_html), function ($mail) use ($message) {
                $mail->to($message->to_email, $message->to_name);
                $mail->subject($message->subject);
                if ($message->from_email) {
                    $mail->from($message->from_email, $message->from_name);
                }
                if ($message->reply_to) {
                    $mail->replyTo($message->reply_to);
                }
                if ($message->body_html) {
                    $mail->html((string) $message->body_html);
                }
                foreach ((array) ($message->cc ?? []) as $cc) {
                    $mail->cc($cc);
                }
                foreach ((array) ($message->bcc ?? []) as $bcc) {
                    $mail->bcc($bcc);
                }
            });

            return new EmailSendResult(
                success: true,
                provider: 'smtp',
                providerMessageId: null,   // SMTP n'a pas de message ID provider standardisé
            );
        } catch (\Throwable $e) {
            Log::warning('[email_v2] smtp send failed', [
                'message_id' => $message->id, 'error' => $e->getMessage(),
            ]);
            return new EmailSendResult(
                success: false,
                provider: 'smtp',
                error: mb_substr($e->getMessage(), 0, 500),
            );
        }
    }
}
