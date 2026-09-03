<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\EmailThemeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * L'HABILLAGE D'UN E-MAIL, SÉPARÉ DE SON CONTENU.
 *
 * Un thème porte le logo, les couleurs, le fond et la typographie ; un gabarit porte les mots.
 * Changer de saison ne touche donc aucun gabarit — c'est le thème actif qui change.
 *
 * @property ?string $starts_on
 * @property ?string $ends_on
 */
class EmailTheme extends Model
{
    /** @use HasFactory<EmailThemeFactory> */
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'description',
        'is_default', 'is_active',
        'starts_on', 'ends_on', 'recurs_yearly', 'priority',
        'logo_url', 'header_image_url', 'background_image_url',
        'color_accent', 'color_accent_contrast', 'color_page_background',
        'color_card_background', 'color_text', 'color_text_muted', 'color_border',
        'color_banner_from', 'color_banner_to',
        'font_stack', 'corner_radius', 'footer_text', 'metadata',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'recurs_yearly' => 'boolean',
        'priority' => 'integer',
        'corner_radius' => 'integer',
        'starts_on' => 'date:Y-m-d',
        'ends_on' => 'date:Y-m-d',
        'metadata' => 'array',
    ];

    /** @return HasMany<EmailTemplate, $this> */
    public function templates(): HasMany
    {
        return $this->hasMany(EmailTemplate::class);
    }

    /**
     * @param  Builder<self>  $q
     * @return Builder<self>
     */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /**
     * CE THÈME COUVRE-T-IL CETTE DATE ?
     *
     * Sans fenêtre, il est permanent. Avec `recurs_yearly`, l'année est ignorée — ce qui permet
     * à une fenêtre de CHEVAUCHER LE 31 DÉCEMBRE : du 20 décembre au 2 janvier, le début est
     * postérieur à la fin dans l'année civile, et la comparaison doit alors s'inverser.
     */
    public function couvre(CarbonInterface $date): bool
    {
        if ($this->starts_on === null || $this->ends_on === null) {
            return true;
        }

        $debut = $this->dateDe('starts_on');
        $fin = $this->dateDe('ends_on');

        if (! $this->recurs_yearly) {
            return $date->betweenIncluded($debut, $fin);
        }

        $jour = (int) $date->format('md');
        $j1 = (int) $debut->format('md');
        $j2 = (int) $fin->format('md');

        return $j1 <= $j2
            ? $jour >= $j1 && $jour <= $j2
            : $jour >= $j1 || $jour <= $j2;
    }

    /** Eloquent declare deja un `asDate()` protege : ce nom-la lui reste. */
    private function dateDe(string $champ): CarbonInterface
    {
        /** @var CarbonInterface $valeur */
        $valeur = $this->getAttribute($champ);

        return $valeur;
    }
}
