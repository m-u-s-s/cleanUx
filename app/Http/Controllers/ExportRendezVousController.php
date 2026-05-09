<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\User;
use App\Support\ActivityLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ExportRendezVousController extends Controller
{
    public function export(Request $request, $format, $employeId = null)
    {
        Gate::authorize('export', User::class);

        /** @var User|null $admin */
        $admin = $request->user();
        if ($admin?->isReadOnlyAdmin()) {
            abort(403);
        }

        $serviceFilter = $request->input('service_catalog_id') ?: $request->input('service_identifier');

        $query = Booking::with(['client', 'employe', 'serviceZone', 'serviceCatalog', 'postalCode'])
            ->when($employeId, fn(Builder $q) => $q->where('employe_id', $employeId))
            ->when($request->status, fn(Builder $q) => $q->where('status', $request->status))
            ->when($request->ville, fn(Builder $q) => $q->where('ville', $request->ville))


            ->when($serviceFilter, function (Builder $q) use ($request, $serviceFilter) {
                if ($request->filled('service_catalog_id')) {
                    $q->where('service_catalog_id', $serviceFilter);

                    return;
                }

                $q->where(function (Builder $sub) use ($serviceFilter) {
                    $sub->whereHas('serviceCatalog', function (Builder $serviceQuery) use ($serviceFilter) {
                        $serviceQuery->where('code', $serviceFilter)
                            ->orWhere('slug', $serviceFilter)
                            ->orWhere('name', $serviceFilter);
                    })
                        ->orWhere('pricing_snapshot->service_identifier', $serviceFilter)
                        ->orWhere('pricing_snapshot->service->service_identifier', $serviceFilter)
                        ->orWhere('pricing_snapshot->service->code', $serviceFilter)
                        ->orWhere('pricing_snapshot->service->slug', $serviceFilter);
                });
            })

            ->when($request->date_from, fn(Builder $q) => $q->whereDate('date', '>=', $request->date_from))
            ->when($request->date_to, fn(Builder $q) => $q->whereDate('date', '<=', $request->date_to));

        $this->applyAdminZoneScope($query, $admin);

        $rdvs = $query
            ->orderBy('date')
            ->orderBy('heure')
            ->get();

        ActivityLogger::log('export_rendez_vous', null, [
            'format' => $format,
            'employe_id' => $employeId,
            'status' => $request->status,
            'ville' => $request->ville,
            'service_filter' => $serviceFilter,
            'service_catalog_id' => $request->service_catalog_id,
            'service_identifier' => $request->service_identifier,
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
            'count' => $rdvs->count(),
            'access_scope' => $admin?->access_scope,
            'managed_service_zone_id' => $admin?->managed_service_zone_id,
        ]);

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('exports.rendezvous', ['data' => $rdvs]);
            return $pdf->download('rendezvous_' . now()->format('Ymd_His') . '.pdf');
        }

        if ($format === 'csv') {
            $filename = 'rendezvous_' . now()->format('Ymd_His') . '.csv';

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function () use ($rdvs) {
                $file = fopen('php://output', 'w');

                fputcsv($file, [
                    'id',
                    'client',
                    'employe',
                    'service',
                    'zone',
                    'ville',
                    'code_postal',
                    'status',
                    'priorite',
                ]);

                foreach ($rdvs as $rdv) {
                    fputcsv($file, [
                        $rdv->id,
                        $rdv->client?->name,
                        $rdv->employe?->name,
                        $rdv->service_display_name,
                        $rdv->serviceZone?->name,
                        $rdv->ville,
                        $rdv->postal_code_display,
                        $rdv->status,
                        $rdv->priorite,
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        }

        abort(404);
    }

    protected function applyAdminZoneScope(Builder $query, ?User $admin): void
    {
        if (! $admin) {
            return;
        }

        if ($admin->isZoneScopedAdmin()) {
            $query->where('service_zone_id', $admin->managed_service_zone_id);
        }
    }
}
