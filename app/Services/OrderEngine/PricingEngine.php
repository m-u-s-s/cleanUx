<?php

namespace App\Services\OrderEngine;

use App\Models\Question;
use App\Models\Trade;
use App\Support\Domain\OrderMode;
use App\Support\Domain\PriceImpactMode;
use App\Support\Domain\PricingUnit;
use App\Support\Domain\QuestionType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

/**
 * Le calcul de prix, en un seul endroit.
 *
 *     prix_ligne = ( base_métier
 *                  + Σ modificateurs des réponses
 *                  + Σ (valeur numérique × coefficient à l'unité) )
 *                  × Π multiplicateurs des réponses
 *                  × coefficient de mode      (majoration du service immédiat)
 *                  × coefficient de zone      (déplacement, zone tarifaire)
 *                  [ × élargissement d'incertitude, sur le MAXIMUM seulement ]
 *                  + trajet mesuré × (mêmes multiplicateurs de PRIX, aucun élargissement)
 *
 *     prix_commande = Σ prix_lignes − remise multi-services
 *
 * L'ORDRE n'est pas négociable : on additionne d'abord, on multiplie ensuite. L'inverser donne un
 * autre prix, et les deux paraissent également plausibles à la lecture — d'où ce rappel.
 *
 * Quatre règles tenues bout à bout :
 *
 * 1. Tout est en CENTIMES ENTIERS. Les multiplicateurs travaillent en flottant, mais l'arrondi
 *    n'a lieu qu'une fois, à la toute fin. Arrondir à chaque étape fait dériver le total de
 *    quelques centimes — assez pour qu'une facture ne tombe pas juste, et pour que personne ne
 *    comprenne pourquoi.
 *
 * 2. La FOURCHETTE n'est pas un pourcentage inventé. Un « je ne sais pas » sur « murs seuls ou
 *    murs + plafonds » borne le prix entre l'option la moins chère et la plus chère : le client
 *    voit ce qu'il risque réellement. Le repli en pourcentage ne sert que lorsqu'une question
 *    n'offre aucune borne exploitable.
 *
 * 3. Le serveur fait autorité. Rien de ce que le navigateur annonce comme prix n'est lu ici.
 *
 * 4. Un MULTIPLICATEUR DE PRIX et un ÉLARGISSEMENT D'INCERTITUDE ne sont pas la même chose, même
 *    s'ils se ressemblent : les deux multiplient. Le premier dit combien la prestation coûte —
 *    majoration de l'immédiat, coefficient de zone, option « avec véhicule ». Le second dit
 *    seulement à quel point nous ignorons ce qui nous attend. Le trajet, lui, est MESURÉ : il prend
 *    les premiers et jamais les seconds. {@see self::quoteItem()}
 */
class PricingEngine
{
    public function __construct(
        protected ConditionEvaluator $conditions,
    ) {}

    /**
     * Estime une ligne de commande — un métier et ses réponses.
     *
     * @param  Collection<int, Question>  $questions  chargées avec `options` et `conditions`
     * @param  array<string, mixed>  $answers  indexées par code de question
     * @param  array{mode?: string, zone_multiplier?: float, zone_base_cents?: int|null, zone_min_cents?: int|null, zone_max_cents?: int|null}  $context
     */
    public function quoteItem(Trade $trade, Collection $questions, array $answers, array $context = []): PriceBreakdown
    {
        // Un métier au devis obligatoire n'annonce aucun prix : mieux vaut ne rien dire que
        // d'annoncer un chiffre qu'un professionnel démentira sur place.
        if ($trade->pricing_unit === PricingUnit::QUOTE_ONLY) {
            return PriceBreakdown::quoteOnly();
        }

        $mode = $context['mode'] ?? OrderMode::SCHEDULED;

        // Une question cachée ne pèse pas sur le prix : le client ne pourrait rattacher son
        // montant à rien de visible, et le contesterait à raison.
        $visible = $this->conditions->visible($questions, $answers);

        /*
         * LE TARIF DE LA ZONE REMPLACE CELUI DU MÉTIER.
         *
         * C'est le sens même de « l'activation et le prix sont la même ligne » : quand
         * `trade_zone_pricing` fixe un tarif pour ce couple (métier, zone), c'est LUI qui engage.
         * Le tarif du métier n'est plus qu'un défaut, celui qu'on hérite là où aucune grille
         * locale n'a été décidée.
         */
        $base = $context['zone_base_cents'] ?? null;
        $base = $base !== null ? (int) $base : (int) ($trade->base_price_cents ?? 0);
        $sumMin = $base;
        $sumMax = $base;
        $multMin = 1.0;
        $multMax = 1.0;
        $duration = (int) ($trade->estimated_duration_min ?? 0);
        $lines = [];

        if ($base > 0) {
            $lines[] = $this->line('_base', $trade->name, 'Prestation de base', $base, $base);
        }

        foreach ($visible as $question) {
            $impact = $this->questionImpact($question, $answers[$question->code] ?? null, $this->isUnknown($answers, $question));

            $sumMin += $impact['add_min'];
            $sumMax += $impact['add_max'];
            $multMin *= $impact['mult_min'];
            $multMax *= $impact['mult_max'];
            $duration += $impact['duration'];

            if ($impact['add_min'] !== 0 || $impact['add_max'] !== 0 || $impact['mult_min'] !== 1.0 || $impact['mult_max'] !== 1.0) {
                $lines[] = $this->line(
                    $question->code,
                    $question->label,
                    $impact['detail'],
                    $impact['add_min'],
                    $impact['add_max'],
                );
            }
        }

        /*
         * LA DISTANCE, quand — et SEULEMENT quand — la zone a ouvert le tarif au kilomètre.
         *
         * Elle n'est pas versée dans la somme du service : elle a son propre chemin plus bas, parce
         * qu'elle ne rencontre pas les mêmes multiplicateurs. Le drapeau est éteint par défaut sur
         * toutes les lignes existantes — la sortie du moteur reste identique au centime pour tous
         * les métiers d'aujourd'hui.
         */
        $distance = $this->distanceImpact($context);

        if ($distance !== null) {
            $lines[] = $this->line('_distance', 'Trajet', $distance['detail'], $distance['cents'], $distance['cents']);
        }

        $modeMultiplier = $mode === OrderMode::ASAP
            ? (float) Config::get('order_engine.asap_multiplier', 1.30)
            : 1.0;
        $zoneMultiplier = (float) ($context['zone_multiplier'] ?? 1.0);

        // Arrondi UNIQUE, ici et nulle part ailleurs.
        $min = (int) round(max(0, $sumMin) * $multMin * $modeMultiplier * $zoneMultiplier);
        $max = (int) round(max(0, $sumMax) * $multMax * $modeMultiplier * $zoneMultiplier);

        // Le questionnaire du mode immédiat est volontairement raccourci : l'estimation est donc
        // moins précise. Le dire par une fourchette plus large vaut mieux qu'une fausse précision.
        if ($mode === OrderMode::ASAP) {
            $spread = (int) Config::get('order_engine.asap_spread_percent', 15);
            $max = (int) round($max * (1 + $spread / 100));
        }

        // Une fourchette inversée serait absurde à l'affichage ; un plancher mal saisi ne doit pas
        // pouvoir produire ça.
        if ($min > $max) {
            [$min, $max] = [$max, $min];
        }

        /*
         * LE TRAJET MESURÉ ENTRE ICI — après les élargissements, jamais avant.
         *
         * Ce qu'il PREND : la majoration du mode immédiat, le coefficient de zone et les
         * multiplicateurs venus des réponses. C'est le modèle Heetch/Bolt/Uber, et c'est le bon :
         * une course de nuit majore ses kilomètres, pas seulement sa prise en charge. Facturer la
         * majoration sur le seul premier mètre reviendrait à dire qu'un trajet de nuit de trente
         * kilomètres coûte à peu près comme le même trajet en plein après-midi.
         *
         * Ce qu'il NE PREND PAS : les élargissements d'incertitude — les +15 % du questionnaire
         * raccourci de l'immédiat, et le repli d'un « je ne sais pas » sans borne exploitable. Ils
         * ont un sens sur une prestation qu'on découvrira sur place ; aucun sur une distance qu'on
         * a mesurée. Ils s'appliquaient pourtant : une course de vingt kilomètres était annoncée
         * « entre 34,45 € et 39,62 € » alors que les deux chiffres portaient les MÊMES kilomètres,
         * et le client lisait cinq euros de risque là où il n'y en avait pas un centime.
         *
         * D'où `$multMin` et non `$multMax` : le premier ne porte que des multiplicateurs de prix
         * réels, le second y mêle l'élargissement du repli. Deux notions dans un même champ — c'est
         * ce que ce passage démêle.
         *
         * Minimum et maximum reçoivent donc le MÊME montant : sur ce qui est mesuré, il n'y a pas
         * de fourchette à annoncer.
         */
        if ($distance !== null) {
            $trajet = (int) round($distance['cents'] * $multMin * $modeMultiplier * $zoneMultiplier);
            $min += $trajet;
            $max += $trajet;
        }

        /*
         * PLANCHER ET PLAFOND DE LA ZONE, appliqués APRÈS l'arrondi.
         *
         * Ce sont des engagements commerciaux — « on ne se déplace pas sous 45 € à Bruxelles » —
         * pas des composantes du calcul. Les faire entrer plus tôt les laisserait multiplier par
         * la majoration du mode immédiat, et le plancher annoncé ne serait pas celui appliqué.
         *
         * Ils portent sur le total TRAJET COMPRIS, et c'est délibéré : ce plancher existe pour
         * couvrir un déplacement, et l'appliquer avant le trajet le facturerait deux fois. La
         * contrepartie tient dans la même phrase — un plafond posé sur un métier tarifé au
         * kilomètre écrête aussi les longues courses. C'est le sens littéral d'un plafond, et
         * l'administrateur qui ouvre le kilomètre décide donc aussi de ce qu'il laisse en face.
         */
        $floor = $context['zone_min_cents'] ?? null;
        $ceiling = $context['zone_max_cents'] ?? null;

        if ($floor !== null) {
            $min = max($min, (int) $floor);
            $max = max($max, (int) $floor);
        }

        if ($ceiling !== null) {
            $min = min($min, (int) $ceiling);
            $max = min($max, (int) $ceiling);
        }

        return new PriceBreakdown($min, $max, max(0, $duration), $lines);
    }

    /**
     * Consolide les lignes d'une commande et applique la remise multi-services.
     *
     * @param  list<PriceBreakdown>  $items
     */
    public function quoteOrder(array $items, string $mode = OrderMode::SCHEDULED): PriceBreakdown
    {
        $lines = [];
        $min = 0;
        $max = 0;
        $duration = 0;

        foreach ($items as $item) {
            if ($item->quoteOnly) {
                continue;
            }
            $min += $item->minCents;
            $max += $item->maxCents;
            $duration += $item->durationMin;
            $lines = array_merge($lines, $item->lines);
        }

        $discount = $mode === OrderMode::BUNDLE ? $this->bundleDiscountPercent(count($items)) : 0;

        if ($discount > 0) {
            $discountMin = (int) round($min * $discount / 100);
            $discountMax = (int) round($max * $discount / 100);

            // La remise est une LIGNE du devis, pas une soustraction silencieuse : une remise que
            // le client ne voit pas ne le décide à rien.
            $lines[] = $this->line(
                '_bundle_discount',
                'Remise multi-services',
                sprintf('−%d %% en commandant %d services ensemble', $discount, count($items)),
                -$discountMin,
                -$discountMax,
            );

            $min -= $discountMin;
            $max -= $discountMax;
        }

        return new PriceBreakdown(max(0, $min), max(0, $max), $duration, $lines);
    }

    /** Palier de remise atteint pour ce nombre de métiers. */
    public function bundleDiscountPercent(int $itemCount): int
    {
        $tiers = (array) Config::get('order_engine.bundle_discount_percent', []);
        $applicable = 0;

        foreach ($tiers as $threshold => $percent) {
            if ($itemCount >= (int) $threshold) {
                $applicable = max($applicable, (int) $percent);
            }
        }

        return $applicable;
    }

    /**
     * Le prix du TRAJET : prise en charge, kilomètres au-delà du forfait, minutes le cas échéant.
     *
     * Rend `null` — et non zéro — quand la tarification à la distance n'est pas ouverte sur ce
     * couple (métier, zone), ou quand aucune route n'a été mesurée. La différence n'est pas
     * cosmétique : zéro produirait une ligne « Trajet — 0,00 € » sur le devis d'un ménage, et
     * ferait douter le client de ce qu'il lit.
     *
     * IL N'Y A PAS DE FOURCHETTE ICI. La distance est MESURÉE, pas estimée à partir d'une réponse
     * incertaine : min et max sont donc le même nombre, et l'élargir donnerait une fausse
     * impression d'approximation là où le chiffre est exact.
     *
     * @param  array<string, mixed>  $context
     * @return array{cents: int, detail: string}|null
     */
    protected function distanceImpact(array $context): ?array
    {
        if (! ($context['distance_pricing_enabled'] ?? false)) {
            return null;
        }

        $metres = $context['route_distance_m'] ?? null;

        if ($metres === null) {
            return null;
        }

        $parKm = $context['price_per_km_cents'] ?? null;
        $parMinute = $context['price_per_minute_cents'] ?? null;
        $priseEnCharge = (int) ($context['pickup_fee_cents'] ?? 0);
        $inclus = (int) ($context['included_km'] ?? 0);

        if ($parKm === null && $parMinute === null && $priseEnCharge === 0) {
            return null;
        }

        $km = $metres / 1000;
        // Les kilomètres compris dans la prise en charge ne se refacturent pas : sans eux, une
        // course de huit cents mètres se paierait au compteur, pour un montant qui ne couvre même
        // pas le déplacement du conducteur.
        $kmFactures = max(0.0, $km - $inclus);

        $cents = $priseEnCharge + (int) round($kmFactures * (int) ($parKm ?? 0));
        $details = [];

        if ($priseEnCharge > 0) {
            $details[] = sprintf('prise en charge %s €', number_format($priseEnCharge / 100, 2, ',', ' '));
        }

        if ($parKm !== null) {
            $details[] = sprintf(
                '%s km × %s €',
                str_replace('.', ',', (string) round($kmFactures, 1)),
                number_format($parKm / 100, 2, ',', ' '),
            );
        }

        if ($parMinute !== null && ($context['route_duration_s'] ?? null) !== null) {
            $minutes = (int) ceil(((int) $context['route_duration_s']) / 60);
            $cents += (int) round($minutes * $parMinute);
            $details[] = sprintf('%d min × %s €', $minutes, number_format($parMinute / 100, 2, ',', ' '));
        }

        return ['cents' => $cents, 'detail' => implode(' · ', $details)];
    }

    /**
     * Impact d'une question sur la somme, le produit et la durée.
     *
     * @return array{add_min: int, add_max: int, mult_min: float, mult_max: float, duration: int, detail: string|null}
     */
    protected function questionImpact(Question $question, mixed $answer, bool $unknown): array
    {
        $none = ['add_min' => 0, 'add_max' => 0, 'mult_min' => 1.0, 'mult_max' => 1.0, 'duration' => 0, 'detail' => null];

        if ($unknown) {
            return $this->unknownImpact($question);
        }

        if ($answer === null || $answer === '' || $answer === []) {
            return $none;
        }

        if ($question->isOptionBased()) {
            return $this->optionImpact($question, $answer);
        }

        if ($question->isNumeric()) {
            return $this->numericImpact($question, $answer);
        }

        return $none;
    }

    /** Réponse par options : on somme les options retenues. */
    protected function optionImpact(Question $question, mixed $answer): array
    {
        $selected = collect(is_array($answer) ? $answer : [$answer])->map(fn ($v) => (string) $v);

        $options = $question->options->whereIn('value', $selected->all());

        $add = (int) $options->sum('price_modifier_cents');
        $mult = 1.0;
        foreach ($options as $option) {
            if ($option->price_multiplier !== null) {
                $mult *= (float) $option->price_multiplier;
            }
        }

        return [
            'add_min' => $add,
            'add_max' => $add,
            'mult_min' => $mult,
            'mult_max' => $mult,
            'duration' => (int) $options->sum('duration_modifier_min'),
            'detail' => $options->pluck('label')->implode(', ') ?: null,
        ];
    }

    /** Réponse numérique : surface, compteur, nombre. */
    protected function numericImpact(Question $question, mixed $answer): array
    {
        $value = is_numeric($answer) ? (float) $answer : 0.0;
        $pricing = $question->pricing ?? [];
        $mode = $pricing['mode'] ?? PriceImpactMode::NONE;
        $coefficient = (float) ($pricing['coefficient'] ?? 0);

        $impact = match ($mode) {
            PriceImpactMode::PER_UNIT => ['add' => (int) round($value * $coefficient), 'mult' => 1.0],
            PriceImpactMode::ADD => ['add' => (int) round($coefficient), 'mult' => 1.0],
            PriceImpactMode::MULTIPLY => ['add' => 0, 'mult' => $coefficient > 0 ? $coefficient : 1.0],
            default => ['add' => 0, 'mult' => 1.0],
        };

        $unit = $pricing['unit'] ?? ($question->validation['unit'] ?? null);

        /*
         * La durée suit la même logique que le prix : à l'unité, elle est PROPORTIONNELLE à la
         * valeur — 80 m² ne prennent pas le même temps que 20. Dans les autres modes, l'impact
         * est forfaitaire. Confondre les deux ferait tenir une façade entière en vingt minutes.
         */
        $durationImpact = (int) $question->duration_impact_min;
        $duration = $mode === PriceImpactMode::PER_UNIT
            ? (int) round($value * $durationImpact)
            : $durationImpact;

        return [
            'add_min' => $impact['add'],
            'add_max' => $impact['add'],
            'mult_min' => $impact['mult'],
            'mult_max' => $impact['mult'],
            'duration' => $duration,
            'detail' => $unit ? trim($value.' '.$unit) : (string) $value,
        ];
    }

    /**
     * « Je ne sais pas » : on borne au lieu de bloquer.
     *
     * C'est ce qui rend la porte de sortie tenable sans mentir sur le prix. Une question à
     * options se borne entre l'option la moins chère et la plus chère ; une question numérique,
     * entre les bornes de validation déclarées par l'administrateur. Le repli en pourcentage
     * n'intervient que si la question n'offre aucune de ces deux prises.
     */
    protected function unknownImpact(Question $question): array
    {
        $duration = 0;

        if ($question->isOptionBased() && $question->options->isNotEmpty()) {
            $modifiers = $question->options->pluck('price_modifier_cents')->map(fn ($v) => (int) $v);

            return [
                'add_min' => (int) $modifiers->min(),
                'add_max' => (int) $modifiers->max(),
                'mult_min' => 1.0,
                'mult_max' => 1.0,
                'duration' => (int) $question->options->max('duration_modifier_min'),
                'detail' => 'À évaluer sur place',
            ];
        }

        if ($question->isNumeric()) {
            $bounds = $question->validationBounds();
            $pricing = $question->pricing ?? [];
            $coefficient = (float) ($pricing['coefficient'] ?? 0);

            if (($pricing['mode'] ?? null) === PriceImpactMode::PER_UNIT
                && $bounds['min'] !== null && $bounds['max'] !== null) {
                return [
                    'add_min' => (int) round((float) $bounds['min'] * $coefficient),
                    'add_max' => (int) round((float) $bounds['max'] * $coefficient),
                    'mult_min' => 1.0,
                    'mult_max' => 1.0,
                    'duration' => $duration,
                    'detail' => 'À évaluer sur place',
                ];
            }
        }

        // Aucune prise : on élargit d'un pourcentage, et on le dit.
        $spread = (int) Config::get('order_engine.unknown_fallback_spread_percent', 20);

        return [
            'add_min' => 0,
            'add_max' => 0,
            'mult_min' => 1.0,
            'mult_max' => 1 + $spread / 100,
            'duration' => $duration,
            'detail' => 'À évaluer sur place',
        ];
    }

    /**
     * La réponse est-elle une porte de sortie ?
     *
     * @param  array<string, mixed>  $answers
     */
    protected function isUnknown(array $answers, Question $question): bool
    {
        $value = $answers[$question->code] ?? null;

        if (is_array($value) && array_key_exists('unknown', $value)) {
            return (bool) $value['unknown'];
        }

        return $value === '__unknown__';
    }

    /** @return array{code: string, label: string, detail: string|null, min_cents: int, max_cents: int} */
    protected function line(string $code, string $label, ?string $detail, int $min, int $max): array
    {
        return [
            'code' => $code,
            'label' => $label,
            'detail' => $detail,
            'min_cents' => $min,
            'max_cents' => $max,
        ];
    }

    /** Types numériques reconnus — exposé pour les tests et le constructeur de questions. */
    public static function numericTypes(): array
    {
        return QuestionType::numeric();
    }
}
