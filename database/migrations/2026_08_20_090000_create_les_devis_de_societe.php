<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LE DEVIS BÂTI PAR LA SOCIÉTÉ (E24).
 *
 * CE QUI EXISTE NE COUVRE PAS CE CAS. `MultiTradeBundle` part du CLIENT : il décrit un chantier,
 * les prestataires répondent. `FinanceQuote` part d'une réservation DÉJÀ prise : c'est un document
 * comptable, pas une proposition commerciale. Entre les deux, il manquait le geste le plus ordinaire
 * d'une société de services — « je passe voir, je chiffre, je vous envoie ça » — qui n'existait que
 * dans l'écran d'administration de la plateforme, saisi à la main par un opérateur.
 *
 * LES LIGNES PORTENT UN MÉTIER, PAS UN LIBELLÉ LIBRE. Un devis « nettoyage + peinture » qui ne dit
 * pas quel métier fait quoi ne peut produire aucune mission à l'acceptation : c'est le métier qui
 * décide qui peut l'exécuter. Le libellé reste libre par-dessus, parce qu'un client lit « remise en
 * état des communs », pas « trade #4 ».
 *
 * LE PRIX EST FIGÉ À L'ENVOI, jamais recalculé. Un devis qu'on rouvre trois semaines plus tard après
 * un changement de tarif afficherait un autre montant que celui que le client a reçu — et personne
 * ne saurait expliquer l'écart. Le moteur de prix SUGGÈRE au moment de la saisie ; ce qui est envoyé
 * est ce qui a été écrit.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('provider_quotes')) {
            Schema::create('provider_quotes', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('organization_account_id');
                // Le CLIENT visé — une personne, ou une entreprise cliente.
                $table->unsignedBigInteger('client_user_id')->nullable();
                $table->unsignedBigInteger('client_organization_id')->nullable();
                $table->unsignedBigInteger('organization_site_id')->nullable();

                $table->string('reference', 40)->unique();
                $table->string('title', 160);
                $table->text('intro')->nullable();

                // draft → sent → accepted | declined | expired | cancelled
                $table->string('status', 20)->default('draft');

                $table->unsignedBigInteger('created_by_user_id')->nullable();

                // Figés à l'ENVOI. Recalculer à l'ouverture ferait afficher un autre montant que
                // celui que le client a reçu.
                $table->unsignedBigInteger('total_cents')->default(0);
                $table->string('currency', 3)->default('EUR');
                $table->decimal('tax_rate', 5, 2)->default(0);

                $table->date('valid_until')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->text('decision_note')->nullable();

                $table->json('metadata')->nullable();
                $table->timestamps();

                // Noms courts : MySQL refuse un index au-delà de 64 caractères.
                $table->index(['organization_account_id', 'status'], 'provider_quotes_org_status_idx');
                $table->index(['client_user_id', 'status'], 'provider_quotes_client_status_idx');

                $table->foreign('organization_account_id')->references('id')->on('organization_accounts')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('provider_quote_lines')) {
            Schema::create('provider_quote_lines', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('provider_quote_id');
                /*
                 * LE MÉTIER EST OBLIGATOIRE. C'est lui qui décide qui peut exécuter la ligne : sans
                 * lui, l'acceptation ne peut produire aucune mission et le devis reste un PDF.
                 */
                $table->unsignedBigInteger('trade_id');
                $table->unsignedBigInteger('service_catalog_id')->nullable();

                $table->string('label', 200);
                $table->text('description')->nullable();

                $table->decimal('quantity', 10, 2)->default(1);
                $table->string('unit', 20)->default('unité');
                $table->unsignedBigInteger('unit_price_cents')->default(0);
                $table->unsignedBigInteger('total_cents')->default(0);

                // Ce que le moteur de prix suggérait : garder l'écart rend la remise lisible, et
                // permet de savoir si la société vend systématiquement sous son tarif.
                $table->unsignedBigInteger('suggested_price_cents')->nullable();

                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedBigInteger('booking_id')->nullable();

                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index('provider_quote_id', 'provider_quote_lines_quote_idx');

                $table->foreign('provider_quote_id')->references('id')->on('provider_quotes')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_quote_lines');
        Schema::dropIfExists('provider_quotes');
    }
};
