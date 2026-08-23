<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * Proxies de confiance de l'application.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    public function __construct()
    {
        $configured = config('trustedproxy.proxies');

        if (is_string($configured) && $configured !== '') {
            $this->proxies = $configured;
        } elseif (is_array($configured) && $configured !== []) {
            $this->proxies = array_values(array_map('strval', $configured));
        }
    }

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
