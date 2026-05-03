@php
    $rdv = $mission->rendezVous;
@endphp

<div class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 px-4 py-3 shadow-[0_-12px_30px_rgba(15,23,42,0.12)] backdrop-blur lg:hidden">
    <div class="mx-auto flex max-w-3xl items-center justify-between gap-3">
        <div class="min-w-0">
            <p class="truncate text-xs font-bold uppercase tracking-wide text-slate-500">Mission #{{ $mission->id }}</p>
            <p class="truncate text-sm font-black text-slate-900">{{ $rdv?->service_display_name ?: 'Intervention terrain' }}</p>
        </div>

        <div class="flex shrink-0 gap-2">
            @if($rdv?->telephone_client)
                <a href="tel:{{ $rdv->telephone_client }}" class="rounded-2xl bg-emerald-600 px-3 py-2 text-sm font-black text-white">📞</a>
            @endif

            @if($rdv?->adresse || $rdv?->ville)
                <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode(($rdv->adresse ?? '').' '.($rdv->ville ?? '')) }}" target="_blank" class="rounded-2xl bg-blue-600 px-3 py-2 text-sm font-black text-white">GPS</a>
            @endif

            @if(Route::has('employe.missions'))
                <a href="{{ route('employe.missions') }}" class="rounded-2xl bg-slate-900 px-3 py-2 text-sm font-black text-white">Retour</a>
            @endif
        </div>
    </div>
</div>
