<?php

namespace App\Services\ChatV2;

class ModerationService
{
    /**
     * Scan a message body :
     *  1. Block if matches toxic words
     *  2. Redact PII (email/phone/IBAN/credit_card)
     *  3. Return ModerationResult with status + redacted body
     */
    public function scan(string $body): ModerationResult
    {
        $cfg = (array) config('chat_v2.moderation');

        // 1) toxic detection
        if (! empty($cfg['toxic_block_enabled'])) {
            $toxic = $this->detectToxic($body, (array) ($cfg['toxic_words'] ?? []));
            if ($toxic !== null) {
                return new ModerationResult(
                    status: 'blocked',
                    reason: 'toxic_word:' . $toxic,
                    redactedBody: $body,
                    originalHash: hash('sha256', $body),
                );
            }
        }

        // 2) PII redaction
        $redacted = $body;
        $piiFlags = [];
        if (! empty($cfg['pii_redaction_enabled'])) {
            foreach ((array) ($cfg['pii_patterns'] ?? []) as $type => $pattern) {
                $count = 0;
                $newBody = @preg_replace($pattern, '[REDACTED:' . $type . ']', $redacted, -1, $count);
                if ($newBody !== null && $count > 0) {
                    $piiFlags[] = $type;
                    $redacted = $newBody;
                }
            }
        }

        if (! empty($piiFlags)) {
            return new ModerationResult(
                status: 'flagged',
                reason: 'pii_redacted:' . implode(',', $piiFlags),
                redactedBody: $redacted,
                originalHash: hash('sha256', $body),
            );
        }

        return new ModerationResult(
            status: 'clean',
            reason: null,
            redactedBody: $body,
            originalHash: null,
        );
    }

    protected function detectToxic(string $body, array $words): ?string
    {
        if (empty($words)) {
            return null;
        }
        $lower = mb_strtolower($body);
        foreach ($words as $w) {
            $lw = mb_strtolower((string) $w);
            if ($lw === '') {
                continue;
            }
            if (preg_match('/\b' . preg_quote($lw, '/') . '\b/u', $lower)) {
                return $lw;
            }
        }
        return null;
    }
}
