<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LES SUPPLÉMENTS PROPOSÉS SUR PLACE.
 *
 * Le prestataire arrive, constate que les vitres n'étaient pas dans le devis, et propose vingt-cinq
 * euros de plus. Aujourd'hui il n'a que deux mauvaises réponses : le faire gratuitement, ou ne pas
 * le faire. Les deux abîment quelque chose — sa marge, ou le service rendu — et la troisième, celle
 * qui consiste à s'arranger en espèces, sort l'argent de la plateforme et le client de toute
 * protection.
 *
 * LE DEVIS D'ORIGINE RESTE FIGÉ, et c'est la décision structurante de cette table. Un supplément
 * est une LIGNE ADDITIONNELLE, pas une correction du montant initial : le client doit pouvoir
 * relire ce qu'il a accepté au départ, et un devis qui change après coup est précisément ce qui
 * fait perdre confiance. La facture porte donc deux lignes distinctes, et le litige éventuel ne
 * porte que sur la seconde.
 *
 * LE PRIX NE SE SAISIT PAS LIBREMENT. Il passe par Pricing v2, dont on garde le devis
 * (`price_quote_id`) : sans cela, le montant serait la parole du prestataire contre celle du
 * client, et la plateforme n'aurait rien à opposer en cas de contestation.
 *
 * LES STATUTS SONT QUATRE, et le quatrième compte autant que les trois autres. `proposed` attend,
 * `approved` a la parole du client, `declined` la refuse — et `charged` dit que l'argent a
 * réellement bougé. Confondre « approuvé » et « encaissé » ferait réclamer deux fois un supplément
 * dont le prélèvement a échoué, ou ne jamais réclamer celui dont on croyait le paiement acquis.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mission_extras')) {
            return;
        }

        Schema::create('mission_extras', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('mission_id');
            $table->unsignedBigInteger('proposed_by_user_id')->nullable();

            $table->string('label');
            $table->text('description')->nullable();

            /*
             * EN CENTIMES, comme tout l'argent de ce dépôt. Un décimal flottant sur un montant qu'on
             * additionne à une facture finit par produire des écarts d'un centime que personne ne
             * sait expliquer, et qui font échouer les rapprochements comptables.
             */
            $table->integer('price_cents');
            $table->string('currency', 3)->default('EUR');

            // Le devis Pricing v2 qui justifie ce montant. Nullable : un supplément saisi par
            // l'administration lors d'une reprise n'en a pas forcément.
            $table->unsignedBigInteger('price_quote_id')->nullable();

            $table->string('status', 20)->default('proposed');

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('charged_at')->nullable();

            // Le prélèvement incrémental est une charge Stripe à part entière, distincte de celle du
            // devis d'origine : elle a son propre identifiant, et son propre sort en cas d'échec.
            $table->string('stripe_payment_intent_id')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            // Nom court : MySQL refuse un nom d'index au-delà de 64 caractères, et celui que Laravel
            // génère par défaut pour deux colonnes sur cette table en approche.
            $table->index(['mission_id', 'status'], 'mission_extras_status_idx');

            $table->foreign('mission_id')->references('id')->on('missions')->cascadeOnDelete();
            $table->foreign('proposed_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_extras');
    }
};
