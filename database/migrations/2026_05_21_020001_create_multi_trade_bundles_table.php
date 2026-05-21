<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('multi_trade_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 191);
            $table->text('description')->nullable();

            $table->foreignId('client_user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->enum('status', [
                'draft',        // brouillon client
                'quoting',      // en cours de devis providers
                'quoted',       // devis reçu, en attente accept
                'accepted',     // accepté, missions individuelles à créer
                'in_progress',  // au moins 1 mission démarrée
                'completed',    // toutes missions terminées
                'cancelled',    // bundle annulé
            ])->default('draft');

            // Pricing snapshot
            $table->unsignedInteger('total_estimated_cents')->default(0);
            $table->unsignedInteger('total_quoted_cents')->default(0);
            $table->unsignedInteger('total_final_cents')->default(0);
            $table->string('currency', 3)->default('EUR');

            // Discount pour groupage (% ou montant fixe)
            $table->decimal('bundle_discount_percent', 5, 2)->default(0);
            $table->unsignedInteger('bundle_discount_cents')->default(0);

            $table->json('address')->nullable();
            $table->date('preferred_start_date')->nullable();
            $table->date('preferred_end_date')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['client_user_id', 'status']);
            $table->index('status');
        });

        Schema::create('multi_trade_bundle_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bundle_id')
                ->constrained('multi_trade_bundles')->cascadeOnDelete();

            $table->foreignId('trade_id')
                ->constrained('trades')->cascadeOnDelete();

            $table->string('label', 191);
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->unsignedInteger('estimated_price_cents')->default(0);
            $table->unsignedInteger('quoted_price_cents')->default(0);
            $table->unsignedInteger('sequence_order')->default(0);
            $table->json('depends_on_item_ids')->nullable();   // pour orchestration : carreleur avant peintre

            $table->foreignId('assigned_provider_user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('booking_id')->nullable()
                ->constrained('bookings')->nullOnDelete();

            $table->enum('status', ['draft', 'quoted', 'accepted', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['bundle_id', 'sequence_order']);
            $table->index(['assigned_provider_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('multi_trade_bundle_items');
        Schema::dropIfExists('multi_trade_bundles');
    }
};
