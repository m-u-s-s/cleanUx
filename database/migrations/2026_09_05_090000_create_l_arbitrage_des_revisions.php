<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** L'ARBITRAGE — savoir qui triche, sans jamais punir sur une seule mission. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mission_dispute_signals')) {
            Schema::create('mission_dispute_signals', function (Blueprint $table) {
                $table->id();

                $table->foreignId('mission_id')->constrained()->cascadeOnDelete();
                $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('quote_revision_id')->nullable();
                $table->unsignedBigInteger('cancellation_id')->nullable();

                // Les deux contreparties, sur la même ligne : c'est ce qui rend la concordance
                // interrogeable en une requête.
                $table->foreignId('provider_user_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('client_user_id')->constrained('users')->restrictOnDelete();

                $table->string('signal_code', 48);
                $table->string('charged_side', 16);
                $table->string('outcome', 24);
                $table->json('evidence')->nullable();

                $table->string('verdict', 24)->nullable();
                $table->timestamp('verdict_at')->nullable();
                $table->unsignedBigInteger('reviewed_by_user_id')->nullable();

                $table->timestamps();

                $table->index(['provider_user_id', 'created_at'], 'mds_prestataire_date_index');
                $table->index(['client_user_id', 'created_at'], 'mds_client_date_index');
                $table->index(['provider_user_id', 'client_user_id'], 'mds_couple_index');
            });
        }

        if (! Schema::hasTable('mission_feature_suspensions')) {
            Schema::create('mission_feature_suspensions', function (Blueprint $table) {
                $table->id();

                $table->foreignId('user_id')->constrained()->cascadeOnDelete();

                // `quote_revision` ou `ordering` : la même table sert les deux côtés, parce que la
                // question posée est la même — « cette personne a-t-elle encore le droit de ? ».
                $table->string('feature', 48);
                $table->unsignedTinyInteger('level')->default(1);

                $table->timestamp('starts_at');
                // NULL = définitif. Un `is_permanent` séparé aurait pu contredire la date.
                $table->timestamp('ends_at')->nullable();

                $table->text('reason');

                $table->timestamp('lifted_at')->nullable();
                $table->unsignedBigInteger('lifted_by_user_id')->nullable();
                $table->text('lift_reason')->nullable();

                $table->timestamps();

                // « Cette option est-elle ouverte à cette personne, maintenant ? » — posée avant
                // chaque proposition de révision.
                $table->index(['user_id', 'feature', 'lifted_at'], 'mfs_personne_option_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_feature_suspensions');
        Schema::dropIfExists('mission_dispute_signals');
    }
};
