<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * Proxies de confiance de l'application.
     *
     * La valeur vient de config/trustedproxy.php, qui normalise TRUSTED_PROXIES :
     * "*" (n'importe quel proxy), une liste CIDR, ou null. On passe par la config
     * et non par env(), qui rend null une fois `config:cache` exécuté.
     *
     * Rien de configuré => la propriété RESTE à null. Ce n'est pas un tableau
     * vide : seul null laisse le framework faire confiance automatiquement aux
     * plateformes gérées (Laravel Cloud, Forge, Vapor).
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
