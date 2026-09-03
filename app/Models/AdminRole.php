<?php

namespace App\Models;

use App\Services\Admin\GestionDesRolesService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * UN RÔLE D'ADMINISTRATION — un paquet de capacités qu'on donne d'un geste.
 *
 * Vingt et une capacités se cochent mal une par une, et se recopient encore plus mal d'un
 * administrateur au suivant. Le rôle les nomme une fois.
 *
 * IL NE CRÉE AUCUNE CAPACITÉ. Ses clés sont exactement celles de
 * {@see User::allowedAdminPermissions()} : une clé inventée ici n'ouvrirait rien,
 * et {@see GestionDesRolesService} la refuse à l'écriture.
 */
class AdminRole extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'permissions', 'access_scope'];

    protected $casts = [
        'permissions' => 'array',
    ];

    /** @return HasMany<User, $this> */
    public function utilisateurs(): HasMany
    {
        return $this->hasMany(User::class, 'admin_role_id');
    }

    /**
     * LES CAPACITÉS, TOUJOURS SOUS FORME DE LISTE.
     *
     * La colonne est castée en tableau, mais une écriture doublement encodée — un import, une
     * console de base — rendrait une chaîne. On la remet à plat plutôt que de la croire.
     *
     * @return list<string>
     */
    public function capacites(): array
    {
        // `getAttribute()` ET NON LA PROPRIETE : le cast promet un tableau, mais une ecriture
        // doublement encodee — un import, une console de base — rend une chaine que le cast
        // ne rattrape pas. La lecture brute laisse la defense ci-dessous faire son travail.
        $brut = $this->getAttribute('permissions') ?? [];

        if (is_string($brut)) {
            $decode = json_decode($brut, true);
            $brut = is_array($decode) ? $decode : [];
        }

        return is_array($brut) ? array_values(array_filter($brut, 'is_string')) : [];
    }

    public static function slugPour(string $nom): string
    {
        return Str::slug($nom) ?: 'role-'.Str::random(6);
    }
}
