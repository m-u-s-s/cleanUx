<?php

namespace App\Services\EmailV2;

use App\Models\EmailMessage;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\EmailV2\Contracts\EmailProviderContract;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class EmailService
{
    public function __construct(protected EmailProviderContract $provider) {}

    /**
     * Envoie un email. Persiste un ledger row + appelle provider.
     *
     * @param array{
     *   to_email: string,
     *   to_name?: ?string,
     *   to_user_id?: ?int,
     *   subject: string,
     *   body_html?: ?string,
     *   body_text?: ?string,
     *   from_email?: ?string,
     *   from_name?: ?string,
     *   reply_to?: ?string,
     *   cc?: ?array,
     *   bcc?: ?array,
     *   attachments?: ?array,
     *   headers?: ?array,
     *   category?: string,
     *   template_code?: ?string,
     *   locale?: ?string,
     *   idempotency_key?: ?string,
     *   metadata?: ?array,
     * } $payload
     */
    public function send(array $payload): ?EmailMessage
    {
        if (! Schema::hasTable('email_messages')) {
            return null;
        }
        if (! (bool) config('email_v2.enabled', true)) {
            return null;
        }

        $this->validateRequired($payload);

        $category = (string) ($payload['category'] ?? EmailMessage::CATEGORY_TRANSACTIONAL);
        $allowed = (array) config('email_v2.allowed_categories', []);
        if (! empty($allowed) && ! in_array($category, $allowed, true)) {
            throw ValidationException::withMessages(['category' => ['Catégorie invalide.']]);
        }

        // Idempotency check
        if (! empty($payload['idempotency_key'])) {
            $existing = EmailMessage::query()
                ->where('idempotency_key', $payload['idempotency_key'])
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        // Opt-out check (marketing only)
        if ($category === EmailMessage::CATEGORY_MARKETING
            && (bool) config('email_v2.check_opt_outs', true)
            && $this->isOptedOut($payload['to_email'])) {
            Log::info('[email_v2] skipping marketing email (opted out)', ['to' => $payload['to_email']]);
            return null;
        }

        // Rate limit check
        if ($this->isRateLimited($payload['to_email'])) {
            Log::info('[email_v2] rate limit hit', ['to' => $payload['to_email']]);
            return null;
        }

        $defaults = (array) config('email_v2.from_default', []);
        $message = DB::transaction(function () use ($payload, $category, $defaults) {
            return EmailMessage::query()->create([
                'code' => EmailMessage::generateCode(),
                'provider' => $this->provider->name(),
                'to_email' => $payload['to_email'],
                'to_name' => $payload['to_name'] ?? null,
                'to_user_id' => $payload['to_user_id'] ?? null,
                'from_email' => $payload['from_email'] ?? ($defaults['email'] ?? 'noreply@cleanux.com'),
                'from_name' => $payload['from_name'] ?? ($defaults['name'] ?? 'CleanUx'),
                'reply_to' => $payload['reply_to'] ?? config('email_v2.reply_to_default'),
                'subject' => mb_substr((string) $payload['subject'], 0, 500),
                'body_html' => $payload['body_html'] ?? null,
                'body_text' => $payload['body_text'] ?? null,
                'cc' => $payload['cc'] ?? null,
                'bcc' => $payload['bcc'] ?? null,
                'attachments' => $payload['attachments'] ?? null,
                'headers' => $payload['headers'] ?? null,
                'category' => $category,
                'template_code' => $payload['template_code'] ?? null,
                'locale' => $payload['locale'] ?? null,
                'status' => EmailMessage::STATUS_QUEUED,
                'queued_at' => now(),
                'idempotency_key' => $payload['idempotency_key'] ?? null,
                'metadata' => $payload['metadata'] ?? null,
            ]);
        });

        $this->dispatch($message);
        return $message->fresh();
    }

    public function dispatch(EmailMessage $message): EmailMessage
    {
        if ($message->isTerminal()) {
            return $message;
        }

        $message->increment('attempts');
        $result = $this->provider->send($message);

        if ($result->success) {
            $message->update([
                'status' => EmailMessage::STATUS_SENT,
                'sent_at' => now(),
                'provider_message_id' => $result->providerMessageId,
                'last_error' => null,
            ]);
        } else {
            $message->update([
                'status' => EmailMessage::STATUS_FAILED,
                'last_error' => $result->error,
            ]);
        }
        return $message->fresh();
    }

    public function renderFromTemplate(string $templateCode, array $variables, ?string $locale = null): array
    {
        $tpl = EmailTemplate::query()->where('code', $templateCode)->active()->first();
        if (! $tpl) {
            throw ValidationException::withMessages(['template_code' => ["Template '{$templateCode}' introuvable."]]);
        }
        $subject = $tpl->subjectForLocale($locale);
        $html = $tpl->bodyHtmlForLocale($locale);

        // Whitelist-based variable substitution (anti-injection)
        $allowed = (array) ($tpl->required_variables ?? []);
        foreach ($variables as $key => $value) {
            $keyClean = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $key);
            if (empty($allowed) || in_array($keyClean, $allowed, true)) {
                $needle = '{{' . $keyClean . '}}';
                $subject = str_replace($needle, (string) $value, $subject);
                $html = str_replace($needle, (string) $value, $html);
            }
        }
        return [
            'subject' => $subject,
            'body_html' => $html,
            'body_text' => strip_tags($html),
            'template_code' => $templateCode,
            'locale' => $locale,
        ];
    }

    protected function validateRequired(array $payload): void
    {
        foreach (['to_email', 'subject'] as $field) {
            if (empty($payload[$field])) {
                throw ValidationException::withMessages([$field => ["Champ {$field} requis."]]);
            }
        }
        if (! filter_var($payload['to_email'], FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['to_email' => ['Email invalide.']]);
        }
    }

    protected function isOptedOut(string $email): bool
    {
        if (Schema::hasTable('marketing_opt_outs')) {
            return DB::table('marketing_opt_outs')->where('email', $email)->exists();
        }
        return false;
    }

    protected function isRateLimited(string $email): bool
    {
        $hourLimit = (int) config('email_v2.rate_limit_per_recipient_per_hour', 20);
        $dayLimit = (int) config('email_v2.rate_limit_per_recipient_per_day', 100);
        if ($hourLimit <= 0 && $dayLimit <= 0) {
            return false;
        }
        $now = now();
        if ($hourLimit > 0) {
            $countHour = EmailMessage::query()
                ->forRecipient($email)
                ->where('created_at', '>=', $now->copy()->subHour())
                ->count();
            if ($countHour >= $hourLimit) {
                return true;
            }
        }
        if ($dayLimit > 0) {
            $countDay = EmailMessage::query()
                ->forRecipient($email)
                ->where('created_at', '>=', $now->copy()->subDay())
                ->count();
            if ($countDay >= $dayLimit) {
                return true;
            }
        }
        return false;
    }
}
