<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\QuestionCondition;
use App\Models\QuestionOption;
use App\Models\QuestionStep;
use App\Models\Sector;
use App\Models\Trade;
use App\Models\TradeBundleSuggestion;
use App\Support\Domain\ConditionAction;
use App\Support\Domain\ConditionOperator;
use App\Support\Domain\PriceImpactMode;
use App\Support\Domain\PricingUnit;
use App\Support\Domain\QuestionType;
use Illuminate\Database\Seeder;

/**
 * Catalogue de démonstration du moteur de commande : 3 secteurs, 9 métiers, questionnaires complets.
 *
 * Deux règles tenues d'un bout à l'autre.
 *
 * IDEMPOTENT. Tout passe par `updateOrCreate` sur une clé stable — slug pour les secteurs et les
 * métiers, `code` pour les questions, `value` pour les options. Rejouer le seeder ne duplique
 * rien et n'écrase aucune réponse déjà donnée par un client.
 *
 * IL RATTACHE, IL NE DUPLIQUE PAS. Six des neuf métiers existent déjà en base avec leurs tarifs et
 * leurs certifications ; le seeder leur ajoute un secteur et les colonnes du moteur de commande.
 * Créer « Peinture » une seconde fois aurait produit deux vérités pour le même métier.
 *
 * Les questionnaires respectent les lois du parcours : au plus sept questions par étape, une
 * porte de sortie sur chacune, un défaut intelligent partout, et une question photo — qui vaut
 * dix questions mal posées.
 */
class OrderEngineCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalog() as $sectorData) {
            $trades = $sectorData['trades'];
            unset($sectorData['trades']);

            $sector = Sector::updateOrCreate(
                ['slug' => $sectorData['slug']],
                $sectorData + ['published_at' => now()],
            );

            foreach ($trades as $index => $tradeData) {
                $this->seedTrade($sector, $tradeData, $index);
            }
        }

        $this->seedBundleSuggestions();
    }

    private function seedTrade(Sector $sector, array $data, int $index): void
    {
        $steps = $data['steps'] ?? [];
        unset($data['steps']);

        $slug = $data['slug'];
        unset($data['slug']);

        /*
         * `updateOrCreate` sur le slug : un métier déjà présent est ENRICHI, pas remplacé. Les
         * colonnes qu'on ne mentionne pas — tarif horaire, certifications, multiplicateurs
         * d'urgence — restent telles que l'exploitation les a réglées.
         */
        $trade = Trade::updateOrCreate(['slug' => $slug], $data + [
            'sector_id' => $sector->id,
            'sort_order' => $index,
            'is_active' => true,
            'published_at' => now(),
        ]);

        foreach ($steps as $stepIndex => $stepData) {
            $step = QuestionStep::updateOrCreate(
                ['trade_id' => $trade->id, 'title' => $stepData['title']],
                ['subtitle' => $stepData['subtitle'] ?? null, 'sort_order' => $stepIndex],
            );

            foreach ($stepData['questions'] as $qIndex => $questionData) {
                $this->seedQuestion($trade, $step, $questionData, $qIndex);
            }
        }
    }

    private function seedQuestion(Trade $trade, QuestionStep $step, array $data, int $index): void
    {
        $options = $data['options'] ?? [];
        $showIf = $data['show_if'] ?? null;
        unset($data['options'], $data['show_if']);

        $question = Question::updateOrCreate(
            ['trade_id' => $trade->id, 'code' => $data['code']],
            $data + [
                'step_id' => $step->id,
                'sort_order' => $index,
                // La porte de sortie est le DÉFAUT : une question sans échappatoire est un mur,
                // et il faut une raison explicite pour en poser un.
                'allows_unknown' => true,
                'is_active' => true,
            ],
        );

        foreach ($options as $optionIndex => $option) {
            QuestionOption::updateOrCreate(
                ['question_id' => $question->id, 'value' => $option['value']],
                $option + ['sort_order' => $optionIndex, 'is_active' => true],
            );
        }

        if ($showIf) {
            $dependsOn = Question::where('trade_id', $trade->id)->where('code', $showIf['code'])->first();

            if ($dependsOn) {
                QuestionCondition::updateOrCreate(
                    [
                        'question_id' => $question->id,
                        'depends_on_question_id' => $dependsOn->id,
                        'action' => ConditionAction::SHOW,
                    ],
                    [
                        'operator' => $showIf['operator'] ?? ConditionOperator::EQUALS,
                        'value' => ['value' => $showIf['value']],
                    ],
                );
            }
        }
    }

    /**
     * « Souvent commandé avec » — les associations du chantier réel.
     *
     * Le délai porte le temps de séchage : après une plomberie, le carrelage ne pose pas dans la
     * foulée. C'est ce qui rend la timeline du mode multi-services crédible plutôt que décorative.
     */
    private function seedBundleSuggestions(): void
    {
        $pairs = [
            ['plumbing', 'electrical', 0],
            ['plumbing', 'nettoyage-fin-chantier', 1440],
            ['peinture', 'nettoyage-fin-chantier', 720],
            ['peinture', 'electrical', 0],
            ['electrical', 'peinture', 2880],
            ['roofing', 'nettoyage-fin-chantier', 1440],
            ['jardinage', 'elagage', 0],
        ];

        foreach ($pairs as $index => [$from, $to, $gap]) {
            $source = Trade::where('slug', $from)->first();
            $target = Trade::where('slug', $to)->first();

            if (! $source || ! $target) {
                continue;
            }

            TradeBundleSuggestion::updateOrCreate(
                ['trade_id' => $source->id, 'suggested_trade_id' => $target->id],
                ['default_sequence_gap_min' => $gap, 'sort_order' => $index, 'is_active' => true],
            );
        }
    }

    /**
     * Le catalogue, en données pures.
     *
     * Ce tableau est exactement ce qu'un administrateur produira depuis le constructeur de
     * parcours : il n'y a rien ici qu'une interface ne puisse écrire.
     */
    private function catalog(): array
    {
        return [
            [
                'slug' => 'batiment-renovation',
                'name' => 'Bâtiment & rénovation',
                'tagline' => 'Du petit dépannage au chantier complet',
                'icon' => 'hammer',
                'accent_color' => '#B45309',
                'sort_order' => 0,
                'trades' => [
                    $this->peinture(),
                    $this->plomberie(),
                    $this->electricite(),
                    $this->toiture(),
                ],
            ],
            [
                'slug' => 'nettoyage',
                'name' => 'Nettoyage',
                'tagline' => 'Remettre à neuf, une fois ou chaque semaine',
                'icon' => 'sparkles',
                'accent_color' => '#0E7490',
                'sort_order' => 1,
                'trades' => [
                    $this->finDeChantier(),
                    $this->nettoyageDomicile(),
                    $this->vitrerie(),
                ],
            ],
            [
                'slug' => 'espaces-verts',
                'name' => 'Espaces verts',
                'tagline' => 'Jardins, haies et grands arbres',
                'icon' => 'tree',
                'accent_color' => '#15803D',
                'sort_order' => 2,
                'trades' => [
                    $this->jardinage(),
                    $this->elagage(),
                ],
            ],
        ];
    }

    // ─── Bâtiment ────────────────────────────────────────────────────────────────────────────

    /**
     * Le questionnaire de référence — celui sur lequel les autres se règlent.
     *
     * Sept questions en DEUX étapes : au-delà de sept d'un coup, un client sur trois abandonne.
     * Une seule est conditionnelle, et elle illustre la règle : « type de pistolet » n'a aucun
     * sens tant qu'on n'a pas dit qu'on peignait au pistolet.
     */
    private function peinture(): array
    {
        return [
            'slug' => 'peinture',
            'code' => 'PNT',
            'name' => 'Peinture',
            'icon' => 'paint-roller',
            'short_description' => 'Murs, plafonds et boiseries, intérieur comme extérieur',
            'base_price_cents' => 12000,
            'pricing_unit' => PricingUnit::PER_M2,
            'estimated_duration_min' => 240,
            'min_duration_min' => 120,
            'allows_scheduled' => true,
            // Un chantier de peinture ne se commande pas dans l'heure : le mode immédiat reste fermé.
            'allows_asap' => false,
            'allows_bundle' => true,
            'steps' => [
                [
                    'title' => 'Le chantier',
                    'subtitle' => 'Trois questions pour cadrer la surface',
                    'questions' => [
                        [
                            'code' => 'surface_m2',
                            'label' => 'Quelle surface à peindre ?',
                            'help_text' => 'Longueur × hauteur des murs. Un doute ? Passez à la suite.',
                            'type' => QuestionType::SURFACE,
                            'is_required' => true,
                            'is_essential' => true,
                            'validation' => ['min' => 5, 'max' => 400, 'step' => 1, 'unit' => 'm²'],
                            'pricing' => ['mode' => PriceImpactMode::PER_UNIT, 'coefficient' => 250, 'unit' => 'm²'],
                            'duration_impact_min' => 3,
                            'display' => ['layout' => 'slider'],
                        ],
                        [
                            'code' => 'etendue',
                            'label' => 'Que faut-il peindre ?',
                            'type' => QuestionType::SINGLE_CHOICE,
                            'is_required' => true,
                            'display' => ['layout' => 'cards', 'columns' => 1],
                            'options' => [
                                ['label' => 'Les murs seulement', 'value' => 'murs', 'price_modifier_cents' => 0, 'is_default' => true],
                                ['label' => 'Murs et plafonds', 'value' => 'murs_plafonds', 'price_modifier_cents' => 4500, 'duration_modifier_min' => 60],
                                ['label' => 'Murs, plafonds et boiseries', 'value' => 'complet', 'price_modifier_cents' => 9000, 'duration_modifier_min' => 120],
                            ],
                        ],
                        [
                            'code' => 'etat_support',
                            'label' => 'Dans quel état sont les murs ?',
                            'help_text' => 'Un support abîmé demande une préparation avant peinture.',
                            'type' => QuestionType::SINGLE_CHOICE,
                            'display' => ['layout' => 'cards', 'columns' => 1],
                            'options' => [
                                ['label' => 'Bon état', 'description' => 'Une couche suffit', 'value' => 'bon', 'is_default' => true],
                                ['label' => 'Quelques trous à reboucher', 'value' => 'a_reboucher', 'price_modifier_cents' => 3500, 'duration_modifier_min' => 60],
                                ['label' => 'Ancien revêtement à décaper', 'value' => 'a_decaper', 'price_multiplier' => 1.35, 'duration_modifier_min' => 180],
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'La prestation',
                    'subtitle' => 'Comment on travaille, et avec quoi',
                    'questions' => [
                        [
                            'code' => 'application',
                            'label' => 'Mode d’application souhaité',
                            'type' => QuestionType::SINGLE_CHOICE,
                            'display' => ['layout' => 'chips'],
                            'options' => [
                                ['label' => 'Au rouleau', 'value' => 'rouleau', 'is_default' => true],
                                ['label' => 'Au pistolet', 'description' => 'Plus rapide sur les grandes surfaces', 'value' => 'pistolet', 'price_modifier_cents' => 4000],
                            ],
                        ],
                        [
                            'code' => 'type_pistolet',
                            'label' => 'Quel type de pistolet ?',
                            'type' => QuestionType::SINGLE_CHOICE,
                            'display' => ['layout' => 'chips'],
                            // La question conditionnelle du parcours : elle n'existe que si le
                            // pistolet a été choisi. Sinon elle n'a aucun sens à l'écran.
                            'show_if' => ['code' => 'application', 'value' => 'pistolet'],
                            'options' => [
                                ['label' => 'Airless', 'value' => 'airless', 'price_modifier_cents' => 2000, 'is_default' => true],
                                ['label' => 'Pneumatique', 'value' => 'pneumatique', 'price_modifier_cents' => 3500],
                            ],
                        ],
                        [
                            'code' => 'fourniture',
                            'label' => 'Qui fournit la peinture ?',
                            'type' => QuestionType::SINGLE_CHOICE,
                            'display' => ['layout' => 'chips'],
                            'options' => [
                                ['label' => 'Je la fournis', 'value' => 'client', 'is_default' => true],
                                ['label' => 'Le prestataire la fournit', 'value' => 'prestataire', 'price_multiplier' => 1.25],
                            ],
                        ],
                        $this->photoQuestion('Une photo de la pièce ?', 'Elle vaut dix questions : le peintre voit tout de suite l’état des murs.'),
                    ],
                ],
            ],
        ];
    }

    /**
     * Plomberie — le métier ouvert au service immédiat.
     *
     * Trois questions sont marquées essentielles : ce sont les seules posées en mode urgent. Le
     * reste se règle sur place, et la fourchette annoncée est simplement plus large.
     */
    private function plomberie(): array
    {
        return [
            'slug' => 'plumbing',
            'code' => 'PLB',
            'name' => 'Plomberie',
            'icon' => 'wrench',
            'short_description' => 'Fuites, débouchages, sanitaires',
            'base_price_cents' => 8500,
            'pricing_unit' => PricingUnit::FIXED,
            'estimated_duration_min' => 90,
            'min_duration_min' => 60,
            'allows_scheduled' => true,
            'allows_asap' => true,
            'allows_bundle' => true,
            'steps' => [
                [
                    'title' => 'L’intervention',
                    'questions' => [
                        [
                            'code' => 'type_intervention',
                            'label' => 'De quoi s’agit-il ?',
                            'type' => QuestionType::SINGLE_CHOICE,
                            'is_required' => true,
                            'is_essential' => true,
                            'display' => ['layout' => 'cards', 'columns' => 2],
                            'options' => [
                                ['label' => 'Une fuite', 'icon' => 'droplet', 'value' => 'fuite', 'is_default' => true],
                                ['label' => 'Un débouchage', 'icon' => 'pipe', 'value' => 'debouchage', 'price_modifier_cents' => 2000],
                                ['label' => 'Remplacer un sanitaire', 'icon' => 'shower', 'value' => 'remplacement', 'price_modifier_cents' => 6000, 'duration_modifier_min' => 60],
                                ['label' => 'Une installation neuve', 'icon' => 'tools', 'value' => 'installation', 'price_modifier_cents' => 12000, 'duration_modifier_min' => 120],
                            ],
                        ],
                        [
                            'code' => 'eau_coupee',
                            'label' => 'L’eau est-elle coupée ?',
                            'help_text' => 'Cela change l’urgence, et ce que le prestataire emporte.',
                            'type' => QuestionType::BOOLEAN,
                            'is_essential' => true,
                            'display' => ['layout' => 'chips'],
                            'options' => [
                                ['label' => 'Oui, tout est coupé', 'value' => 'oui', 'price_modifier_cents' => 0],
                                ['label' => 'Non', 'value' => 'non', 'is_default' => true],
                            ],
                        ],
                        [
                            'code' => 'acces',
                            'label' => 'L’accès à l’installation est-il dégagé ?',
                            'type' => QuestionType::SINGLE_CHOICE,
                            'is_essential' => true,
                            'display' => ['layout' => 'cards', 'columns' => 1],
                            'options' => [
                                ['label' => 'Accès libre', 'value' => 'libre', 'is_default' => true],
                                ['label' => 'Derrière une trappe', 'value' => 'trappe', 'price_modifier_cents' => 1500, 'duration_modifier_min' => 20],
                                ['label' => 'Encastré, à ouvrir', 'value' => 'encastre', 'price_modifier_cents' => 5000, 'duration_modifier_min' => 60],
                            ],
                        ],
                        [
                            'code' => 'fourniture_piece',
                            'label' => 'Qui fournit les pièces ?',
                            'type' => QuestionType::SINGLE_CHOICE,
                            'display' => ['layout' => 'chips'],
                            'options' => [
                                ['label' => 'Le prestataire', 'value' => 'prestataire', 'is_default' => true, 'price_modifier_cents' => 3000],
                                ['label' => 'Je les ai déjà', 'value' => 'client'],
                            ],
                        ],
                        [
                            'code' => 'etage',
                            'label' => 'À quel étage ?',
                            'type' => QuestionType::COUNTER,
                            'validation' => ['min' => 0, 'max' => 12, 'step' => 1],
                            'display' => ['layout' => 'counter'],
                        ],
                        $this->photoQuestion('Une photo de la fuite ou du sanitaire ?', 'Le plombier saura quoi emporter avant de partir.'),
                    ],
                ],
            ],
        ];
    }

    private function electricite(): array
    {
        return [
            'slug' => 'electrical',
            'code' => 'ELC',
            'name' => 'Électricité',
            'icon' => 'bolt',
            'short_description' => 'Panne, mise aux normes, points lumineux',
            'base_price_cents' => 9000,
            'pricing_unit' => PricingUnit::FIXED,
            'estimated_duration_min' => 90,
            'min_duration_min' => 60,
            'allows_asap' => true,
            'steps' => [[
                'title' => 'L’intervention',
                'questions' => [
                    [
                        'code' => 'type_intervention',
                        'label' => 'Que faut-il faire ?',
                        'type' => QuestionType::SINGLE_CHOICE,
                        'is_required' => true,
                        'is_essential' => true,
                        'display' => ['layout' => 'cards', 'columns' => 2],
                        'options' => [
                            ['label' => 'Une panne', 'value' => 'panne', 'is_default' => true],
                            ['label' => 'Ajouter des prises', 'value' => 'prises', 'price_modifier_cents' => 4000],
                            ['label' => 'Point lumineux', 'value' => 'luminaire', 'price_modifier_cents' => 3000],
                            ['label' => 'Mise aux normes', 'value' => 'normes', 'price_modifier_cents' => 15000, 'duration_modifier_min' => 180],
                        ],
                    ],
                    [
                        'code' => 'courant_coupe',
                        'label' => 'Le courant est-il coupé ?',
                        'type' => QuestionType::BOOLEAN,
                        'is_essential' => true,
                        'display' => ['layout' => 'chips'],
                        'options' => [
                            ['label' => 'Oui', 'value' => 'oui'],
                            ['label' => 'Non', 'value' => 'non', 'is_default' => true],
                        ],
                    ],
                    [
                        'code' => 'nb_points',
                        'label' => 'Combien de points concernés ?',
                        'type' => QuestionType::COUNTER,
                        'validation' => ['min' => 1, 'max' => 30, 'step' => 1],
                        'pricing' => ['mode' => PriceImpactMode::PER_UNIT, 'coefficient' => 1500],
                        'duration_impact_min' => 20,
                        'display' => ['layout' => 'counter'],
                    ],
                    [
                        'code' => 'tableau_accessible',
                        'label' => 'Le tableau électrique est-il accessible ?',
                        'type' => QuestionType::BOOLEAN,
                        'display' => ['layout' => 'chips'],
                        'options' => [
                            ['label' => 'Oui', 'value' => 'oui', 'is_default' => true],
                            ['label' => 'Non', 'value' => 'non', 'price_modifier_cents' => 2000],
                        ],
                    ],
                    $this->photoQuestion('Une photo du tableau ?', 'Elle évite un déplacement pour rien.'),
                ],
            ]],
        ];
    }

    /** Toiture : au devis obligatoire — aucun prix ne s'annonce sans avoir vu le toit. */
    private function toiture(): array
    {
        return [
            'slug' => 'roofing',
            'code' => 'ROF',
            'name' => 'Toiture',
            'icon' => 'home',
            'short_description' => 'Fuite, tuiles, gouttières, isolation',
            'pricing_unit' => PricingUnit::QUOTE_ONLY,
            'estimated_duration_min' => 240,
            'allows_asap' => false,
            'steps' => [[
                'title' => 'Votre toiture',
                'questions' => [
                    [
                        'code' => 'nature',
                        'label' => 'Que constatez-vous ?',
                        'type' => QuestionType::SINGLE_CHOICE,
                        'is_required' => true,
                        'display' => ['layout' => 'cards', 'columns' => 1],
                        'options' => [
                            ['label' => 'Une infiltration', 'value' => 'infiltration', 'is_default' => true],
                            ['label' => 'Des tuiles déplacées', 'value' => 'tuiles'],
                            ['label' => 'Gouttières à reprendre', 'value' => 'gouttieres'],
                            ['label' => 'Rénovation complète', 'value' => 'renovation'],
                        ],
                    ],
                    [
                        'code' => 'niveaux',
                        'label' => 'Combien de niveaux a le bâtiment ?',
                        'type' => QuestionType::COUNTER,
                        'validation' => ['min' => 1, 'max' => 8, 'step' => 1],
                        'display' => ['layout' => 'counter'],
                    ],
                    [
                        'code' => 'acces_toit',
                        'label' => 'Comment accède-t-on au toit ?',
                        'type' => QuestionType::SINGLE_CHOICE,
                        'display' => ['layout' => 'cards', 'columns' => 1],
                        'options' => [
                            ['label' => 'Par une trappe intérieure', 'value' => 'trappe', 'is_default' => true],
                            ['label' => 'Échelle depuis l’extérieur', 'value' => 'echelle'],
                            ['label' => 'Échafaudage nécessaire', 'value' => 'echafaudage'],
                        ],
                    ],
                    $this->photoQuestion('Une photo du toit ?', 'Indispensable pour chiffrer sans se déplacer.'),
                ],
            ]],
        ];
    }

    // ─── Nettoyage ───────────────────────────────────────────────────────────────────────────

    private function finDeChantier(): array
    {
        return [
            'slug' => 'nettoyage-fin-chantier',
            'code' => 'CLN-FDC',
            'name' => 'Nettoyage fin de chantier',
            'icon' => 'broom',
            'short_description' => 'Remise en état après travaux',
            'base_price_cents' => 15000,
            'pricing_unit' => PricingUnit::PER_M2,
            'estimated_duration_min' => 300,
            'min_duration_min' => 180,
            'allows_asap' => false,
            'steps' => [[
                'title' => 'Le chantier à nettoyer',
                'questions' => [
                    [
                        'code' => 'surface_m2',
                        'label' => 'Quelle surface au sol ?',
                        'type' => QuestionType::SURFACE,
                        'is_required' => true,
                        'validation' => ['min' => 10, 'max' => 600, 'step' => 5, 'unit' => 'm²'],
                        'pricing' => ['mode' => PriceImpactMode::PER_UNIT, 'coefficient' => 180, 'unit' => 'm²'],
                        'duration_impact_min' => 2,
                        'display' => ['layout' => 'slider'],
                    ],
                    [
                        'code' => 'etages',
                        'label' => 'Combien d’étages ?',
                        'type' => QuestionType::COUNTER,
                        'validation' => ['min' => 0, 'max' => 10, 'step' => 1],
                        'pricing' => ['mode' => PriceImpactMode::PER_UNIT, 'coefficient' => 2500],
                        'duration_impact_min' => 30,
                        'display' => ['layout' => 'counter'],
                    ],
                    [
                        'code' => 'ascenseur',
                        'label' => 'Y a-t-il un ascenseur ?',
                        'type' => QuestionType::BOOLEAN,
                        'display' => ['layout' => 'chips'],
                        // Sans étage, la question n'a pas lieu d'être posée.
                        'show_if' => ['code' => 'etages', 'operator' => ConditionOperator::GT, 'value' => 0],
                        'options' => [
                            ['label' => 'Oui', 'value' => 'oui', 'is_default' => true],
                            ['label' => 'Non', 'value' => 'non', 'price_modifier_cents' => 3000, 'duration_modifier_min' => 45],
                        ],
                    ],
                    [
                        'code' => 'gravats',
                        'label' => 'Reste-t-il des gravats à évacuer ?',
                        'type' => QuestionType::SINGLE_CHOICE,
                        'display' => ['layout' => 'cards', 'columns' => 1],
                        'options' => [
                            ['label' => 'Aucun', 'value' => 'aucun', 'is_default' => true],
                            ['label' => 'Quelques sacs', 'value' => 'legers', 'price_modifier_cents' => 4000, 'duration_modifier_min' => 45],
                            ['label' => 'Un volume important', 'value' => 'importants', 'price_modifier_cents' => 15000, 'duration_modifier_min' => 120],
                        ],
                    ],
                    [
                        'code' => 'vitres',
                        'label' => 'Faut-il nettoyer les vitres ?',
                        'type' => QuestionType::BOOLEAN,
                        'display' => ['layout' => 'chips'],
                        'options' => [
                            ['label' => 'Oui', 'value' => 'oui', 'price_multiplier' => 1.2, 'duration_modifier_min' => 60],
                            ['label' => 'Non', 'value' => 'non', 'is_default' => true],
                        ],
                    ],
                    $this->photoQuestion('Une photo du chantier ?', 'Le volume de poussière se voit mieux qu’il ne se décrit.'),
                ],
            ]],
        ];
    }

    private function nettoyageDomicile(): array
    {
        return [
            'slug' => 'nettoyage',
            'code' => 'CLN',
            'name' => 'Nettoyage à domicile',
            'icon' => 'sparkles',
            'short_description' => 'Ponctuel ou régulier, chez vous',
            'base_price_cents' => 4500,
            'pricing_unit' => PricingUnit::PER_HOUR,
            'estimated_duration_min' => 120,
            'min_duration_min' => 120,
            'allows_asap' => true,
            'steps' => [[
                'title' => 'Votre logement',
                'questions' => [
                    [
                        'code' => 'surface_m2',
                        'label' => 'Quelle surface ?',
                        'type' => QuestionType::SURFACE,
                        'is_required' => true,
                        'is_essential' => true,
                        'validation' => ['min' => 15, 'max' => 400, 'step' => 5, 'unit' => 'm²'],
                        'pricing' => ['mode' => PriceImpactMode::PER_UNIT, 'coefficient' => 45, 'unit' => 'm²'],
                        'duration_impact_min' => 1,
                        'display' => ['layout' => 'slider'],
                    ],
                    [
                        'code' => 'frequence',
                        'label' => 'À quelle fréquence ?',
                        'type' => QuestionType::SINGLE_CHOICE,
                        'is_essential' => true,
                        'display' => ['layout' => 'cards', 'columns' => 1],
                        'options' => [
                            ['label' => 'Une seule fois', 'value' => 'ponctuel', 'is_default' => true],
                            ['label' => 'Chaque semaine', 'value' => 'hebdo', 'price_multiplier' => 0.9],
                            ['label' => 'Toutes les deux semaines', 'value' => 'bimensuel', 'price_multiplier' => 0.95],
                        ],
                    ],
                    [
                        'code' => 'produits',
                        'label' => 'Qui fournit les produits ?',
                        'type' => QuestionType::SINGLE_CHOICE,
                        'display' => ['layout' => 'chips'],
                        'options' => [
                            ['label' => 'J’ai tout sur place', 'value' => 'client', 'is_default' => true],
                            ['label' => 'Le prestataire apporte', 'value' => 'prestataire', 'price_modifier_cents' => 1200],
                        ],
                    ],
                    [
                        'code' => 'animaux',
                        'label' => 'Des animaux à la maison ?',
                        'type' => QuestionType::BOOLEAN,
                        'display' => ['layout' => 'chips'],
                        'options' => [
                            ['label' => 'Oui', 'value' => 'oui', 'duration_modifier_min' => 20],
                            ['label' => 'Non', 'value' => 'non', 'is_default' => true],
                        ],
                    ],
                    [
                        'code' => 'presence',
                        'label' => 'Serez-vous présent ?',
                        'type' => QuestionType::SINGLE_CHOICE,
                        'display' => ['layout' => 'chips'],
                        'options' => [
                            ['label' => 'Oui', 'value' => 'present', 'is_default' => true],
                            ['label' => 'Non, je confie les clés', 'value' => 'cles'],
                        ],
                    ],
                    $this->photoQuestion('Une photo du logement ?', 'Elle aide à estimer le temps réellement nécessaire.'),
                ],
            ]],
        ];
    }

    private function vitrerie(): array
    {
        return [
            'slug' => 'vitrerie',
            'code' => 'CLN-VIT',
            'name' => 'Nettoyage de vitres',
            'icon' => 'window',
            'short_description' => 'Vitres, baies et vérandas',
            'base_price_cents' => 6000,
            'pricing_unit' => PricingUnit::PER_UNIT,
            'estimated_duration_min' => 90,
            'allows_asap' => false,
            'steps' => [[
                'title' => 'Vos vitres',
                'questions' => [
                    [
                        'code' => 'nb_fenetres',
                        'label' => 'Combien de fenêtres ?',
                        'type' => QuestionType::COUNTER,
                        'is_required' => true,
                        'validation' => ['min' => 1, 'max' => 60, 'step' => 1],
                        'pricing' => ['mode' => PriceImpactMode::PER_UNIT, 'coefficient' => 800],
                        'duration_impact_min' => 6,
                        'display' => ['layout' => 'counter'],
                    ],
                    [
                        'code' => 'faces',
                        'label' => 'Intérieur, extérieur, ou les deux ?',
                        'type' => QuestionType::SINGLE_CHOICE,
                        'display' => ['layout' => 'chips'],
                        'options' => [
                            ['label' => 'Les deux', 'value' => 'deux', 'is_default' => true],
                            ['label' => 'Intérieur seulement', 'value' => 'interieur', 'price_multiplier' => 0.6],
                            ['label' => 'Extérieur seulement', 'value' => 'exterieur', 'price_multiplier' => 0.7],
                        ],
                    ],
                    [
                        'code' => 'hauteur',
                        'label' => 'Des vitres en hauteur ?',
                        'type' => QuestionType::SINGLE_CHOICE,
                        'display' => ['layout' => 'cards', 'columns' => 1],
                        'options' => [
                            ['label' => 'Toutes accessibles', 'value' => 'accessible', 'is_default' => true],
                            ['label' => 'Escabeau nécessaire', 'value' => 'escabeau', 'price_modifier_cents' => 2000],
                            ['label' => 'Nacelle nécessaire', 'value' => 'nacelle', 'price_modifier_cents' => 25000],
                        ],
                    ],
                    $this->photoQuestion('Une photo de la façade ?', 'Elle montre la hauteur mieux qu’un chiffre.'),
                ],
            ]],
        ];
    }

    // ─── Espaces verts ───────────────────────────────────────────────────────────────────────

    private function jardinage(): array
    {
        return [
            'slug' => 'jardinage',
            'code' => 'GRD',
            'name' => 'Jardinage',
            'icon' => 'leaf',
            'short_description' => 'Tonte, haies, désherbage',
            'base_price_cents' => 5500,
            'pricing_unit' => PricingUnit::PER_HOUR,
            'estimated_duration_min' => 120,
            'allows_asap' => false,
            'steps' => [[
                'title' => 'Votre jardin',
                'questions' => [
                    [
                        'code' => 'surface_m2',
                        'label' => 'Quelle surface de jardin ?',
                        'type' => QuestionType::SURFACE,
                        'is_required' => true,
                        'validation' => ['min' => 20, 'max' => 3000, 'step' => 10, 'unit' => 'm²'],
                        'pricing' => ['mode' => PriceImpactMode::PER_UNIT, 'coefficient' => 25, 'unit' => 'm²'],
                        'duration_impact_min' => 1,
                        'display' => ['layout' => 'slider'],
                    ],
                    [
                        'code' => 'prestations',
                        'label' => 'Que faut-il faire ?',
                        'type' => QuestionType::MULTI_CHOICE,
                        'is_required' => true,
                        'display' => ['layout' => 'cards', 'columns' => 2],
                        'options' => [
                            ['label' => 'Tonte', 'value' => 'tonte', 'is_default' => true],
                            ['label' => 'Taille de haies', 'value' => 'haies', 'price_modifier_cents' => 4000, 'duration_modifier_min' => 60],
                            ['label' => 'Désherbage', 'value' => 'desherbage', 'price_modifier_cents' => 3000, 'duration_modifier_min' => 45],
                            ['label' => 'Ramassage de feuilles', 'value' => 'feuilles', 'price_modifier_cents' => 2500, 'duration_modifier_min' => 30],
                        ],
                    ],
                    [
                        'code' => 'evacuation',
                        'label' => 'Faut-il évacuer les déchets verts ?',
                        'type' => QuestionType::BOOLEAN,
                        'display' => ['layout' => 'chips'],
                        'options' => [
                            ['label' => 'Oui', 'value' => 'oui', 'price_modifier_cents' => 4500, 'duration_modifier_min' => 30],
                            ['label' => 'Non, je m’en occupe', 'value' => 'non', 'is_default' => true],
                        ],
                    ],
                    [
                        'code' => 'acces_jardin',
                        'label' => 'L’accès au jardin est-il praticable ?',
                        'type' => QuestionType::SINGLE_CHOICE,
                        'display' => ['layout' => 'chips'],
                        'options' => [
                            ['label' => 'Accès direct', 'value' => 'direct', 'is_default' => true],
                            ['label' => 'Par la maison', 'value' => 'maison', 'duration_modifier_min' => 20],
                        ],
                    ],
                    $this->photoQuestion('Une photo du jardin ?', 'La surface et l’état de la végétation se voient d’un coup d’œil.'),
                ],
            ]],
        ];
    }

    private function elagage(): array
    {
        return [
            'slug' => 'elagage',
            'code' => 'GRD-ELG',
            'name' => 'Élagage',
            'icon' => 'tree',
            'short_description' => 'Grands arbres, abattage, sécurisation',
            'pricing_unit' => PricingUnit::QUOTE_ONLY,
            'estimated_duration_min' => 240,
            'requires_certification' => true,
            'allows_asap' => false,
            'steps' => [[
                'title' => 'Vos arbres',
                'questions' => [
                    [
                        'code' => 'nb_arbres',
                        'label' => 'Combien d’arbres ?',
                        'type' => QuestionType::COUNTER,
                        'is_required' => true,
                        'validation' => ['min' => 1, 'max' => 50, 'step' => 1],
                        'display' => ['layout' => 'counter'],
                    ],
                    [
                        'code' => 'hauteur_arbre',
                        'label' => 'Quelle hauteur environ ?',
                        'type' => QuestionType::SINGLE_CHOICE,
                        'is_required' => true,
                        'display' => ['layout' => 'cards', 'columns' => 1],
                        'options' => [
                            ['label' => 'Moins de 5 mètres', 'value' => 'petit', 'is_default' => true],
                            ['label' => 'Entre 5 et 15 mètres', 'value' => 'moyen'],
                            ['label' => 'Plus de 15 mètres', 'value' => 'grand'],
                        ],
                    ],
                    [
                        'code' => 'nature_travaux',
                        'label' => 'Que faut-il faire ?',
                        'type' => QuestionType::SINGLE_CHOICE,
                        'display' => ['layout' => 'cards', 'columns' => 1],
                        'options' => [
                            ['label' => 'Élaguer', 'value' => 'elagage', 'is_default' => true],
                            ['label' => 'Abattre', 'value' => 'abattage'],
                            ['label' => 'Abattre et dessoucher', 'value' => 'dessouchage'],
                        ],
                    ],
                    [
                        'code' => 'proximite',
                        'label' => 'L’arbre est-il proche d’un bâtiment ou d’une ligne ?',
                        'help_text' => 'Cela impose un démontage par sections, plus long et plus technique.',
                        'type' => QuestionType::BOOLEAN,
                        'display' => ['layout' => 'chips'],
                        'options' => [
                            ['label' => 'Oui', 'value' => 'oui'],
                            ['label' => 'Non, dégagé', 'value' => 'non', 'is_default' => true],
                        ],
                    ],
                    $this->photoQuestion('Une photo de l’arbre ?', 'Elle est indispensable pour chiffrer un élagage.'),
                ],
            ]],
        ];
    }

    /**
     * La question photo, identique partout.
     *
     * Jamais obligatoire : c'est un RACCOURCI offert au client, pas un péage. Une photo permet au
     * prestataire de comprendre en deux secondes ce que dix questions décrivent mal — mais
     * l'exiger transformerait l'aide en obstacle.
     */
    private function photoQuestion(string $label, string $help): array
    {
        return [
            'code' => 'photos',
            'label' => $label,
            'help_text' => $help,
            'type' => QuestionType::PHOTO,
            'is_required' => false,
            'display' => ['layout' => 'cards'],
        ];
    }
}
