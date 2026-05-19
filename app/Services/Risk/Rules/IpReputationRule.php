<?php

namespace App\Services\Risk\Rules;

use App\Services\Risk\RiskContext;
use App\Services\Risk\RiskRuleHit;
use App\Services\Risk\RiskRuleInterface;
use Illuminate\Support\Facades\Config;

/**
 * Flag les IPs présentes dans la liste `risk.flagged_networks` ou
 * passées via params['cidrs'].
 *
 * Vérifie via IPv4/IPv6 in_cidr basique. Pour prod, brancher MaxMind ou IPQS.
 */
class IpReputationRule implements RiskRuleInterface
{
    public function code(): string
    {
        return 'ip.flagged_network';
    }

    public function evaluate(RiskContext $context, ?array $params = null): ?RiskRuleHit
    {
        $ip = $context->ipAddress();
        if (! $ip) {
            return null;
        }

        $cidrs = (array) ($params['cidrs'] ?? Config::get('risk.flagged_networks', []));
        if (empty($cidrs)) {
            return null;
        }

        foreach ($cidrs as $cidr) {
            $cidr = trim((string) $cidr);
            if ($cidr === '') {
                continue;
            }
            if ($this->ipMatchesCidr($ip, $cidr)) {
                return new RiskRuleHit(
                    code: $this->code(),
                    score: (int) ($params['score'] ?? 40),
                    reason: "IP {$ip} dans réseau flaggé {$cidr}",
                    details: ['ip' => $ip, 'cidr' => $cidr],
                );
            }
        }

        return null;
    }

    protected function ipMatchesCidr(string $ip, string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return $ip === $cidr;
        }
        [$subnet, $maskBits] = explode('/', $cidr, 2);
        $maskBits = (int) $maskBits;

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            if ($ipLong === false || $subnetLong === false) {
                return false;
            }
            $mask = -1 << (32 - $maskBits);
            return ($ipLong & $mask) === ($subnetLong & $mask);
        }

        // IPv6 simpliste : binary prefix compare
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
            && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ipBin = inet_pton($ip);
            $subnetBin = inet_pton($subnet);
            if ($ipBin === false || $subnetBin === false) {
                return false;
            }
            $bytes = intdiv($maskBits, 8);
            return substr($ipBin, 0, $bytes) === substr($subnetBin, 0, $bytes);
        }

        return false;
    }
}
