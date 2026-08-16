<?php

namespace App\Services\Kyc\Providers;

use App\Models\KycCheck;
use App\Models\KycVerification;
use App\Services\Kyc\KycProviderInterface;
use App\Services\Kyc\KycStartRequest;
use App\Services\Kyc\KycStartResult;
use App\Services\Kyc\KycStatusResult;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Adapter Onfido (https://documentation.onfido.com/).
 *
 * Skeleton — appels HTTP de base. À enrichir selon les besoins :
 *   - Workflow SDK token pour iframe / mobile SDK
 *   - Multi-document support
 *   - Custom check configurations
 *
 * Configuration via `config/kyc.php > providers.onfido` :
 *   - api_token (REST API token)
 *   - region (eu|us|ca)
 *   - webhook_token (token HMAC pour vérifier signature webhooks)
 */
class OnfidoProvider implements KycProviderInterface
{
    public function name(): string
    {
        return 'onfido';
    }

    public function startVerification(KycStartRequest $request): KycStartResult
    {
        $applicant = $this->client()->post('/applicants', [
            'first_name' => $this->firstName($request->user),
            'last_name' => $this->lastName($request->user),
            'email' => $request->user->email,
            'location' => ['country_of_residence' => strtoupper($request->countryCode)],
        ]);

        if ($applicant->failed()) {
            throw new RuntimeException('Onfido create applicant failed: '.$applicant->body());
        }

        $applicantId = (string) $applicant->json('id');

        return new KycStartResult(
            externalApplicantId: $applicantId,
            externalCheckId: null,
            hostedFlowUrl: null,
            raw: $applicant->json() ?? [],
        );
    }

    public function fetchStatus(KycVerification $verification): KycStatusResult
    {
        $checkId = $verification->external_check_id;
        if (! $checkId) {
            return new KycStatusResult(
                status: KycVerification::STATUS_AWAITING_DOCS,
                decision: KycVerification::DECISION_PENDING,
            );
        }

        $check = $this->client()->get("/checks/{$checkId}");
        if ($check->failed()) {
            throw new RuntimeException('Onfido fetch check failed: '.$check->body());
        }

        return $this->mapCheckResponse($check->json() ?? []);
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $token = (string) config('kyc.providers.onfido.webhook_token', '');
        if ($token === '') {
            throw new RuntimeException('Onfido webhook token missing (kyc.providers.onfido.webhook_token).');
        }

        $signature = $headers['X-SHA2-Signature'][0] ?? $headers['x-sha2-signature'][0] ?? null;
        if (! $signature) {
            throw new RuntimeException('Missing X-SHA2-Signature header.');
        }

        $computed = hash_hmac('sha256', $payload, $token);
        if (! hash_equals($computed, $signature)) {
            throw new RuntimeException('Invalid Onfido webhook signature.');
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function mapWebhookEvent(array $payload): ?KycStatusResult
    {
        $resource = $payload['payload']['resource_type'] ?? null;
        if ($resource !== 'check') {
            return null;
        }

        $object = $payload['payload']['object'] ?? [];
        if (empty($object)) {
            return null;
        }

        return $this->mapCheckResponse($object);
    }

    protected function client()
    {
        $config = (array) config('kyc.providers.onfido', []);
        $token = (string) ($config['api_token'] ?? '');
        $region = strtolower((string) ($config['region'] ?? 'eu'));

        $baseUrl = match ($region) {
            'us' => 'https://api.us.onfido.com/v3.6',
            'ca' => 'https://api.ca.onfido.com/v3.6',
            default => 'https://api.eu.onfido.com/v3.6',
        };

        if ($token === '') {
            throw new RuntimeException('Onfido API token missing (kyc.providers.onfido.api_token).');
        }

        return Http::withToken($token, 'Token')
            ->baseUrl($baseUrl)
            ->acceptJson()
            ->asJson();
    }

    protected function mapCheckResponse(array $body): KycStatusResult
    {
        // Onfido check.result : 'clear' | 'consider' | null (pending)
        // Onfido check.status : 'awaiting_data' | 'in_progress' | 'complete' | 'withdrawn'
        $result = $body['result'] ?? null;
        $status = $body['status'] ?? null;

        $mappedStatus = match (true) {
            $result === 'clear' => KycVerification::STATUS_CLEAR,
            $result === 'consider' => KycVerification::STATUS_CONSIDER,
            $status === 'awaiting_data' => KycVerification::STATUS_AWAITING_DOCS,
            $status === 'in_progress' => KycVerification::STATUS_IN_REVIEW,
            $status === 'withdrawn' => KycVerification::STATUS_CANCELLED,
            default => KycVerification::STATUS_IN_REVIEW,
        };

        $decision = match ($result) {
            'clear' => KycVerification::DECISION_APPROVED,
            'consider' => KycVerification::DECISION_MANUAL_REVIEW,
            default => KycVerification::DECISION_PENDING,
        };

        $checks = $this->mapReports($body, $result);

        return new KycStatusResult(
            status: $mappedStatus,
            decision: $decision,
            score: null,
            checks: $checks,
            rejectionReason: $result === 'consider' ? ($body['sub_result'] ?? 'Manual review required by Onfido') : null,
            raw: $body,
        );
    }

    /**
     * LE TYPE D'UN RAPPORT SE LIT SUR LE RAPPORT, PAS SUR LA VÉRIFICATION.
     *
     * `/checks/{id}` ne rend que `report_ids` : une liste d'identifiants, sans nature. L'adaptateur
     * les rangeait tous en `document`. Conséquence directe : `config/kyc.php` demande bien un
     * rapport `facial_similarity` à chaque vérification, Onfido le rend, et il était enregistré
     * comme un contrôle de document — le seul résultat facial de la plateforme était perdu à
     * l'écriture, et le type `KycCheck::TYPE_FACIAL_SIMILARITY` n'était jamais posé par personne.
     *
     * On lit donc les rapports. Trois sources, dans l'ordre : ceux déjà présents dans la charge
     * utile (certains webhooks les embarquent), sinon un appel à `/reports?check_id=…`, sinon —
     * réseau coupé, jeton absent — les identifiants seuls, typés `unknown`. Ne pas savoir se dit ;
     * ça ne s'invente pas.
     *
     * @param  array<string, mixed>  $body
     * @return list<array<string, mixed>>
     */
    protected function mapReports(array $body, ?string $checkResult): array
    {
        $reports = $body['reports'] ?? null;

        if (! is_array($reports) || $reports === []) {
            $reports = $this->fetchReports($body);
        }

        if ($reports !== []) {
            $checks = [];

            foreach ($reports as $report) {
                if (! is_array($report)) {
                    continue;
                }

                $ligne = [
                    'type' => $this->reportType((string) ($report['name'] ?? '')),
                    'result' => $this->reportResult($report['result'] ?? null),
                    'external_id' => (string) ($report['id'] ?? ''),
                ];

                if (filled($report['sub_result'] ?? null)) {
                    $ligne['sub_result'] = (string) $report['sub_result'];
                }

                if (is_array($report['breakdown'] ?? null)) {
                    $ligne['breakdown'] = $report['breakdown'];
                }

                $score = $report['properties']['score'] ?? null;
                if (is_numeric($score)) {
                    $ligne['confidence'] = (float) $score;
                }

                $checks[] = $ligne;
            }

            return $checks;
        }

        // Repli : on connaît les identifiants, pas la nature. On le dit.
        $checks = [];
        foreach ((array) ($body['report_ids'] ?? []) as $reportId) {
            $checks[] = [
                'type' => KycCheck::TYPE_UNKNOWN,
                'result' => $this->reportResult($checkResult),
                'external_id' => (string) $reportId,
            ];
        }

        return $checks;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return list<array<string, mixed>>
     */
    protected function fetchReports(array $body): array
    {
        $checkId = $body['id'] ?? null;

        if (! filled($checkId)) {
            return [];
        }

        /*
         * Soft-fail volontaire : l'appel des rapports enrichit le résultat, il ne le conditionne
         * pas. Une panne réseau ne doit pas transformer une vérification aboutie en échec — le
         * repli ci-dessus enregistre alors les identifiants sans leur inventer de nature.
         */
        try {
            $response = $this->client()->get('/reports', ['check_id' => (string) $checkId]);

            if ($response->failed()) {
                return [];
            }

            $reports = $response->json('reports');

            return is_array($reports) ? array_values($reports) : [];
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Noms de rapports Onfido → types de contrôle CleanUx.
     *
     * Onfido décline la similarité faciale en trois variantes (`_photo`, `_video`, `_motion`) et
     * les listes de surveillance en cinq : on compare donc par préfixe, sinon la prochaine variante
     * repartirait en `unknown` sans que rien ne le signale.
     */
    protected function reportType(string $name): string
    {
        $name = strtolower(trim($name));

        return match (true) {
            $name === 'document' => KycCheck::TYPE_DOCUMENT,
            str_starts_with($name, 'facial_similarity') => KycCheck::TYPE_FACIAL_SIMILARITY,
            str_starts_with($name, 'known_faces') => KycCheck::TYPE_FACIAL_SIMILARITY,
            str_starts_with($name, 'watchlist') => KycCheck::TYPE_WATCHLIST_AML,
            $name === 'right_to_work' => KycCheck::TYPE_RIGHT_TO_WORK,
            str_contains($name, 'criminal') => KycCheck::TYPE_CRIMINAL_RECORD,
            str_contains($name, 'address') => KycCheck::TYPE_ADDRESS,
            $name === 'tax_id' => KycCheck::TYPE_TAX_ID,
            default => KycCheck::TYPE_UNKNOWN,
        };
    }

    protected function reportResult(mixed $result): string
    {
        return match ((string) $result) {
            'clear' => KycCheck::RESULT_CLEAR,
            'consider' => KycCheck::RESULT_CONSIDER,
            'unidentified' => KycCheck::RESULT_UNIDENTIFIED,
            'caution' => KycCheck::RESULT_CAUTION,
            'rejected' => KycCheck::RESULT_REJECTED,
            default => KycCheck::RESULT_PENDING,
        };
    }

    protected function firstName($user): string
    {
        $parts = preg_split('/\s+/', (string) $user->name, 2);

        return $parts[0] ?? 'Unknown';
    }

    protected function lastName($user): string
    {
        $parts = preg_split('/\s+/', (string) $user->name, 2);

        return $parts[1] ?? '-';
    }
}
