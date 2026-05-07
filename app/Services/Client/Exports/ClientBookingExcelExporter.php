<?php

namespace App\Services\Client\Exports;

use App\Models\Booking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase 6.1 — Export Excel multi-onglets.
 *
 * Différence avec ClientBookingExporter (Phase 6, CSV/PDF) :
 *   - Format .xlsx natif Excel
 *   - Plusieurs onglets : Synthèse / Détails / Par site / Par mois
 *   - Formules Excel pour totaux
 *   - Mise en forme : headers gras, couleurs par statut, formats nombres
 *
 * Requiert : composer require phpoffice/phpspreadsheet
 *
 * Streaming : on construit le fichier en mémoire puis on stream le binaire.
 * Pour 5000 bookings → ~30 MB RAM, 5s de génération. Au-delà préférer CSV.
 */
class ClientBookingExcelExporter
{
    public function export(User $user, array $filters = []): StreamedResponse
    {
        $bookings = $this->buildBookingQuery($user, $filters)
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->limit(10000)
            ->get();

        $spreadsheet = new Spreadsheet();

        // Onglet 1 : Synthèse
        $this->buildSummarySheet($spreadsheet, $bookings, $filters);

        // Onglet 2 : Détails
        $this->buildDetailsSheet($spreadsheet, $bookings);

        // Onglet 3 : Par site (uniquement si entreprise multi-sites)
        if ($user->organization_account_id) {
            $this->buildBySiteSheet($spreadsheet, $bookings);
        }

        // Onglet 4 : Par mois
        $this->buildByMonthSheet($spreadsheet, $bookings);

        $spreadsheet->setActiveSheetIndex(0);

        $filename = sprintf('rendez-vous_%s.xlsx', now()->format('Y-m-d_Hi'));

        return new StreamedResponse(function () use ($spreadsheet) {
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-cache, must-revalidate',
        ]);
    }

    // ──────────────────────────────────────────────────────
    // Onglets
    // ──────────────────────────────────────────────────────

    protected function buildSummarySheet(Spreadsheet $sp, $bookings, array $filters): void
    {
        $sheet = $sp->getActiveSheet();
        $sheet->setTitle('Synthèse');

        $sheet->setCellValue('A1', 'Récapitulatif des rendez-vous');
        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1')->getFont()->setSize(16)->setBold(true);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Période
        $periodLabel = $this->describePeriod($filters);
        $sheet->setCellValue('A2', 'Période : ' . $periodLabel);
        $sheet->mergeCells('A2:D2');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('64748B');

        $sheet->setCellValue('A3', 'Généré le : ' . now()->format('d/m/Y à H:i'));
        $sheet->mergeCells('A3:D3');
        $sheet->getStyle('A3')->getFont()->setItalic(true)->getColor()->setRGB('64748B');

        // KPIs
        $row = 5;
        $sheet->setCellValue("A{$row}", 'Indicateur');
        $sheet->setCellValue("B{$row}", 'Valeur');
        $this->styleHeaderRow($sheet, $row);
        $row++;

        $stats = [
            ['Total rendez-vous',  $bookings->count()],
            ['Terminés',           $bookings->whereIn('status', ['termine', 'completed'])->count()],
            ['En attente',         $bookings->whereIn('status', ['en_attente', 'pending'])->count()],
            ['Confirmés',          $bookings->whereIn('status', ['confirme', 'confirmed'])->count()],
            ['Annulés',            $bookings->whereIn('status', ['annule', 'cancelled', 'refuse'])->count()],
            ['CA estimé total',    number_format((float) $bookings->whereNotIn('status', ['annule', 'cancelled'])->sum('estimated_price'), 2, ',', ' ') . ' €'],
            ['CA total annulé',    number_format((float) $bookings->whereIn('status', ['annule', 'cancelled'])->sum('estimated_price'), 2, ',', ' ') . ' €'],
            ['Surface moyenne',    $bookings->avg('surface_m2') ? round($bookings->avg('surface_m2')) . ' m²' : '—'],
        ];

        foreach ($stats as $stat) {
            $sheet->setCellValue("A{$row}", $stat[0]);
            $sheet->setCellValue("B{$row}", $stat[1]);
            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(20);
    }

    protected function buildDetailsSheet(Spreadsheet $sp, $bookings): void
    {
        $sheet = $sp->createSheet();
        $sheet->setTitle('Détails');

        $headers = ['Référence', 'Date', 'Heure', 'Service', 'Site', 'Adresse', 'Ville', 'Statut', 'Surface (m²)', 'Prix estimé (€)', 'Mode'];

        // Headers row
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . '1', $h);
            $col++;
        }
        $this->styleHeaderRow($sheet, 1);

        // Data
        $row = 2;
        foreach ($bookings as $b) {
            $sheet->setCellValue("A{$row}", $b->booking_reference);
            $sheet->setCellValue("B{$row}", $b->scheduled_date instanceof Carbon
                ? $b->scheduled_date->format('Y-m-d')
                : (string) $b->scheduled_date);
            $sheet->setCellValue("C{$row}", $b->scheduled_time
                ? Carbon::parse($b->scheduled_time)->format('H:i')
                : '');
            $sheet->setCellValue("D{$row}", $b->serviceCatalog?->name ?? '');
            $sheet->setCellValue("E{$row}", $b->organizationSite?->name ?? '');
            $sheet->setCellValue("F{$row}", $b->address ?? '');
            $sheet->setCellValue("G{$row}", $b->city ?? '');
            $sheet->setCellValue("H{$row}", $b->status);
            $sheet->setCellValue("I{$row}", $b->surface_m2 ? (int) $b->surface_m2 : '');
            $sheet->setCellValue("J{$row}", $b->estimated_price ? (float) $b->estimated_price : '');
            $sheet->setCellValue("K{$row}", $b->booking_mode ?? '');

            // Coloration par statut
            $color = $this->statusColor($b->status);
            $sheet->getStyle("H{$row}")->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($color);
            $sheet->getStyle("H{$row}")->getFont()->getColor()->setRGB('FFFFFF');

            $row++;
        }

        // Format colonne prix en monétaire
        if ($row > 2) {
            $sheet->getStyle("J2:J" . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00 €');

            // Total ligne
            $sheet->setCellValue("I{$row}", 'TOTAL');
            $sheet->setCellValue("J{$row}", '=SUM(J2:J' . ($row - 1) . ')');
            $sheet->getStyle("I{$row}:J{$row}")->getFont()->setBold(true);
            $sheet->getStyle("J{$row}")->getNumberFormat()->setFormatCode('#,##0.00 €');
        }

        $this->autoSizeColumns($sheet, count($headers));
    }

    protected function buildBySiteSheet(Spreadsheet $sp, $bookings): void
    {
        $sheet = $sp->createSheet();
        $sheet->setTitle('Par site');

        $headers = ['Site', 'Nb RDV', 'Terminés', 'Annulés', 'CA estimé'];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . '1', $h);
            $col++;
        }
        $this->styleHeaderRow($sheet, 1);

        $row = 2;
        $bySite = $bookings->groupBy(fn ($b) => $b->organizationSite?->name ?? 'Sans site');
        foreach ($bySite as $siteName => $siteBookings) {
            $sheet->setCellValue("A{$row}", $siteName);
            $sheet->setCellValue("B{$row}", $siteBookings->count());
            $sheet->setCellValue("C{$row}", $siteBookings->whereIn('status', ['termine', 'completed'])->count());
            $sheet->setCellValue("D{$row}", $siteBookings->whereIn('status', ['annule', 'cancelled'])->count());
            $sheet->setCellValue("E{$row}", (float) $siteBookings->whereNotIn('status', ['annule', 'cancelled'])->sum('estimated_price'));
            $row++;
        }

        if ($row > 2) {
            $sheet->getStyle("E2:E" . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00 €');
        }

        $this->autoSizeColumns($sheet, count($headers));
    }

    protected function buildByMonthSheet(Spreadsheet $sp, $bookings): void
    {
        $sheet = $sp->createSheet();
        $sheet->setTitle('Par mois');

        $headers = ['Mois', 'Nb RDV', 'Terminés', 'Annulés', 'CA estimé'];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col . '1', $h);
            $col++;
        }
        $this->styleHeaderRow($sheet, 1);

        $row = 2;
        $byMonth = $bookings->groupBy(function ($b) {
            $date = $b->scheduled_date instanceof Carbon ? $b->scheduled_date : Carbon::parse((string) $b->scheduled_date);
            return $date->format('Y-m');
        })->sortKeys();

        foreach ($byMonth as $month => $monthBookings) {
            $monthLabel = Carbon::parse($month . '-01')->locale('fr')->isoFormat('MMMM YYYY');
            $sheet->setCellValue("A{$row}", $monthLabel);
            $sheet->setCellValue("B{$row}", $monthBookings->count());
            $sheet->setCellValue("C{$row}", $monthBookings->whereIn('status', ['termine', 'completed'])->count());
            $sheet->setCellValue("D{$row}", $monthBookings->whereIn('status', ['annule', 'cancelled'])->count());
            $sheet->setCellValue("E{$row}", (float) $monthBookings->whereNotIn('status', ['annule', 'cancelled'])->sum('estimated_price'));
            $row++;
        }

        if ($row > 2) {
            $sheet->getStyle("E2:E" . ($row - 1))->getNumberFormat()->setFormatCode('#,##0.00 €');
        }

        $this->autoSizeColumns($sheet, count($headers));
    }

    // ──────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────

    protected function styleHeaderRow($sheet, int $row): void
    {
        $sheet->getStyle("A{$row}:Z{$row}")
            ->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1E40AF');
        $sheet->getStyle("A{$row}:Z{$row}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A{$row}:Z{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    protected function autoSizeColumns($sheet, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $col = chr(ord('A') + $i);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    protected function statusColor(string $status): string
    {
        return match ($status) {
            'en_attente', 'pending'                  => 'F59E0B',
            'confirme', 'confirmed'                  => '3B82F6',
            'en_route', 'on_route'                   => '8B5CF6',
            'sur_place', 'on_site', 'in_progress'    => '06B6D4',
            'termine', 'completed', 'done'           => '10B981',
            'annule', 'cancelled', 'refuse'          => 'EF4444',
            default                                  => '64748B',
        };
    }

    protected function buildBookingQuery(User $user, array $filters)
    {
        $query = Booking::query()
            ->with(['serviceCatalog:id,name', 'organizationSite:id,name']);

        if ($user->organization_account_id) {
            $query->where(function ($q) use ($user) {
                $q->where('customer_organization_id', $user->organization_account_id)
                  ->orWhere('customer_user_id', $user->id);
            });
        } else {
            $query->where(function ($q) use ($user) {
                $q->where('customer_user_id', $user->id)
                  ->orWhere('client_id', $user->id);
            });
        }

        if (! empty($filters['from'])) {
            $query->whereDate('scheduled_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('scheduled_date', '<=', $filters['to']);
        }
        if (! empty($filters['site_ids'])) {
            $query->whereIn('organization_site_id', (array) $filters['site_ids']);
        }
        if (! empty($filters['statuses'])) {
            $query->whereIn('status', (array) $filters['statuses']);
        }

        return $query;
    }

    protected function describePeriod(array $filters): string
    {
        $from = ! empty($filters['from']) ? Carbon::parse($filters['from'])->format('d/m/Y') : null;
        $to   = ! empty($filters['to'])   ? Carbon::parse($filters['to'])->format('d/m/Y')   : null;

        if ($from && $to)  return "Du {$from} au {$to}";
        if ($from)         return "À partir du {$from}";
        if ($to)           return "Jusqu'au {$to}";
        return 'Toutes périodes';
    }
}
