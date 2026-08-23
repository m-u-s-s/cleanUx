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

/** Rend UNE question, depuis la base, sans qu'une ligne de son contenu soit écrite en dur. */
class QuestionRenderer extends Component
{
    public Question $question;

    /** Réponse courante. Scalaire, ou tableau pour un choix multiple. */
    public mixed $value = null;

    /** La porte de sortie a été empruntée : « je ne sais pas, à évaluer sur place ». */
    public bool $unknown = false;

    /** Le champ a-t-il été quitté au moins une fois ? Aucune erreur ne s'affiche avant. */
    public bool $touched = false;

    public ?string $error = null;

    /** Aperçu du constructeur : rien n'est persisté, mais le rendu est identique au vrai. */
    public bool $preview = false;

    /** Assistant de calcul de surface : longueur × largeur, pour ceux qui n'ont pas les m² en tête. */
    public ?float $helperLength = null;

    public ?float $helperWidth = null;

    /** Ce que le client TAPE dans un champ de localisation, distinct de ce qu'il a CHOISI. */
    public string $locationQuery = '';

    public function mount(Question $question, mixed $value = null, bool $unknown = false, bool $preview = false): void
    {
        $this->question = $question->loadMissing('options');
        $this->preview = $preview;
        $this->unknown = $unknown;

        // Le défaut intelligent (loi 5) : la réponse la plus fréquente est déjà là, et c'est l'administrateur qui l'a désignée.
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

    /** Le client retient une proposition : elle porte déjà sa position. */
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

    /** « Utiliser ma position » — sur un trajet, c'est le geste principal, pas un raccourci. */
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

    /** UN POINT POSÉ SUR LA CARTE, arrivé depuis le parcours. */
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

    /** La forme d'une réponse de localisation — c'est un CONTRAT. */
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

    /** « Je ne sais pas — à évaluer sur place ». */
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

    /** Validation bienveillante. */
    public function validateAnswer(): void
    {
        $this->error = null;

        if ($this->unknown) {
            return;
        }

        // UNE LOCALISATION SANS COORDONNÉES N'EST PAS UNE RÉPONSE.
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

    /** La réponse, sous la forme exacte que le moteur tarifaire lit. */
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
