<?php

namespace App\Http\Middleware;

use App\Models\AssistantApiLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 5.1 — Rate limiting pour les appels assistant LLM.
 *
 * Limites configurables via .env :
 *   - ASSISTANT_RATE_PER_HOUR (défaut 30 messages/heure/user)
 *   - ASSISTANT_RATE_PER_DAY  (défaut 200 messages/jour/user)
 *   - ASSISTANT_COST_LIMIT_USD_PER_DAY (défaut $1.00/jour/user)
 *
 * À utiliser sur les routes/Livewire actions qui déclenchent un appel LLM
 * (AssistantWidget::send, et tous les futurs endpoints API).
 *
 * Usage :
 *   Route::middleware(['auth', 'assistant.ratelimit'])->group(...)
 *
 * Et register dans Kernel.php :
 *   protected $middlewareAliases = [
 *       'assistant.ratelimit' => \App\Http\Middleware\AssistantRateLimit::class,
 *   ];
 */
class AssistantRateLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $perHour    = (int) config('services.assistant.rate_per_hour', 30);
        $perDay     = (int) config('services.assistant.rate_per_day', 200);
        $costPerDay = (float) config('services.assistant.cost_limit_usd_per_day', 1.0);

        // 1) Limite horaire
        $hourCount = AssistantApiLog::query()
            ->forUser($user->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($hourCount >= $perHour) {
            return $this->limited(
                "Tu as atteint la limite de {$perHour} messages par heure. Réessaye dans une heure.",
                'hour'
            );
        }

        // 2) Limite quotidienne
        $dayCount = AssistantApiLog::query()
            ->forUser($user->id)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($dayCount >= $perDay) {
            return $this->limited(
                "Tu as atteint la limite de {$perDay} messages par jour.",
                'day'
            );
        }

        // 3) Plafond de coût quotidien
        $dailyCost = (float) AssistantApiLog::query()
            ->forUser($user->id)
            ->where('created_at', '>=', now()->subDay())
            ->sum('cost_usd');

        if ($dailyCost >= $costPerDay) {
            return $this->limited(
                "Plafond de coût quotidien atteint (\$" . number_format($costPerDay, 2) . "). Réessaye demain.",
                'cost'
            );
        }

        return $next($request);
    }

    private function limited(string $message, string $reason): Response
    {
        if (request()->expectsJson()) {
            return response()->json([
                'error'         => 'rate_limit_exceeded',
                'reason'        => $reason,
                'message'       => $message,
            ], 429);
        }

        // Fallback non-JSON : redirect avec flash
        return redirect()->back()->with('error', $message);
    }
}
