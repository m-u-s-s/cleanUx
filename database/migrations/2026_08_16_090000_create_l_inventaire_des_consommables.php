<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** L'INVENTAIRE DES CONSOMMABLES (E23), ET CE QUI SE CONSOMME SUR PLACE (F7). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_items')) {
            Schema::create('inventory_items', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('organization_account_id');
                // Nullable : une société sans agences tient un stock unique, et l'obliger à créer
                // une agence fictive pour ranger ses produits serait une formalité inventée.
                $table->unsignedBigInteger('provider_agency_id')->nullable();

                $table->string('name');
                $table->string('sku')->nullable();
                $table->string('unit', 24)->default('unité');

                // En unités entières : un demi-sac poubelle n'existe pas, et un décimal sur un
                // compteur invite à des écarts qu'on n'explique plus.
                $table->integer('quantity')->default(0);
                $table->unsignedInteger('reorder_threshold')->default(0);

                // Le coût sert à valoriser une consommation dans le calcul de rentabilité (E22).
                $table->unsignedInteger('unit_cost_cents')->nullable();

                $table->boolean('is_billable')->default(false);
                $table->boolean('is_active')->default(true);
                $table->json('metadata')->nullable();
                $table->timestamps();

                // Nom court : MySQL refuse au-delà de 64 caractères, et celui que Laravel génère
                // pour ces deux colonnes en fait exactement 64.
                $table->index(['organization_account_id', 'provider_agency_id'], 'inventory_items_org_agency_idx');

                $table->foreign('organization_account_id')->references('id')->on('organization_accounts')->cascadeOnDelete();
                $table->foreign('provider_agency_id')->references('id')->on('provider_agencies')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('inventory_movements')) {
            Schema::create('inventory_movements', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('inventory_item_id');
                $table->unsignedBigInteger('user_id')->nullable();
                // La mission qui a consommé, quand il y en a une. C'est ce lien qui permet à F7 de
                // rattacher un produit à une intervention, et à E22 d'en calculer le coût.
                $table->unsignedBigInteger('mission_id')->nullable();

                // `reception`, `consumption`, `adjustment`. Un mouvement sans cause ne sert qu'à
                // constater un écart qu'on ne saura pas expliquer.
                $table->string('type', 20);

                // SIGNÉE : positive à la réception, négative à la consommation. Un seul signe
                // obligerait chaque lecteur à savoir dans quel sens compter, et l'un d'eux se
                // tromperait.
                $table->integer('quantity');

                $table->string('reason')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['inventory_item_id', 'created_at'], 'inventory_movements_item_idx');

                $table->foreign('inventory_item_id')->references('id')->on('inventory_items')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('mission_id')->references('id')->on('missions')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_items');
    }
};
