<?php

namespace App\Services\Bookings;

use App\Models\Booking;
use App\Models\OrganizationSite;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Service d'import bulk de bookings depuis un CSV (cas B2B facility management). */
class BulkBookingImporter
{
    /**
     * @param  User  $orderer  Le user qui importe (doit être membre de l'organization)
     * @param  string  $separator  ',' ou ';'
     */
    public function import(User $orderer, int $organizationId, string $csvContent, string $separator = ','): array
    {
        $lines = $this->parseCsv($csvContent, $separator);
        if (empty($lines)) {
            return ['ok' => false, 'error' => 'empty_csv', 'created' => 0, 'errors' => []];
        }

        $header = array_map('trim', array_shift($lines));
        $created = [];
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($lines as $idx => $row) {
                $rowNum = $idx + 2;   // ligne CSV physique (header = 1)
                $data = $this->mapRow($header, $row);
                if (empty(array_filter($data))) {
                    continue;   // ligne vide
                }

                try {
                    $booking = $this->createBookingFromRow($orderer, $organizationId, $data);
                    if ($booking) {
                        $created[] = ['row' => $rowNum, 'booking_id' => $booking->id];
                    }
                } catch (\Throwable $e) {
                    $errors[] = ['row' => $rowNum, 'error' => $e->getMessage(), 'data' => $data];
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ['ok' => false, 'error' => 'transaction_failed', 'message' => $e->getMessage()];
        }

        return [
            'ok' => true,
            'created_count' => count($created),
            'errors_count' => count($errors),
            'created' => $created,
            'errors' => $errors,
        ];
    }

    protected function parseCsv(string $content, string $separator): array
    {
        $lines = [];
        $fp = fopen('php://memory', 'r+');
        fwrite($fp, $content);
        rewind($fp);
        while (($row = fgetcsv($fp, 4096, $separator)) !== false) {
            $lines[] = $row;
        }
        fclose($fp);

        return $lines;
    }

    protected function mapRow(array $header, array $row): array
    {
        $data = [];
        foreach ($header as $i => $key) {
            $data[strtolower(trim((string) $key))] = isset($row[$i]) ? trim((string) $row[$i]) : '';
        }

        return $data;
    }

    /** Crée un Booking depuis une ligne CSV. Throw exception si validation échoue. */
    protected function createBookingFromRow(User $orderer, int $organizationId, array $data): ?Booking
    {
        // Resolve site
        $site = null;
        if (! empty($data['site_code'])) {
            // LA COLONNE S'APPELLE `site_code` (corrigé le 2026-08-05).
            $site = OrganizationSite::query()
                ->where('site_code', $data['site_code'])
                ->where('organization_account_id', $organizationId)
                ->first();
            if (! $site) {
                throw new \RuntimeException("Site '{$data['site_code']}' introuvable pour cette organisation");
            }
        }

        // Resolve trade
        if (empty($data['trade_code'])) {
            throw new \RuntimeException('trade_code manquant');
        }
        $trade = Trade::query()->where('code', $data['trade_code'])->first();
        if (! $trade) {
            throw new \RuntimeException("Trade '{$data['trade_code']}' introuvable");
        }

        // Parse date
        if (empty($data['scheduled_at'])) {
            throw new \RuntimeException('scheduled_at manquant');
        }
        try {
            $scheduled = Carbon::parse($data['scheduled_at']);
            if ($scheduled->isPast()) {
                throw new \RuntimeException('scheduled_at est dans le passé');
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException('scheduled_at format invalide (attendu ISO 8601)');
        }

        // Idempotency via external_ref optionnel
        $externalRef = $data['external_ref'] ?? null;
        if ($externalRef) {
            $existing = Booking::query()
                ->where('customer_organization_id', $organizationId)
                ->whereJsonContains('matching_snapshot->bulk_external_ref', $externalRef)
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        $duration = (int) ($data['duration_minutes'] ?? 60);
        $budgetEur = (float) ($data['budget_max_eur'] ?? 0);

        return Booking::query()->create([
            'client_id' => $orderer->id,
            'customer_organization_id' => $organizationId,
            'trade_id' => $trade->id,
            'service_zone_id' => $site?->service_zone_id ?? null,
            'date' => $scheduled->toDateString(),
            'heure' => $scheduled->format('H:i:s'),
            'duree_estimee' => $duration,
            'devis_estime' => $budgetEur,
            // LE LIEN AU SITE VIT DANS UNE COLONNE, PAS DANS UN BLOB (corrigé le 2026-08-05).
            'organization_site_id' => $site?->id,
            'destination_lat' => $site?->latitude ?? null,
            'destination_lng' => $site?->longitude ?? null,
            'address_components' => $site ? [
                'site_id' => $site->id,
                'site_code' => $site->site_code,   // `code` n'existe pas sur ce modèle
                'address_line_1' => $site->address_line_1,
            ] : null,
            'status' => 'en_attente',
            'commentaire_client' => $data['notes'] ?? null,
            'booking_channel' => 'b2b_bulk_import',
            'matching_snapshot' => [
                'source' => 'bulk_csv_import',
                'bulk_external_ref' => $externalRef,
                'imported_by_user_id' => $orderer->id,
                'imported_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
