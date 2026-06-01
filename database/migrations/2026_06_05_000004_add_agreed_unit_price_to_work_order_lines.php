<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('work_order_lines') && ! Schema::hasColumn('work_order_lines', 'agreed_unit_price')) {
            Schema::table('work_order_lines', function (Blueprint $table) {
                $table->decimal('agreed_unit_price', 10, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        // additive
    }
};
