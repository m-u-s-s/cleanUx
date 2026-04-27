<?php

namespace App\Services\System;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PlatformReadinessService
{
    public function check(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'tables' => $this->checkTables(),
            'queue' => $this->checkQueue(),
            'mail' => $this->checkMail(),
            'stripe' => $this->checkStripe(),
            'scheduler' => $this->checkScheduler(),
            'features' => $this->checkFeatures(),
        ];
    }

    protected function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function checkTables(): bool
    {
        return Schema::hasTable('users')
            && Schema::hasTable('rendez_vous')
            && Schema::hasTable('missions')
            && Schema::hasTable('finance_invoices');
    }

    protected function checkQueue(): bool
    {
        return config('queue.default') !== 'sync';
    }

    protected function checkMail(): bool
    {
        return config('mail.default') !== null;
    }

    protected function checkStripe(): bool
    {
        return config('services.stripe.key')
            && config('services.stripe.secret');
    }

    protected function checkScheduler(): bool
    {
        return file_exists(base_path('app/Console/Kernel.php'));
    }

    protected function checkFeatures(): array
    {
        return [
            'dispatch' => class_exists(\App\Services\Dispatch\AiDispatchService::class),
            'notifications' => class_exists(\App\Services\Notifications\SmartNotificationService::class),
            'b2b_invoice' => class_exists(\App\Services\Finance\B2BMonthlyInvoiceService::class),
            'workflow' => class_exists(\App\Services\Enterprise\EnterpriseBookingApprovalService::class),
        ];
    }
}