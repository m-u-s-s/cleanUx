<div class="mx-auto max-w-5xl">

    <header class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Planning et absences</h1>
        <p class="mt-1 text-sm text-slate-500">
            Qui travaille quand, et qui s'absente. La répartition des missions lit les deux.
        </p>
    </header>

    @if ($refus)
    <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        {{ $refus }}
    </div>
    @endif

    {{-- Navigation de semaine --}}
    <div class="mb-6 flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4">
        <button type="button" wire:click="semainePrecedente"
            class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            ← Semaine précédente
        </button>

        <p class="text-sm font-semibold text-slate-900">
            Semaine du {{ $lundi->format('d/m/Y') }}
        </p>

        <button type="button" wire:click="semaineSuivante"
            class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
            Semaine suivante →
        </button>
    </div>

    @if ($peutGerer)
    {{-- Ajouter un créneau --}}
    <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-500">Ajouter un créneau</h2>

        <div class="grid gap-4 sm:grid-cols-3">
            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Personne</span>
                <select wire:model="shiftUserId"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                    <option value="">Choisir…</option>
                    @foreach ($collegues as $membre)
                    <option value="{{ $membre->user_id }}">{{ $membre->user?->name }}</option>
                    @endforeach
                </select>
                @error('shiftUserId')
                <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Début</span>
                <input type="datetime-local" wire:model="shiftDebut"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                @error('shiftDebut')
                <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Fin</span>
                <input type="datetime-local" wire:model="shiftFin"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                @error('shiftFin')
                <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </label>
        </div>

        <div class="mt-4 flex flex-wrap gap-3">
            <button type="button" wire:click="ajouterUnCreneau"
                class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
                Ajouter au brouillon
            </button>

            {{-- Publier engage : c'est le geste qui rend l'équipe assignable. --}}
            <button type="button" wire:click="publierLaSemaine"
                class="rounded-xl border border-emerald-600 px-4 py-2.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50">
                Publier la semaine
            </button>
        </div>

        <p class="mt-3 text-xs text-slate-500">
            Un créneau en brouillon ne rend personne assignable. Tant qu'aucun créneau n'est publié,
            la répartition fonctionne comme avant.
        </p>
    </div>
    @endif

    {{-- Le planning de la semaine --}}
    <div class="mb-8 rounded-2xl border border-slate-200 bg-white">
        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-bold uppercase tracking-wide text-slate-500">
            Créneaux de la semaine
        </h2>

        @forelse ($creneaux as $creneau)
        <div class="flex items-center justify-between border-b border-slate-50 px-5 py-3 last:border-0">
            <div>
                <p class="text-sm font-semibold text-slate-900">{{ $creneau->user?->name }}</p>
                <p class="text-xs text-slate-500">
                    {{ $creneau->starts_at->format('D d/m H:i') }} → {{ $creneau->ends_at->format('H:i') }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                @if ($creneau->status === \App\Models\Shift::STATUS_PUBLISHED)
                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Publié</span>
                @else
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Brouillon</span>
                @endif

                @if ($peutGerer)
                <button type="button" wire:click="annulerLeCreneau({{ $creneau->id }})"
                    class="text-xs font-semibold text-rose-600 hover:underline">
                    Annuler
                </button>
                @endif
            </div>
        </div>
        @empty
        <p class="px-5 py-8 text-center text-sm text-slate-500">
            Aucun créneau cette semaine.
        </p>
        @endforelse
    </div>

    {{-- Poser une absence --}}
    <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-500">Poser une absence</h2>

        <div class="grid gap-4 sm:grid-cols-3">
            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Du</span>
                <input type="date" wire:model="congeDebut"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                @error('congeDebut')
                <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Au (inclus)</span>
                <input type="date" wire:model="congeFin"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                @error('congeFin')
                <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Type</span>
                <select wire:model="congeType"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                    <option value="paid">Congé payé</option>
                    <option value="unpaid">Sans solde</option>
                    <option value="sick">Maladie</option>
                    <option value="other">Autre</option>
                </select>
            </label>
        </div>

        <label class="mt-4 block">
            <span class="mb-1 block text-sm font-semibold text-slate-900">Motif (facultatif)</span>
            <input type="text" wire:model="congeMotif"
                class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
        </label>

        <button type="button" wire:click="poserUneAbsence"
            class="mt-4 w-full rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 sm:w-auto">
            Envoyer la demande
        </button>
    </div>

    @if ($peutGerer && $enAttente->isNotEmpty())
    {{-- À trancher --}}
    <div class="mb-8 rounded-2xl border border-amber-200 bg-amber-50/50">
        <h2 class="border-b border-amber-100 px-5 py-3 text-sm font-bold uppercase tracking-wide text-amber-800">
            Demandes en attente
        </h2>

        @foreach ($enAttente as $demande)
        <div class="flex items-center justify-between border-b border-amber-100 px-5 py-3 last:border-0">
            <div>
                <p class="text-sm font-semibold text-slate-900">{{ $demande->user?->name }}</p>
                <p class="text-xs text-slate-600">
                    Du {{ $demande->starts_on->format('d/m/Y') }} au {{ $demande->ends_on->format('d/m/Y') }}
                    @if ($demande->reason) — {{ $demande->reason }} @endif
                </p>
            </div>

            <div class="flex gap-2">
                <button type="button" wire:click="statuerSurLAbsence({{ $demande->id }}, true)"
                    class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                    Approuver
                </button>
                <button type="button" wire:click="statuerSurLAbsence({{ $demande->id }}, false)"
                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-white">
                    Refuser
                </button>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Les absences qui touchent la semaine --}}
    <div class="rounded-2xl border border-slate-200 bg-white">
        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-bold uppercase tracking-wide text-slate-500">
            Absences sur la semaine
        </h2>

        @forelse ($absences as $absence)
        <div class="flex items-center justify-between border-b border-slate-50 px-5 py-3 last:border-0">
            <div>
                <p class="text-sm font-semibold text-slate-900">{{ $absence->user?->name }}</p>
                <p class="text-xs text-slate-500">
                    Du {{ $absence->starts_on->format('d/m') }} au {{ $absence->ends_on->format('d/m') }}
                </p>
            </div>

            @if ($absence->status === \App\Models\LeaveRequest::STATUS_APPROVED)
            <span class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">
                Approuvée — bloque le planning
            </span>
            @else
            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                {{ $absence->status === \App\Models\LeaveRequest::STATUS_PENDING ? 'En attente' : 'Traitée' }}
            </span>
            @endif
        </div>
        @empty
        <p class="px-5 py-8 text-center text-sm text-slate-500">
            Personne n'est absent cette semaine.
        </p>
        @endforelse
    </div>
</div>
