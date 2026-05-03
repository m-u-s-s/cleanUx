@php
    $rdv = $mission->rendezVous;
    $client = $rdv?->client;
@endphp

<section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Client & accès</p>
            <h2 class="mt-1 text-xl font-black text-slate-900">Informations d’intervention</h2>
        </div>

        @if($rdv?->priorite)
            <x-priority-badge :priority="$rdv->priorite" />
        @endif
    </div>

    <div class="mt-5 grid gap-3 sm:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Client</p>
            <p class="mt-1 font-black text-slate-900">{{ $client?->name ?? 'Client non précisé' }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ $client?->email ?? '—' }}</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Téléphone</p>
            <p class="mt-1 font-black text-slate-900">{{ $rdv?->telephone_client ?? '—' }}</p>
            @if($rdv?->telephone_client)
                <a href="tel:{{ $rdv->telephone_client }}" class="mt-2 inline-flex text-sm font-bold text-blue-700 hover:text-blue-900">Appeler maintenant</a>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:col-span-2">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Adresse</p>
            <p class="mt-1 font-black text-slate-900">
                {{ $rdv?->adresse ?? 'Adresse non précisée' }}{{ $rdv?->ville ? ', '.$rdv->ville : '' }}
            </p>
            @if($rdv?->code_postal || $rdv?->serviceZone?->name)
                <p class="mt-1 text-sm text-slate-500">
                    {{ $rdv?->code_postal }} {{ $rdv?->serviceZone?->name ? '· '.$rdv->serviceZone->name : '' }}
                </p>
            @endif
        </div>

        @if($rdv?->notes)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:col-span-2">
                <p class="text-xs font-bold uppercase tracking-wide text-amber-700">Notes client</p>
                <p class="mt-1 text-sm leading-6 text-amber-900">{{ $rdv->notes }}</p>
            </div>
        @endif

        @if($mission->notes)
            <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 sm:col-span-2">
                <p class="text-xs font-bold uppercase tracking-wide text-blue-700">Notes mission</p>
                <p class="mt-1 text-sm leading-6 text-blue-900">{{ $mission->notes }}</p>
            </div>
        @endif
    </div>
</section>
