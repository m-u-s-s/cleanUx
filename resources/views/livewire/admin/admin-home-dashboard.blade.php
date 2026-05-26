<div class="cu-page space-y-6" wire:poll.30s>

    <div class="cu-page-header">
        <div>
            <p class="cu-eyebrow">Tableau de bord</p>
            <h2 class="cu-section-title mt-2">Vue d'ensemble</h2>
            <p class="cu-section-subtitle">Actualisation automatique toutes les 30 secondes.</p>
        </div>
    </div>

    {{-- KPI row --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="cu-kpi">
            <p class="cu-kpi-label">Réservations aujourd'hui</p>
            <p class="cu-kpi-value">{{ $bookingsToday }}</p>
        </div>

        <div class="cu-kpi">
            <p class="cu-kpi-label">Missions actives</p>
            <p class="cu-kpi-value">{{ $activeMissions }}</p>
        </div>

        <div class="cu-kpi">
            <p class="cu-kpi-label">Prestataires en ligne</p>
            <p class="cu-kpi-value">{{ $providersOnline }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        {{-- Recent disputes --}}
        <div class="cu-card">
            <h3 class="cu-section-title mb-4">Litiges en cours</h3>

            @forelse($recentDisputes as $dispute)
            <div class="flex items-center justify-between border-b border-slate-100 py-3 last:border-0">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-800">{{ $dispute->reference }}</p>
                    <p class="truncate text-xs text-slate-500">{{ $dispute->subject }}</p>
                </div>
                <span @class([
                    'ml-3 shrink-0 rounded-full px-2.5 py-0.5 text-xs font-bold',
                    'bg-amber-100 text-amber-700' => $dispute->status === 'open',
                    'bg-blue-100 text-blue-700'   => $dispute->status === 'assigned',
                    'bg-purple-100 text-purple-700' => $dispute->status === 'investigating',
                ])>{{ $dispute->status }}</span>
            </div>
            @empty
            <p class="py-4 text-center text-sm text-slate-400">Aucun litige en cours</p>
            @endforelse
        </div>

        {{-- Recent bookings --}}
        <div class="cu-card">
            <h3 class="cu-section-title mb-4">Dernières réservations</h3>

            <div class="overflow-x-auto">
                <table class="cu-table w-full">
                    <thead>
                        <tr>
                            <th>Ref</th>
                            <th>Service</th>
                            <th>Statut</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentBookings as $booking)
                        <tr>
                            <td class="font-mono text-xs">{{ $booking->reference }}</td>
                            <td>{{ $booking->serviceCatalog?->name ?? '—' }}</td>
                            <td>
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs font-bold',
                                    'bg-slate-100 text-slate-600'     => in_array($booking->status, ['en_attente', 'draft']),
                                    'bg-blue-100 text-blue-700'       => $booking->status === 'confirme',
                                    'bg-emerald-100 text-emerald-700' => $booking->status === 'completed',
                                    'bg-red-100 text-red-700'         => $booking->status === 'annule',
                                ])>{{ $booking->status }}</span>
                            </td>
                            <td class="text-xs text-slate-500">
                                {{ $booking->scheduled_date ? \Carbon\Carbon::parse($booking->scheduled_date)->format('d/m') : $booking->created_at?->format('d/m') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="py-4 text-center text-sm text-slate-400">Aucune réservation</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
