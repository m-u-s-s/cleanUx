<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'category',
        'subject_pattern', 'body_html_pattern', 'body_text_pattern',
        'locale_overrides', 'required_variables',
        'is_active', 'metadata',
    ];

    protected $casts = [
        'locale_overrides' => 'array',
        'required_variables' => 'array',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
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
