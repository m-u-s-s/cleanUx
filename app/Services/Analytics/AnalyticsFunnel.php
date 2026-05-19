<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use Illuminate\Support\Facades\DB;

/**
 * Funnel analyzer — calcule un funnel ordonné d'event_names sur une période.
 *
 * Usage :
 *   AnalyticsFunnel::for($from, $to)
 *     ->steps(['search.performed', 'provider.viewed', 'booking.started', 'booking.confirmed'])
 *     ->groupBy('user_id')  // ou 'session_id'
 *     ->compute();
 *
 * Retourne un tableau ordonné de :
 *   ['step' => 'search.performed', 'count' => N, 'rate_from_start' => 1.0, 'rate_from_prev' => 1.0]
 *   ...
 */
class AnalyticsFunnel
{
    /** @var array<int,string> */
    protected array $steps = [];
    protected string $groupBy = 'user_id';
    protected \DateTimeInterface $from;
    protected \DateTimeInterface $to;

    public static function for(\DateTimeInterface $from, \DateTimeInterface $to): self
    {
        $i = new self();
        $i->from = $from;
        $i->to = $to;
        return $i;
    }

    public function steps(array $names): self
    {
        $this->steps = array_values(array_filter($names, fn ($s) => is_string($s) && $s !== ''));
        return $this;
    }

    public function groupBy(string $col): self
    {
        if (! in_array($col, ['user_id', 'session_id', 'anonymous_id'], true)) {
            throw new \InvalidArgumentException('groupBy must be user_id|session_id|anonymous_id');
        }
        $this->groupBy = $col;
        return $this;
    }

    /**
     * @return array<int, array{step:string, count:int, rate_from_start:float, rate_from_prev:float}>
     */
    public function compute(): array
    {
        if (empty($this->steps)) {
            return [];
        }

        $results = [];
        $startCount = null;
        $prevCount = null;

        // For each step, count distinct group-by-keys that triggered that event in window.
        // Note: this is a "ever-did" funnel (not strict order). For strict ordered funnels,
        // a windowed SQL would be required — out of scope for the initial helper.
        foreach ($this->steps as $name) {
            $count = AnalyticsEvent::query()
                ->between($this->from, $this->to)
                ->named($name)
                ->whereNotNull($this->groupBy)
                ->distinct($this->groupBy)
                ->count($this->groupBy);

            $startCount ??= $count;

            $rateFromStart = $startCount > 0 ? round($count / $startCount, 4) : 0.0;
            $rateFromPrev = $prevCount === null
                ? 1.0
                : ($prevCount > 0 ? round($count / $prevCount, 4) : 0.0);

            $results[] = [
                'step' => $name,
                'count' => (int) $count,
                'rate_from_start' => $rateFromStart,
                'rate_from_prev' => $rateFromPrev,
            ];

            $prevCount = $count;
        }

        return $results;
    }
}
