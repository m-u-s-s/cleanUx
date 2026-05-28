<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema-drift repair for App\Models\FinanceReminder.
 *
 * The model declares reminder_type, channel, recipient_email, error_message
 * and meta (cast array) as fillable but the finance_reminders table is
 * missing the columns. Added defensively with matching types.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('finance_reminders')) {
            return;
        }

        Schema::table('finance_reminders', function (Blueprint $table) {
            if (! Schema::hasColumn('finance_reminders', 'reminder_type')) {
                $table->string('reminder_type')->nullable();
            }
            if (! Schema::hasColumn('finance_reminders', 'channel')) {
                $table->string('channel')->nullable();
            }
            if (! Schema::hasColumn('finance_reminders', 'recipient_email')) {
                $table->string('recipient_email')->nullable();
            }
            if (! Schema::hasColumn('finance_reminders', 'error_message')) {
                $table->text('error_message')->nullable();
            }
            if (! Schema::hasColumn('finance_reminders', 'meta')) {
                $table->json('meta')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('finance_reminders')) {
            return;
        }

        Schema::table('finance_reminders', function (Blueprint $table) {
            foreach ([
                'reminder_type', 'channel', 'recipient_email', 'error_message', 'meta',
            ] as $col) {
                if (Schema::hasColumn('finance_reminders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
