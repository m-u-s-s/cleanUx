<?php

namespace App\Services\Marketing;

use App\Models\MarketingSegment;
use App\Models\MarketingSegmentMember;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SegmentEngine — évalue la DSL d'un segment et matérialise ses membres.
 *
 * DSL JSON (récursive) :
 *   { "and": [ {leaf}, {leaf}, { "or": [...] } ] }
 *   { "or":  [ {leaf}, {leaf} ] }
 *   { "not": {leaf} }
 *
 * Leaf :
 *   { "field": "role", "op": "eq", "value": "client" }
 *
 * Champs whitelistés (config marketing.segment_fields) :
 *   role, locale, country_code, email_domain,
 *   created_at, last_login_at,
 *   bookings_count, last_booking_at, total_spent_cents
 *
 * Operators whitelistés (config marketing.segment_operators) :
 *   eq, neq, in, not_in, gt, gte, lt, lte,
 *   older_than_days, newer_than_days,
 *   is_null, is_not_null, contains, starts_with, ends_with
 *
 * Pour les champs derived (bookings_count, last_booking_at, total_spent_cents),
 * l'engine compile une subquery sur la table bookings (schema-defensive).
 */
class SegmentEngine
{
    public function compute(MarketingSegment $segment): int
    {
        if (! $segment->is_active) {
            return 0;
        }

        $query = $this->buildQuery($segment->rules ?? []);
        if (! $query) {
            return 0;
        }

        $userIds = $query->pluck('users.id')->all();

        DB::transaction(function () use ($segment, $userIds) {
            $segment->memberships()->delete();

            $now = now();
            $rows = array_map(fn ($uid) => [
                'segment_id' => $segment->id,
                'user_id' => $uid,
                'computed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ], $userIds);

            if (! empty($rows)) {
                MarketingSegmentMember::query()->insert($rows);
            }

            $segment->forceFill([
                'member_count' => count($userIds),
                'last_computed_at' => $now,
            ])->save();
        });

        ActivityLogger::log('marketing.segment_computed', $segment, [
            'member_count' => count($userIds),
        ]);

        return count($userIds);
    }

    public function preview(array $rules, int $limit = 25): array
    {
        $query = $this->buildQuery($rules);
        if (! $query) {
            return ['count' => 0, 'sample' => []];
        }

        $count = (clone $query)->count('users.id');
        $sample = $query->limit($limit)->get(['users.id', 'users.email', 'users.name'])->toArray();

        return ['count' => $count, 'sample' => $sample];
    }

    protected function buildQuery(array $rules): ?Builder
    {
        if (empty($rules)) {
            return null;
        }

        $query = User::query()->from('users');

        $hasBookings = Schema::hasTable('bookings');

        $query->where(function ($q) use ($rules, $hasBookings) {
            $this->applyNode($q, $rules, $hasBookings);
        });

        return $query;
    }

    protected function applyNode($q, array $node, bool $hasBookings): void
    {
        if (isset($node['and']) && is_array($node['and'])) {
            $q->where(function ($inner) use ($node, $hasBookings) {
                foreach ($node['and'] as $sub) {
                    $inner->where(function ($w) use ($sub, $hasBookings) {
                        $this->applyNode($w, $sub, $hasBookings);
                    });
                }
            });
            return;
        }

        if (isset($node['or']) && is_array($node['or'])) {
            $q->where(function ($inner) use ($node, $hasBookings) {
                foreach ($node['or'] as $sub) {
                    $inner->orWhere(function ($w) use ($sub, $hasBookings) {
                        $this->applyNode($w, $sub, $hasBookings);
                    });
                }
            });
            return;
        }

        if (isset($node['not'])) {
            $q->whereNot(function ($inner) use ($node, $hasBookings) {
                $this->applyNode($inner, $node['not'], $hasBookings);
            });
            return;
        }

        $this->applyLeaf($q, $node, $hasBookings);
    }

    protected function applyLeaf($q, array $leaf, bool $hasBookings): void
    {
        $field = (string) ($leaf['field'] ?? '');
        $op = (string) ($leaf['op'] ?? '');
        $value = $leaf['value'] ?? null;

        $allowedFields = (array) Config::get('marketing.segment_fields', []);
        $allowedOps = (array) Config::get('marketing.segment_operators', []);

        if (! in_array($field, $allowedFields, true) || ! in_array($op, $allowedOps, true)) {
            // Reject unknown field/op silently — segment never matches anyone if rule is invalid
            $q->whereRaw('1=0');
            return;
        }

        // Derived fields → subqueries on bookings
        if (in_array($field, ['bookings_count', 'last_booking_at', 'total_spent_cents'], true)) {
            if (! $hasBookings) {
                $q->whereRaw('1=0');
                return;
            }
            $this->applyBookingDerivedField($q, $field, $op, $value);
            return;
        }

        // Special leaf : email_domain
        if ($field === 'email_domain') {
            $this->applyOperator($q, 'users.email', $this->wrapForDomain($op, $value), $op);
            return;
        }

        $this->applyOperator($q, 'users.' . $field, $value, $op);
    }

    protected function applyOperator($q, string $column, mixed $value, string $op): void
    {
        switch ($op) {
            case 'eq':
                $q->where($column, '=', $value);
                break;
            case 'neq':
                $q->where($column, '!=', $value);
                break;
            case 'in':
                $q->whereIn($column, (array) $value);
                break;
            case 'not_in':
                $q->whereNotIn($column, (array) $value);
                break;
            case 'gt':
                $q->where($column, '>', $value);
                break;
            case 'gte':
                $q->where($column, '>=', $value);
                break;
            case 'lt':
                $q->where($column, '<', $value);
                break;
            case 'lte':
                $q->where($column, '<=', $value);
                break;
            case 'older_than_days':
                $q->where($column, '<=', now()->subDays((int) $value));
                break;
            case 'newer_than_days':
                $q->where($column, '>=', now()->subDays((int) $value));
                break;
            case 'is_null':
                $q->whereNull($column);
                break;
            case 'is_not_null':
                $q->whereNotNull($column);
                break;
            case 'contains':
                $q->where($column, 'like', '%' . str_replace('%', '\\%', (string) $value) . '%');
                break;
            case 'starts_with':
                $q->where($column, 'like', str_replace('%', '\\%', (string) $value) . '%');
                break;
            case 'ends_with':
                $q->where($column, 'like', '%' . str_replace('%', '\\%', (string) $value));
                break;
        }
    }

    protected function wrapForDomain(string $op, mixed $value): mixed
    {
        // For ends_with / contains, value should already be the domain string.
        return $value;
    }

    protected function applyBookingDerivedField($q, string $field, string $op, mixed $value): void
    {
        // Pick client/customer columns defensively
        $clientCols = array_values(array_filter([
            Schema::hasColumn('bookings', 'client_id') ? 'client_id' : null,
            Schema::hasColumn('bookings', 'customer_user_id') ? 'customer_user_id' : null,
        ]));

        if (empty($clientCols)) {
            $q->whereRaw('1=0');
            return;
        }

        // Build subquery selecting users.id with aggregate
        if ($field === 'bookings_count') {
            $sub = DB::table('bookings')
                ->select(DB::raw('COUNT(*) AS agg'), $clientCols[0] . ' AS uid')
                ->groupBy($clientCols[0]);
            $alias = 'b_count_agg';
            $aggCol = 'agg';
        } elseif ($field === 'last_booking_at') {
            $sub = DB::table('bookings')
                ->select(DB::raw('MAX(created_at) AS agg'), $clientCols[0] . ' AS uid')
                ->groupBy($clientCols[0]);
            $alias = 'b_lastat_agg';
            $aggCol = 'agg';
        } else {  // total_spent_cents
            $col = Schema::hasColumn('bookings', 'final_price') ? 'final_price' :
                   (Schema::hasColumn('bookings', 'payment_amount_cents') ? 'payment_amount_cents' : null);
            if (! $col) {
                $q->whereRaw('1=0');
                return;
            }
            $sub = DB::table('bookings')
                ->select(DB::raw("SUM($col) AS agg"), $clientCols[0] . ' AS uid')
                ->groupBy($clientCols[0]);
            $alias = 'b_spent_agg';
            $aggCol = 'agg';
        }

        $q->leftJoinSub($sub, $alias, function ($join) use ($alias) {
            $join->on('users.id', '=', "{$alias}.uid");
        });

        $this->applyOperator($q, "{$alias}.{$aggCol}", $value, $op);
    }
}
