<div class="bg-white border rounded-2xl shadow-sm p-4">
    <div class="flex flex-col md:flex-row md:justify-between gap-3">
        <div>
            <p class="font-semibold text-slate-900 text-lg">
                {{ $rdv->service_display_name }}
            </p>
            <p class="text-sm text-gray-600">
                📅 {{ $rdv->date }} à {{ $rdv->heure }}
            </p>
            <p class="text-sm text-gray-600">
                👤 {{ $rdv->client->name ?? '—' }} • 🧑‍💼 {{ $rdv->employe->name ?? '—' }}
            </p>
            <p class="text-sm text-gray-600">
                📍 {{ $rdv->adresse ?? '—' }}, {{ $rdv->ville ?? '—' }}
            </p>
        </div>

        <div class="flex items-start gap-2">
            <x-badge :status="$rdv->status" />
            <x-priority-badge :priority="$rdv->priorite" />
        </div>

        <div class="flex flex-col gap-2 sm:flex-row md:flex-col lg:flex-row">
            <button
                type="button"
                wire:click="dispatchRendezVous({{ $rdv->id }})"
                class="rounded-xl bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
            >
                ⚡ Dispatch auto
            </button>

            <button
                type="button"
                wire:click="previewDispatch({{ $rdv->id }})"
                class="rounded-xl border px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                👀 Voir scoring
            </button>
        </div>
    </div>
</div>
