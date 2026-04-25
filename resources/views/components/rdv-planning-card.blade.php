@props(['rdv'])

@php
    $minutes = ($rdv->duree ?? $rdv->duree_estimee ?? 90) + 30;
    $isUrgent = $rdv->priorite === 'urgente';
    $isUnassigned = blank($rdv->employe_id);
    $clientBrief = filled($rdv->commentaire_client)
        ? \Illuminate\Support\Str::limit($rdv->commentaire_client, 120)
        : null;

    $statusTone = match ($rdv->status) {
        'confirme' => 'border-emerald-200 bg-emerald-50/70',
        'en_route' => 'border-blue-200 bg-blue-50/70',
        'sur_place' => 'border-indigo-200 bg-indigo-50/70',
        'termine' => 'border-slate-200 bg-slate-50',
        'refuse' => 'border-rose-200 bg-rose-50/70',
        default => 'border-amber-200 bg-amber-50/60',
    };
@endphp

<div class="rounded-2xl border p-4 shadow-sm transition {{ $statusTone }} {{ $isUrgent ? 'ring-2 ring-red-200' : '' }}">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                <p class="text-sm font-black tracking-wide text-slate-900">
                    {{ substr((string) $rdv->heure, 0, 5) }}
                </p>
                @if($rdv->booking_reference)
                    <span class="rounded-full bg-white/80 px-2 py-0.5 text-[11px] font-semibold text-slate-500">
                        {{ $rdv->booking_reference }}
                    </span>
                @endif
            </div>
            <p class="mt-1 truncate text-sm font-bold text-slate-900">
                {{ $rdv->service_display_name }}
            </p>
            <div class="mt-2 space-y-1 text-xs text-slate-600">
                @if($rdv->client)
                    <p class="truncate">👤 {{ $rdv->client->name }}</p>
                @endif
                @if($rdv->organizationAccount)
                    <p class="truncate">🏢 {{ $rdv->organizationAccount->name }}</p>
                @endif
                @if($rdv->organizationSite)
                    <p class="truncate">📍 {{ $rdv->organizationSite->name }}</p>
                @elseif($rdv->ville)
                    <p class="truncate">📍 {{ $rdv->ville }}</p>
                @endif
            </div>
        </div>

        <div class="flex shrink-0 flex-col items-end gap-2">
            <x-badge :status="$rdv->status" />
            <x-priority-badge :priority="$rdv->priorite" />
        </div>
    </div>

    <div class="mt-3 grid grid-cols-1 gap-2 text-xs text-slate-600 sm:grid-cols-2">
        <div class="rounded-xl bg-white/70 px-3 py-2">
            <span class="font-semibold text-slate-700">Employé :</span>
            {{ $rdv->employe?->name ?? 'À assigner' }}
        </div>
        <div class="rounded-xl bg-white/70 px-3 py-2">
            <span class="font-semibold text-slate-700">Charge :</span>
            {{ $minutes }} min
        </div>
    </div>

    @if($isUnassigned || $isUrgent || $clientBrief)
        <div class="mt-3 space-y-2 text-xs">
            @if($isUnassigned)
                <div class="rounded-xl border border-dashed border-amber-300 bg-white/80 px-3 py-2 font-semibold text-amber-700">
                    Affectation requise : aucun employé n’est encore assigné.
                </div>
            @endif

            @if($clientBrief)
                <div class="rounded-xl bg-white/80 px-3 py-2 text-slate-600">
                    <span class="font-semibold text-slate-700">Brief :</span>
                    {{ $clientBrief }}
                </div>
            @endif
        </div>
    @endif
</div>
