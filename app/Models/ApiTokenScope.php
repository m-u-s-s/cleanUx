<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ApiTokenScope extends Model
{
    public const CATEGORY_READ = 'read';
    public const CATEGORY_WRITE = 'write';
    public const CATEGORY_ADMIN = 'admin';

    protected $fillable = [
        'code', 'name', 'description', 'category',
        'required_role', 'is_active', 'is_dangerous',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_dangerous' => 'boolean',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeForRole(Builder $q, ?string $role): Builder
    {
        return $q->where(function ($w) use ($role) {
            $w->whereNull('required_role');
            if ($role) {
                $w->orWhere('required_role', $role);
            }
        });
    }
}
