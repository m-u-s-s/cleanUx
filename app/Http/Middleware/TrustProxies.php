<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     * env('TRUSTED_PROXIES'): "*" (any proxy), "10.0.0.0/8,172.16.0.0/12" (CIDR list),
     * or null (default: trust LB but fail without it). Use "*" derrière Cloudflare/ALB.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    public function __construct()
    {
        $env = env('TRUSTED_PROXIES');
        if ($env === '*') {
            $this->proxies = '*';
        } elseif ($env) {
            $this->proxies = array_map('trim', explode(',', $env));
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
