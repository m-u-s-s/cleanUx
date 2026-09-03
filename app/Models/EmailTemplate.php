<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'description', 'category',
        'subject_pattern', 'preheader', 'body_html_pattern', 'body_text_pattern',
        // LE DOCUMENT EN BLOCS et le theme impose : le contenu d un cote, l habillage de l autre.
        'blocks', 'email_theme_id',
        'locale_overrides', 'required_variables',
        'is_active', 'metadata',
    ];

    protected $casts = [
        'blocks' => 'array',
        'locale_overrides' => 'array',
        'required_variables' => 'array',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** @return HasMany<EmailSendRule, $this> */
    public function sendRules(): HasMany
    {
        return $this->hasMany(EmailSendRule::class);
    }

    /** @return BelongsTo<EmailTheme, $this> */
    public function emailTheme(): BelongsTo
    {
        return $this->belongsTo(EmailTheme::class);
    }

    /**
     * Les blocs de ce gabarit pour cette langue, ou ceux de la langue de reference.
     *
     * @return list<array<string, mixed>>
     */
    public function blocsPourLaLangue(?string $locale = null): array
    {
        if ($locale && isset($this->locale_overrides[$locale]['blocks'])) {
            return array_values((array) $this->locale_overrides[$locale]['blocks']);
        }

        return array_values((array) ($this->blocks ?? []));
    }

    public function subjectForLocale(?string $locale = null): string
    {
        if ($locale && isset($this->locale_overrides[$locale]['subject'])) {
            return $this->locale_overrides[$locale]['subject'];
        }

        return $this->subject_pattern;
    }

    public function bodyHtmlForLocale(?string $locale = null): string
    {
        if ($locale && isset($this->locale_overrides[$locale]['body_html'])) {
            return $this->locale_overrides[$locale]['body_html'];
        }

        return $this->body_html_pattern;
    }
}
