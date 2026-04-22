<?php

namespace App\Livewire\Admin;

use App\Models\EmailLog;
use App\Services\Email\EmailLogService;
use App\Services\Email\ProductEmailTemplates;
use Livewire\Component;

class ProductEmailsCenter extends Component
{
    public string $templateKey = 'booking_confirmed';
    public string $recipientName = 'Client Démo';
    public string $recipientEmail = 'client@example.test';
    public string $previewHtml = '';
    public string $subject = '';

    public function mount(): void
    {
        $this->generatePreview(false);
    }

    public function updatedTemplateKey(): void
    {
        $this->generatePreview(false);
    }

    public function generatePreview(bool $log = true): void
    {
        $payload = ProductEmailTemplates::payload($this->templateKey);
        $this->subject = (string) ($payload['subject'] ?? 'Notification CleanUx');
        $this->previewHtml = view('emails.product.generic', [
            'eyebrow' => $payload['eyebrow'] ?? 'CleanUx',
            'title' => $payload['title'] ?? $this->subject,
            'intro' => $payload['intro'] ?? null,
            'details' => $payload['details'] ?? [],
            'highlight' => $payload['highlight'] ?? null,
            'actionText' => $payload['action_text'] ?? null,
            'actionUrl' => $payload['action_url'] ?? null,
            'outro' => $payload['outro'] ?? null,
            'tone' => $payload['tone'] ?? 'info',
            'footer' => $payload['footer'] ?? 'CleanUx — plateforme de gestion des interventions.',
        ])->render();

        if ($log) {
            app(EmailLogService::class)->logPreview(
                $this->templateKey,
                $this->subject,
                $this->recipientEmail,
                auth()->id(),
                ['recipient_name' => $this->recipientName]
            );

            $this->dispatch('toast', 'Aperçu email généré.', 'success');
        }
    }

    public function getRecentLogsProperty()
    {
        if (! app(EmailLogService::class)->available()) {
            return collect();
        }

        return EmailLog::latest()->limit(8)->get();
    }

    public function render()
    {
        return view('livewire.admin.product-emails-center', [
            'templates' => ProductEmailTemplates::definitions(),
            'recentLogs' => $this->recentLogs,
        ]);
    }
}
