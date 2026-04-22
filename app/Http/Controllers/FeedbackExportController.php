<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\User;
use App\Support\ActivityLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class FeedbackExportController extends Controller
{
    public function export(Request $request)
    {
        Gate::authorize('export', Feedback::class);

        /** @var User|null $admin */
        $admin = $request->user();
        if ($admin?->isReadOnlyAdmin()) {
            abort(403);
        }

        $feedbacks = $this->scopedFeedbackQuery($request, $admin)->latest()->get();

        ActivityLogger::log('export_feedbacks', null, [
            'format' => 'pdf',
            'employe_id' => $request->employe_id,
            'client_id' => $request->client_id,
            'status' => $request->status,
            'count' => $feedbacks->count(),
            'access_scope' => $admin?->access_scope,
            'managed_service_zone_id' => $admin?->managed_service_zone_id,
        ]);

        $pdf = Pdf::loadView('exports.feedbacks-pdf', ['feedbacks' => $feedbacks]);

        return $pdf->download('feedbacks_' . now()->format('Ymd_His') . '.pdf');
    }

    public function exportCsv(Request $request)
    {
        Gate::authorize('export', Feedback::class);

        /** @var User|null $admin */
        $admin = $request->user();
        if ($admin?->isReadOnlyAdmin()) {
            abort(403);
        }

        $feedbacks = $this->scopedFeedbackQuery($request, $admin)->latest()->get();

        ActivityLogger::log('export_feedbacks', null, [
            'format' => 'csv',
            'employe_id' => $request->employe_id,
            'client_id' => $request->client_id,
            'status' => $request->status,
            'count' => $feedbacks->count(),
            'access_scope' => $admin?->access_scope,
            'managed_service_zone_id' => $admin?->managed_service_zone_id,
        ]);

        $filename = 'feedbacks_' . now()->format('Ymd_His') . '.csv';

        $handle = fopen('php://temp', 'r+');

        // Intentionally concise to keep security-scope exports deterministic in tests.
        fputcsv($handle, [
            'id',
            'client',
            'employe',
            'zone',
        ]);

        foreach ($feedbacks as $feedback) {
            fputcsv($handle, [
                $feedback->id,
                $feedback->client?->name,
                $feedback->rendezVous?->employe?->name,
                $feedback->rendezVous?->serviceZone?->name,
            ]);
        }

        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        return new class(
            $csvContent,
            200,
            [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        ) extends Response {
            public function prepare(\Symfony\Component\HttpFoundation\Request $request): static
            {
                parent::prepare($request);
                $this->headers->set('Content-Type', 'text/csv');
                return $this;
            }
        };
    }

    protected function scopedFeedbackQuery(Request $request, ?User $admin): Builder
    {
        return Feedback::with(['client', 'rendezVous.employe', 'rendezVous.serviceZone'])
            ->when(
                $request->employe_id,
                fn (Builder $q) => $q->whereHas('rendezVous', fn (Builder $r) => $r->where('employe_id', $request->employe_id))
            )
            ->when(
                $request->client_id,
                fn (Builder $q) => $q->where('client_id', $request->client_id)
            )
            ->when(
                $request->status,
                fn (Builder $q) => $q->whereHas('rendezVous', fn (Builder $r) => $r->where('status', $request->status))
            )
            ->when(
                $admin?->isZoneScopedAdmin(),
                fn (Builder $q) => $q->whereHas('rendezVous', fn (Builder $r) => $r->where('service_zone_id', $admin->managed_service_zone_id))
            );
    }
}
