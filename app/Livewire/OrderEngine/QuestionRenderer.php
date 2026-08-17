<?php

namespace App\Livewire\OrderEngine;

use App\Models\Question;
use App\Services\GeolocationV2\AddressSuggestion;
use App\Services\GeolocationV2\GeocodingService;
use App\Support\Domain\QuestionType;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Rend UNE question, depuis la base, sans qu'une ligne de son contenu soit écrite en dur.
 *
 * Le même composant sert le parcours client et l'aperçu du constructeur d'administration. C'est
 * délibéré et c'est la seule façon de tenir la promesse « ce que voit l'administrateur est
 * exactement ce que verra le client » : un aperçu qui réimplémenterait le rendu finirait par
 * mentir, et personne ne s'en apercevrait avant la mise en ligne.
 *
 * La délégation par type passe par des PARTIELS Blade, pas par des sous-composants Livewire. Sept
 * questions à l'écran feraient sinon sept composants, donc sept états serveur et autant
 * d'allers-retours pour un simple changement de réponse. Le partiel rend le même balisage sans
 * ce coût.
 *
 * Ce composant ne connaît ni le prix, ni la commande, ni l'étape suivante : il émet la réponse et
 * le parent en fait ce qu'il veut. C'est ce qui lui permet de vivre dans deux contextes qui n'ont
 * rien à voir.
 */
class QuestionRenderer extends Component
{
    public Question $question;

    /** Réponse courante. Scalaire, ou tableau pour un choix multiple. */
    public mixed $value = null;

    /** La porte de sortie a été empruntée : « je ne sais pas, à évaluer sur place ». */
    public bool $unknown = false;

    /**
     * Le champ a-t-il été quitté au moins une fois ?
     *
     * Aucune erreur ne s'affiche avant. Reprocher un champ vide à quelqu'un qui n'a pas fini de le
     * remplir est la façon la plus sûre de lui donner tort pendant qu'il a raison.
     */
    public bool $touched = false;

    public ?string $error = null;

    /** Aperçu du constructeur : rien n'est persisté, mais le rendu est identique au vrai. */
    public bool $preview = false;

    /** Assistant de calcul de surface : longueur × largeur, pour ceux qui n'ont pas les m² en tête. */
    public ?float $helperLength = null;

    public ?float $helperWidth = null;

    /**
     * Ce que le client TAPE dans un champ de localisation, distinct de ce qu'il a CHOISI.
     *
     * Les deux ne se confondent pas : la réponse d'une localisation est un point — libellé,
     * latitude, longitude — et une frappe en cours n'en est pas un. Les mélanger produirait une
     * réponse à moitié écrite, sans coordonnées, que la commande accepterait puis que le dispatch
     * ne saurait pas servir.
     */
    public string $locationQuery = '';

    public function mount(Question $question, mixed $value = null, bool $unknown = false, bool $preview = false): void
    {
        $this->question = $question->loadMissing('options');
        $this->preview = $preview;
        $this->unknown = $unknown;

        /*
         * Le défaut intelligent (loi 5) : la réponse la plus fréquente est déjà là, et c'est
         * l'administrateur qui l'a désignée. Le client valide plus qu'il ne remplit.
         *
         * Il n'écrase JAMAIS une réponse existante — sans quoi revenir en arrière effacerait ce
         * qu'on vient de saisir, ce qui est précisément ce que la loi 10 interdit.
         */
        $this->value = $value ?? $this->defaultValue();

        // Revenir en arrière doit retrouver le lieu déjà choisi, écrit dans le champ : le laisser
        // vide donnerait à croire que la réponse a été perdue, et ferait tout ressaisir.
        if ($this->question->isLocation() && is_array($this->value)) {
            $this->locationQuery = (string) ($this->value['label'] ?? '');
        }
    }

    // ─── Localisation ────────────────────────────────────────────────────────────────────────

    /**
     * Les propositions d'adresse, pendant la frappe.
     *
     * Le géocodage échoue EN SILENCE sur ce produit, par conception : une faute de frappe ne doit
     * jamais bloquer une commande. Mais sur un trajet, une adresse mal située ne coûte pas une
     * phrase rassurante — elle envoie une voiture au mauvais endroit. Les propositions sont donc le
     * chemin normal, et la saisie libre le repli.
     *
     * @return list<AddressSuggestion>
     */
    #[Computed]
    public function locationSuggestions(): array
    {
        $tape = trim($this->locationQuery);

        if (! $this->question->isLocation() || mb_strlen($tape) < 3) {
            return [];
        }

        try {
            return array_values(array_filter(
                app(GeocodingService::class)->autocomplete(
                    $tape,
                    Config::get('order_engine.geocoding_country', 'BE'),
                    5,
                ),
                fn (AddressSuggestion $s) => $s->description !== ($this->value['label'] ?? null),
            ));
        } catch (\Throwable $e) {
            // Un service de suggestions en panne fait perdre un confort, jamais la commande.
            Log::warning('[order_engine] suggestions de localisation indisponibles', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function updatedLocationQuery(): void
    {
        unset($this->locationSuggestions);
    }

    /**
     * Le client retient une proposition : elle porte déjà sa position.
     *
     * Quand elle ne la porte pas — certains fournisseurs ne renvoient qu'un libellé —, on géocode.
     * Sans coordonnées, une localisation n'est qu'une chaîne de caractères, et tout ce qui suit
     * (distance, prix au kilomètre, itinéraire, zone) devient impossible.
     */
    public function chooseLocation(string $description, ?float $lat = null, ?float $lng = null, ?string $postalCode = null): void
    {
        $this->locationQuery = $description;
        $this->touched = true;
        $this->unknown = false;

        if ($lat === null || $lng === null) {
            try {
                $resultat = app(GeocodingService::class)->geocode(
                    $description,
                    Config::get('order_engine.geocoding_country', 'BE'),
                );

                $lat = $resultat?->latitude;
                $lng = $resultat?->longitude;
                $postalCode ??= $resultat?->postalCode;
            } catch (\Throwable $e) {
                Log::warning('[order_engine] localisation non géocodée', ['error' => $e->getMessage()]);
            }
        }

        $this->applyLocation($description, $lat, $lng, $postalCode);
    }

    /**
     * « Utiliser ma position » — sur un trajet, c'est le geste principal, pas un raccourci.
     *
     * Le départ d'une course est presque toujours là où se trouve le téléphone. Les coordonnées sont
     * retenues MÊME si le serveur ne sait pas les nommer : ce sont elles qui comptent, le libellé
     * n'est qu'un confort de lecture.
     */
    public function useMyLocation(float $lat, float $lng): void
    {
        $libelle = null;
        $codePostal = null;

        try {
            $resultat = app(GeocodingService::class)->reverseGeocode($lat, $lng);
            $libelle = $resultat?->formattedAddress;
            $codePostal = $resultat?->postalCode;
        } catch (\Throwable $e) {
            Log::warning('[order_engine] position non nommable', ['error' => $e->getMessage()]);
        }

        $libelle ??= sprintf('Position (%.5f, %.5f)', $lat, $lng);
        $this->locationQuery = $libelle;
        $this->touched = true;
        $this->unknown = false;

        $this->applyLocation($libelle, $lat, $lng, $codePostal);
    }

    /**
     * UN POINT POSÉ SUR LA CARTE, arrivé depuis le parcours.
     *
     * Le même traitement que « utiliser ma position », et c'est délibéré : géocodage inverse pour
     * le libellé, coordonnées retenues même si le serveur ne sait pas les nommer, validation,
     * remontée de la réponse. Réécrire ces quatre étapes ici en ferait une seconde version qui
     * divergerait — et deux façons d'enregistrer un lieu, c'est deux façons de le perdre.
     *
     * LE FILTRAGE PAR CODE EST INDISPENSABLE. Chaque question du parcours est une instance du même
     * composant : l'événement leur parvient à toutes. Sans ce garde, poser le point d'arrivée
     * écraserait aussi le point de départ, et le client verrait ses deux marqueurs se superposer.
     */
    #[On('place-location')]
    public function placerDepuisLaCarte(string $code, float $lat, float $lng): void
    {
        if ($code !== $this->question->code) {
            return;
        }

        $this->useMyLocation($lat, $lng);
    }

    /** Effacer un lieu choisi — pour en désigner un autre, sans avoir à sélectionner le texte. */
    public function clearLocation(): void
    {
        $this->locationQuery = '';
        $this->value = null;
        $this->touched = true;
        unset($this->locationSuggestions);
        $this->validateAnswer();
        $this->emitAnswer();
    }

    /**
     * La forme d'une réponse de localisation — c'est un CONTRAT.
     *
     * `lat` et `lng` sont ce que lisent le calcul de distance, la résolution de zone et le suivi de
     * mission. Changer ces clés ici sans les changer là-bas ferait silencieusement disparaître le
     * point de la carte, sans qu'aucune validation ne s'en plaigne.
     */
    private function applyLocation(string $label, ?float $lat, ?float $lng, ?string $postalCode): void
    {
        $this->value = [
            'label' => $label,
            'lat' => $lat,
            'lng' => $lng,
            'postal_code' => $postalCode,
        ];

        unset($this->locationSuggestions);
        $this->validateAnswer();
        $this->emitAnswer();
    }

    /** Livewire appelle ceci à chaque frappe : c'est le moment où la question devient « touchée ». */
    public function updatedValue(): void
    {
        $this->touched = true;
        $this->unknown = false;
        $this->validateAnswer();
        $this->emitAnswer();
    }

    /**
     * « Je ne sais pas — à évaluer sur place ».
     *
     * Ne bloque rien et n'efface rien : la réponse prend la forme que le moteur tarifaire attend
     * pour élargir la fourchette entre l'option la moins chère et la plus chère.
     */
    public function markUnknown(): void
    {
        $this->unknown = true;
        $this->value = null;
        $this->error = null;
        $this->touched = true;
        $this->emitAnswer();
    }

    /** Compteurs : le pas vient de la question, jamais d'une constante écrite ici. */
    public function increment(): void
    {
        $bounds = $this->bounds();
        $step = (float) ($this->question->validation['step'] ?? 1);
        $next = (float) ($this->value ?? $bounds['min'] ?? 0) + $step;

        $this->value = $bounds['max'] !== null ? min($next, (float) $bounds['max']) : $next;
        $this->updatedValue();
    }

    public function decrement(): void
    {
        $bounds = $this->bounds();
        $step = (float) ($this->question->validation['step'] ?? 1);
        $next = (float) ($this->value ?? 0) - $step;

        $this->value = $bounds['min'] !== null ? max($next, (float) $bounds['min']) : $next;
        $this->updatedValue();
    }

    /** L'assistant de surface : deux mesures faciles valent mieux qu'un m² deviné. */
    public function applySurfaceHelper(): void
    {
        if ($this->helperLength > 0 && $this->helperWidth > 0) {
            $this->value = round($this->helperLength * $this->helperWidth, 1);
            $this->updatedValue();
        }
    }

    /** Bascule une option d'un choix multiple. */
    public function toggleOption(string $optionValue): void
    {
        $current = is_array($this->value) ? $this->value : array_filter([$this->value]);

        $this->value = in_array($optionValue, $current, true)
            ? array_values(array_diff($current, [$optionValue]))
            : array_values(array_merge($current, [$optionValue]));

        $this->updatedValue();
    }

    /**
     * Validation bienveillante.
     *
     * Le message dit QUOI FAIRE, pas seulement ce qui ne va pas : « Indiquez une surface entre 5
     * et 400 m² » plutôt que « valeur invalide ». Un message qui décrit le problème sans indiquer
     * l'issue laisse la personne exactement où elle était.
     */
    public function validateAnswer(): void
    {
        $this->error = null;

        if ($this->unknown) {
            return;
        }

        /*
         * UNE LOCALISATION SANS COORDONNÉES N'EST PAS UNE RÉPONSE.
         *
         * Un libellé seul passerait la garde du « champ rempli » et laisserait la commande aller
         * jusqu'au bout — puis le calcul de distance rendrait zéro, le prix au kilomètre serait
         * gratuit et la carte n'aurait aucun point à afficher. Le refus est posé ici, au moment où
         * la personne peut encore corriger, plutôt qu'à la confirmation où il ne reste qu'à
         * recommencer.
         */
        if ($this->question->isLocation()) {
            $situee = is_array($this->value)
                && ($this->value['lat'] ?? null) !== null
                && ($this->value['lng'] ?? null) !== null;

            if (! $situee && ($this->question->is_required || is_array($this->value))) {
                $this->error = 'Choisissez une adresse dans la liste, ou utilisez votre position : nous avons besoin du point exact.';
            }

            return;
        }

        $empty = $this->value === null || $this->value === '' || $this->value === [];

        if ($this->question->is_required && $empty) {
            $this->error = $this->question->allows_unknown
                ? 'Choisissez une réponse, ou indiquez que vous ne savez pas.'
                : 'Cette réponse est nécessaire pour estimer votre commande.';

            return;
        }

        if ($empty || ! $this->question->isNumeric() || ! is_numeric($this->value)) {
            return;
        }

        $bounds = $this->bounds();
        $unit = $this->question->validation['unit'] ?? '';
        $number = (float) $this->value;

        if ($bounds['min'] !== null && $number < (float) $bounds['min']) {
            $this->error = trim(sprintf('Indiquez au moins %s %s.', $bounds['min'], $unit));
        }

        if ($bounds['max'] !== null && $number > (float) $bounds['max']) {
            $this->error = trim(sprintf(
                'Au-delà de %s %s, une visite est nécessaire — indiquez « je ne sais pas ».',
                $bounds['max'],
                $unit,
            ));
        }
    }

    /**
     * La réponse, sous la forme exacte que le moteur tarifaire lit.
     *
     * Cette forme est un CONTRAT : `['unknown' => true]` est ce que PricingEngine reconnaît pour
     * élargir la fourchette. La changer ici sans la changer là-bas ferait silencieusement
     * disparaître la porte de sortie du calcul.
     */
    public function answerPayload(): mixed
    {
        return $this->unknown ? ['unknown' => true] : $this->value;
    }

    protected function emitAnswer(): void
    {
        $this->dispatch(
            'question-answered',
            code: $this->question->code,
            value: $this->answerPayload(),
            valid: $this->error === null,
        );
    }

    protected function defaultValue(): mixed
    {
        if ($this->question->isOptionBased()) {
            return $this->question->defaultOption()?->value;
        }

        $declared = $this->question->default_value;

        return is_array($declared) ? ($declared['value'] ?? null) : $declared;
    }

    /** @return array{min: mixed, max: mixed} */
    protected function bounds(): array
    {
        return $this->question->validationBounds();
    }

    /** Le partiel qui sait rendre ce type. Un type inconnu retombe sur un champ texte plutôt que de casser la page. */
    public function partial(): string
    {
        return match ($this->question->type) {
            QuestionType::SINGLE_CHOICE, QuestionType::MULTI_CHOICE, QuestionType::BOOLEAN => 'choice',
            QuestionType::COUNTER, QuestionType::NUMBER, QuestionType::SURFACE, QuestionType::RANGE => 'numeric',
            QuestionType::DATE, QuestionType::TIME => 'datetime',
            QuestionType::PHOTO => 'photo',
            QuestionType::ADDRESS => 'address',
            QuestionType::LOCATION => 'location',
            default => 'text',
        };
    }

    /** Disposition voulue par l'administrateur : la même question se présente autrement à 2 ou 12 options. */
    public function layout(): string
    {
        return $this->question->display['layout']
            ?? ($this->question->isOptionBased() ? 'cards' : 'slider');
    }

    public function render()
    {
        return view('livewire.order-engine.question-renderer');
    }
}
