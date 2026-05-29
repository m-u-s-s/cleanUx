<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LogSlowQueries
{
    private const THRESHOLD_MS = 500;

    public function handle(Request $request, Closure $next): mixed
    {
        $queries = [];

        DB::listen(function ($query) use (&$queries) {
            if ($query->time > self::THRESHOLD_MS) {
                $queries[] = [
                    'sql' => $query->sql,
                    'time' => $query->time,
                    'bindings' => $query->bindings,
                ];
            }
        });

        $response = $next($request);

        if (count($queries) > 0) {
            Log::channel('slow-queries')->warning(
                'Slow queries on '.$request->method().' '.$request->path(),
                [
                    'queries' => $queries,
                    'total_count' => count($queries),
                ]
            );
        }

        return $response;
    }
}
