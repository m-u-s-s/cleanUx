<div class="flex flex-wrap gap-2 pt-2">
    @if($rdv->mission)
    <a href="{{ route('missions.tracking.live', $rdv->mission) }}"
        class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
        📍 Suivre la mission
    </a>
    @endif

    @if($rdv->mission)
    <a
        href="{{ route('client.missions.tracking', $rdv->mission) }}"
        class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">
        Suivre mon employé
    </a>
    @endif

    @if($rdv->canStillBeEditedByClient())
    <button wire:click="modifier({{ $rdv->id }})"
        class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
        🔁 Replanifier
    </button>

    <button wire:click="demanderAnnulation({{ $rdv->id }})"
        class="rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100">
        Annuler
    </button>
    @endif

    @if($rdv->recurring_series_id)
    <a href="{{ route('client.rendezvous.series.edit', $rdv->id) }}"
        class="rounded-xl border px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">
        🗓️ Gérer la série
    </a>
    @endif

    @if($rdv->status === 'termine' && $rdv->feedback)
    <span class="rounded-xl bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700">
        💬 Feedback laissé
    </span>
    @elseif($rdv->status === 'termine')
    <a href="{{ route('feedback.create', $rdv->id) }}"
        class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
        ⭐ Laisser un avis
    </a>
    @endif
</div>
