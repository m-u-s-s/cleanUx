<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_overrides', function (Blueprint $table) {
            $table->id();

            $table->string('locale', 8);
            $table->string('group', 64)->default('*');
            $table->string('key', 255);

            $table->text('value');

            $table->string('namespace', 64)->default('*');
            $table->boolean('is_published')->default(true);

            $table->foreignId('updated_by_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(['locale', 'group', 'key', 'namespace'], 'tx_overrides_unique');
            $table->index(['locale', 'group']);
            $table->index('is_published');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_overrides');
    }
};
