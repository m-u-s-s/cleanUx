<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks a request as "embedded" (rendered inside a mobile WebView) when
 * ?embed=1 or the X-Embedded: 1 header is present. Views read the shared
 * `$embedded` flag to drop navigation chrome.
 */
class EmbedMode
{
    public function handle(Request $request, Closure $next): Response
    {
        $embedded = $request->boolean('embed') || $request->header('X-Embedded') === '1';
        View::share('embedded', $embedded);

        return $next($request);
    }
}
