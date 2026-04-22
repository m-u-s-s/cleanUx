<?php

namespace App\Services\Email;

use App\Models\EmailLog;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Schema;

class EmailLogService
{
    public function available(): bool
    {
        return Schema::hasTable('email_logs');
    }

    public function logPreview(string $templateKey, string $subject, ?string $recipientEmail = null, ?int $previewedBy = null, array $context = []): ?EmailLog
    {
        if (! $this->available()) {
            return null;
        }

        return EmailLog::create([
            'template_key' => $templateKey,
            'subject' => $subject,
            'status' => 'preview',
            'channel' => 'preview',
            'recipient_email' => $recipientEmail,
            'previewed_by_user_id' => $previewedBy,
            'context' => $context,
        ]);
    }

    public function logNotification(string $status, object $notifiable, Notification $notification, string $channel, array $meta = []): ?EmailLog
    {
        if (! $this->available()) {
            return null;
        }

        $message = method_exists($notification, 'toMail') ? $notification->toMail($notifiable) : null;
        $subject = is_object($message) ? ($message->subject ?? class_basename($notification)) : class_basename($notification);

        $templateKey = method_exists($notification, 'toArray')
            ? (string) (data_get($notification->toArray($notifiable), 'template_key') ?: class_basename($notification))
            : class_basename($notification);

        return EmailLog::create([
            'template_key' => $templateKey,
            'subject' => $subject,
            'status' => $status,
            'channel' => $channel,
            'recipient_email' => $notifiable->email ?? null,
            'notifiable_type' => $notifiable::class,
            'notifiable_id' => $notifiable->id ?? null,
            'meta' => $meta,
            'sent_at' => $status === 'sent' ? now() : null,
            'failed_at' => $status === 'failed' ? now() : null,
        ]);
    }
}
