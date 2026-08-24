<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Audit HIGH — avoirs (credit notes). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('credit_note_number', 40)->unique();

            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->unsignedBigInteger('finance_invoice_id')->nullable();
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('organization_account_id')->nullable();

            $table->decimal('amount', 12, 2)->default(0);       // montant TTC remboursé
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('EUR');

            $table->string('reason')->nullable();
            $table->string('stripe_refund_id')->nullable()->unique();

            $table->timestamp('issued_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'issued_at']);
            $table->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_credit_notes');
    }
};
