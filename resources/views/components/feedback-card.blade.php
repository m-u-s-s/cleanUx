@props(['feedback'])

@php
    $rdv = $feedback->rendezVous;
    $service = $rdv?->service_display_name ?? 'Service non précisé';
    $zone = $rdv?->serviceZone?->name;
    $status = $rdv?->status ?? 'en_attente';
@endphp

<div class="cu-feedback-shell cu-fade-up">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-semibold text-slate-900">{{ $feedback->client->name ?? 'Client' }}</span>
                @if($rdv?->employe)
                    <span class="cu-chip !py-1">🧑‍💼 {{ $rdv->employe->name }}</span>
                @endif
                <x-badge :status="$status" />
            </div>

            <div class="mt-2 flex flex-wrap items-center gap-3 text-sm text-slate-500">
                <span>📅 {{ optional($feedback->created_at)->translatedFormat('d M Y') }}</span>
                <span>🧽 {{ $service }}</span>
                @if($zone)
                    <span>📍 {{ $zone }}</span>
                @endif
            </div>
        </div>

        <div class="text-left lg:text-right">
            <div class="inline-flex items-center gap-2 rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2">
                <x-star-rating :rating="$feedback->note" readonly />
                <span class="text-sm font-semibold text-amber-800">{{ $feedback->note }}/5</span>
            </div>
        </div>
    </div>

    <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50/80 p-4 text-sm leading-6 text-slate-700">
        {{ $feedback->commentaire ?: 'Aucun commentaire.' }}
    </div>

    @if($feedback->reponse_admin)
        <div class="cu-feedback-response">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">Réponse admin</p>
            <p class="mt-2 leading-6">{{ $feedback->reponse_admin }}</p>
        </div>
    @endif

    @if(Route::has('admin.rendezvous.show') && $rdv)
        <div class="mt-4 flex justify-end">
            <a href="{{ route('admin.rendezvous.show', $rdv->id) }}" class="cu-inline-action">
                🔎 Voir le rendez-vous lié
            </a>
        </div>
    @endif
</div>
