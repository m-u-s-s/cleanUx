<?php

namespace App\Services\Audit;

use App\Models\AuditEvent;
use App\Models\AuditRedactionRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AuditService (Phase Audit v2).
 *
 * Successeur de ActivityLogger pour les events typés v2. ActivityLogger reste
 * en place pour la rétrocompat de toutes les écritures existantes — le mirror
 * vers audit_events peut être activé via `audit.mirror_activity_logger` quand
 * on veut centraliser.
 *
 * Workflow record() :
 *   1. Resolve domain / severity / actor / subject / request context
 *   2. Apply redaction rules (drop_keys, hash_keys, regex patterns DB)
 *   3. Clamp context size (drop if > max_context_size_bytes)
 *   4. Resolve retention policy code
 *   5. Persist row (idempotent via key si fourni)
 *   6. Soft-fail (try/catch + Log::warning) — l'audit ne bloque JAMAIS le flow
 */
class AuditService
{
    /**
     * Main entrypoint.
     *
     * @param array<string,mixed> $options
     *   - actor: ?User|string (User instance, 'system', 'webhook', 'job', null)
     *   - subject: ?Model
     *   - severity: ?string (info|warning|error|critical)
     *   - domain: ?string (inferred from event_type prefix if absent)
     *   - request: ?Request
     *   - idempotency_key: ?string
     *   - tenant_id: ?int
     *   - service_zone_id: ?int
     *   - occurred_at: ?\DateTimeInterface
     *   - retention_policy_code: ?string
     *   - is_pinned: ?bool
     */
    public function record(string $eventType, array $context = [], array $options = []): ?AuditEvent
    {
        if (! Config::get('audit.enabled', true)) {
            return null;
        }

        try {
            $idempotencyKey = $options['idempotency_key'] ?? null;
            if ($idempotencyKey) {
                $existing = AuditEvent::query()->where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $domain = (string) ($options['domain'] ?? $this->inferDomain($eventType));
            $severity = (string) ($options['severity'] ?? $this->inferSeverity($eventType));

            [$redactedContext, $redactedKeys] = $this->redact($context, $domain);
            $redactedContext = $this->clampContextSize($redactedContext);

            $actorMeta = $this->resolveActor($options['actor'] ?? null);
            $subject = $options['subject'] ?? null;
            $subjectMeta = $this->resolveSubject($subject);
            $request = $options['request'] ?? (app()->bound('request') ? request() : null);
            $requestMeta = $this->resolveRequestMeta($request);

            return AuditEvent::create([
                'event_type' => $eventType,
                'domain' => $domain,
                'severity' => $severity,
                'actor_type' => $actorMeta['type'],
                'actor_id' => $actorMeta['id'],
                'actor_label' => $actorMeta['label'],
                'subject_type' => $subjectMeta['type'],
                'subject_id' => $subjectMeta['id'],
                'subject_label' => $subjectMeta['label'],
                'context' => $redactedContext,
                'context_redacted' => empty($redactedKeys) ? null : $redactedKeys,
                'ip_hash' => $requestMeta['ip_hash'],
                'user_agent_short' => $requestMeta['user_agent_short'],
                'route_name' => $requestMeta['route_name'],
                'request_id' => $requestMeta['request_id'],
                'tenant_id' => $options['tenant_id'] ?? $this->resolveTenantId($subject, $actorMeta),
                'service_zone_id' => $options['service_zone_id'] ?? $this->resolveServiceZoneId($subject, $context),
                'retention_policy_code' => $options['retention_policy_code'] ?? null,
                'is_pinned' => (bool) ($options['is_pinned'] ?? false),
                'idempotency_key' => $idempotencyKey,
                'occurred_at' => $options['occurred_at'] ?? now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AuditService::record failed', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function pin(AuditEvent $event): AuditEvent
    {
        $event->forceFill(['is_pinned' => true])->save();
        return $event->fresh();
    }

    public function unpin(AuditEvent $event): AuditEvent
    {
        $event->forceFill(['is_pinned' => false])->save();
        return $event->fresh();
    }

    /**
     * Applies all active redaction rules to context.
     *
     * @return array{0: array<string,mixed>, 1: array<int,string>}
     */
    public function redact(array $context, ?string $domain = null): array
    {
        $cfg = (array) Config::get('audit.redaction', []);
        $dropKeys = array_map('strtolower', (array) ($cfg['drop_keys'] ?? []));
        $hashKeys = array_map('strtolower', (array) ($cfg['hash_keys'] ?? []));

        $redactedKeys = [];

        // 1) Walk context recursively applying config rules
        $clean = $this->walkAndRedact($context, $dropKeys, $hashKeys, $redactedKeys);

        // 2) Apply DB-driven regex/path rules (additional)
        try {
            $dbRules = AuditRedactionRule::query()->active()->get()
                ->filter(fn ($r) => $r->appliesToDomain($domain));

            foreach ($dbRules as $rule) {
                $clean = $this->applyDbRule($clean, $rule, $redactedKeys);
            }
        } catch (\Throwable $e) {
            // table may not exist in dev; ignore
        }

        return [$clean, array_values(array_unique($redactedKeys))];
    }

    public function clampContextSize(array $context): array
    {
        $max = (int) Config::get('audit.redaction.max_context_size_bytes', 32768);
        $encoded = json_encode($context) ?: '{}';
        if (strlen($encoded) <= $max) {
            return $context;
        }

        return [
            '_truncated' => true,
            '_original_size_bytes' => strlen($encoded),
            '_preview_keys' => array_keys($context),
        ];
    }

    protected function walkAndRedact(array $context, array $dropKeys, array $hashKeys, array &$redactedKeys): array
    {
        $clean = [];
        foreach ($context as $k => $v) {
            $keyLower = strtolower((string) $k);

            if (in_array($keyLower, $dropKeys, true)) {
                $redactedKeys[] = (string) $k;
                continue;
            }

            if (in_array($keyLower, $hashKeys, true)) {
                if ($v !== null && $v !== '') {
                    $clean[$k] = 'sha256:' . substr(hash('sha256', (string) $v), 0, 16);
                }
                $redactedKeys[] = (string) $k;
                continue;
            }

            if (is_array($v)) {
                $clean[$k] = $this->walkAndRedact($v, $dropKeys, $hashKeys, $redactedKeys);
            } else {
                $clean[$k] = $v;
            }
        }
        return $clean;
    }

    protected function applyDbRule(array $context, AuditRedactionRule $rule, array &$redactedKeys): array
    {
        $matchType = $rule->match_type;
        $pattern = $rule->pattern;
        $replacement = $rule->replacement;

        $apply = function ($value) use ($matchType, $pattern, $replacement, &$apply) {
            if (is_array($value)) {
                return array_map($apply, $value);
            }
            if (! is_string($value) && ! is_numeric($value)) {
                return $value;
            }
            $str = (string) $value;
            if ($matchType === AuditRedactionRule::MATCH_REGEX) {
                $result = @preg_replace($pattern, $replacement, $str);
                return $result !== null ? $result : $str;
            }
            return $str;
        };

        $clean = [];
        foreach ($context as $k => $v) {
            $keyLower = strtolower((string) $k);
            if ($matchType === AuditRedactionRule::MATCH_KEY && $keyLower === strtolower($pattern)) {
                $redactedKeys[] = (string) $k;
                $clean[$k] = $replacement;
                continue;
            }
            $clean[$k] = $apply($v);
        }
        return $clean;
    }

    protected function inferDomain(string $eventType): string
    {
        if (str_contains($eventType, '.')) {
            return Str::before($eventType, '.');
        }
        return 'general';
    }

    protected function inferSeverity(string $eventType): string
    {
        return match (true) {
            str_contains($eventType, 'failed'),
            str_contains($eventType, 'error'),
            str_contains($eventType, 'blocked'),
            str_contains($eventType, 'fraud') => AuditEvent::SEVERITY_ERROR,
            str_contains($eventType, 'critical'),
            str_contains($eventType, 'breach') => AuditEvent::SEVERITY_CRITICAL,
            str_contains($eventType, 'deleted'),
            str_contains($eventType, 'cancelled'),
            str_contains($eventType, 'expired'),
            str_contains($eventType, 'opt_out'),
            str_contains($eventType, 'export') => AuditEvent::SEVERITY_WARNING,
            default => AuditEvent::SEVERITY_INFO,
        };
    }

    /**
     * @return array{type: ?string, id: ?int, label: ?string}
     */
    protected function resolveActor(mixed $actor): array
    {
        if ($actor === null) {
            $authedUser = Auth::user();
            if ($authedUser) {
                return [
                    'type' => AuditEvent::ACTOR_USER,
                    'id' => $authedUser->id,
                    'label' => Str::limit((string) ($authedUser->email ?? $authedUser->name ?? ''), 191, ''),
                ];
            }
            return ['type' => AuditEvent::ACTOR_SYSTEM, 'id' => null, 'label' => null];
        }

        if ($actor instanceof User) {
            return [
                'type' => AuditEvent::ACTOR_USER,
                'id' => $actor->id,
                'label' => Str::limit((string) ($actor->email ?? $actor->name ?? ''), 191, ''),
            ];
        }

        if (is_string($actor)) {
            $type = in_array($actor, [
                AuditEvent::ACTOR_SYSTEM,
                AuditEvent::ACTOR_WEBHOOK,
                AuditEvent::ACTOR_JOB,
            ], true) ? $actor : AuditEvent::ACTOR_SYSTEM;
            return ['type' => $type, 'id' => null, 'label' => $actor];
        }

        return ['type' => AuditEvent::ACTOR_SYSTEM, 'id' => null, 'label' => null];
    }

    /**
     * @return array{type: ?string, id: ?int, label: ?string}
     */
    protected function resolveSubject(?Model $subject): array
    {
        if (! $subject) {
            return ['type' => null, 'id' => null, 'label' => null];
        }
        return [
            'type' => class_basename(get_class($subject)),
            'id' => (int) $subject->getKey(),
            'label' => $this->subjectLabel($subject),
        ];
    }

    protected function subjectLabel(Model $subject): ?string
    {
        foreach (['email', 'code', 'name', 'reference', 'booking_reference', 'policy_number'] as $attr) {
            if (isset($subject->{$attr}) && $subject->{$attr}) {
                return Str::limit((string) $subject->{$attr}, 191, '');
            }
        }
        return null;
    }

    /**
     * @return array{ip_hash: ?string, user_agent_short: ?string, route_name: ?string, request_id: ?string}
     */
    protected function resolveRequestMeta(?Request $request): array
    {
        if (! $request) {
            return ['ip_hash' => null, 'user_agent_short' => null, 'route_name' => null, 'request_id' => null];
        }
        return [
            'ip_hash' => $request->ip() ? hash('sha256', (string) $request->ip()) : null,
            'user_agent_short' => $request->userAgent() ? Str::limit((string) $request->userAgent(), 191, '') : null,
            'route_name' => $request->route()?->getName(),
            'request_id' => $request->headers->get('X-Request-Id')
                ?: $request->headers->get('X-Correlation-Id')
                ?: null,
        ];
    }

    protected function resolveTenantId(?Model $subject, array $actorMeta): ?int
    {
        if ($subject && isset($subject->organization_account_id)) {
            return $subject->organization_account_id ? (int) $subject->organization_account_id : null;
        }
        return null;
    }

    protected function resolveServiceZoneId(?Model $subject, array $context): ?int
    {
        if (isset($context['service_zone_id']) && $context['service_zone_id']) {
            return (int) $context['service_zone_id'];
        }
        if ($subject && isset($subject->service_zone_id) && $subject->service_zone_id) {
            return (int) $subject->service_zone_id;
        }
        return null;
    }
}
