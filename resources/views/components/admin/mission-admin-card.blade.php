@props(['rdv'])

<div class="cu-card p-5 md:p-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="space-y-2">
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                    {{ $rdv->booking_reference ?: 'RDV-'.$rdv->id }}
                </span>
                <x-badge :status="$rdv->status" />
                <x-priority-badge :priority="$rdv->priorite" />
                @if(!$rdv->employe_id && in_array($rdv->status, ['en_attente', 'confirme'], true))
                    <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">Sans employé</span>
                @endif
                @if($rdv->mission)
                    <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Mission liée</span>
                @endif
            </div>

            <div>
                <h3 class="text-lg font-bold text-slate-900">{{ $rdv->service_display_name ?: 'Mission sans service' }}</h3>
                <p class="mt-1 text-sm text-slate-500">
                    {{ optional($rdv->date)->format('d/m/Y') ?: '—' }} à {{ substr((string) $rdv->heure, 0, 5) ?: '—' }}
                    @if($rdv->organizationAccount)
                        • {{ $rdv->organizationAccount->name }}
                        @if($rdv->organizationSite)
                            / {{ $rdv->organizationSite->name }}
                        @endif
                    @endif
                </p>
            </div>
        </div>

        <div class="grid min-w-[240px] grid-cols-2 gap-3 text-sm">
            <div class="rounded-2xl bg-slate-50 p-3">
                <div class="text-xs uppercase tracking-wide text-slate-400">Client</div>
                <div class="mt-1 font-semibold text-slate-800">{{ $rdv->client?->name ?: '—' }}</div>
            </div>
            <div class="rounded-2xl bg-slate-50 p-3">
                <div class="text-xs uppercase tracking-wide text-slate-400">Employé</div>
                <div class="mt-1 font-semibold text-slate-800">{{ $rdv->employe?->name ?: 'Non assigné' }}</div>
            </div>
            <div class="rounded-2xl bg-slate-50 p-3">
                <div class="text-xs uppercase tracking-wide text-slate-400">Zone</div>
                <div class="mt-1 font-semibold text-slate-800">{{ $rdv->serviceZone?->name ?: '—' }}</div>
            </div>
            <div class="rounded-2xl bg-slate-50 p-3">
                <div class="text-xs uppercase tracking-wide text-slate-400">Durée estimée</div>
                <div class="mt-1 font-semibold text-slate-800">{{ $rdv->duree_estimee ? $rdv->duree_estimee.' min' : '—' }}</div>
            </div>
        </div>
    </div>

    <div class="mt-4 grid gap-4 md:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 p-4">
            <div class="text-xs uppercase tracking-wide text-slate-400">Adresse</div>
            <div class="mt-1 text-sm font-medium text-slate-800">{{ $rdv->location_display_name ?: ($rdv->adresse ?: 'Adresse non renseignée') }}</div>
            <div class="mt-2 text-sm text-slate-500">
                {{ $rdv->postal_code_display ?: '—' }} {{ $rdv->ville ?: '' }}
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 p-4">
            <div class="text-xs uppercase tracking-wide text-slate-400">Contexte opérationnel</div>
            <div class="mt-1 grid grid-cols-2 gap-3 text-sm text-slate-600">
                <div><span class="font-medium text-slate-800">Fréquence :</span> {{ $rdv->frequence ?: '—' }}</div>
                <div><span class="font-medium text-slate-800">Surface :</span> {{ $rdv->surface ?: '—' }}</div>
                <div><span class="font-medium text-slate-800">Parking :</span> {{ $rdv->acces_parking ? 'Oui' : 'Non' }}</div>
                <div><span class="font-medium text-slate-800">Animaux :</span> {{ $rdv->presence_animaux ? 'Oui' : 'Non' }}</div>
            </div>
        </div>
    </div>

    @if($rdv->commentaire_client)
        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
            <span class="font-semibold text-slate-800">Brief client :</span>
            {{ $rdv->commentaire_client }}
        </div>
    @endif

    <div class="mt-4 flex flex-wrap gap-2">
        @if($rdv->mission)
            <a href="{{ route('missions.show', $rdv->mission) }}" class="cu-btn-secondary">Ouvrir mission</a>
            <a href="{{ route('missions.report.pdf', $rdv->mission) }}" class="cu-btn-secondary">Rapport PDF</a>
        @endif
        <a href="{{ route('admin.finance') }}?search={{ urlencode($rdv->booking_reference ?: 'RDV-'.$rdv->id) }}" class="cu-btn-secondary">Voir finance</a>
    </div>
</div>
