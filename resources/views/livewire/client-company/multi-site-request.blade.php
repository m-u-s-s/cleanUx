<div class="mx-auto max-w-4xl px-4 py-6">

    <header class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-900">Demande multi-locaux</h1>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-500">
            Une même prestation, planifiée d'un coup pour plusieurs de vos locaux.
        </p>
    </header>

    @if ($demandeCreee)
    <div class="mb-6 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-3 dark:border-emerald-700 dark:bg-emerald-900/30">
        <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-200">
            Demande enregistrée. Chaque local sélectionné a reçu sa réservation.
        </p>
        <a href="{{ route('client-company.bookings.index') }}"
            class="mt-1 inline-block text-sm font-semibold text-emerald-700 underline dark:text-emerald-700">
            Voir mes réservations
        </a>
    </div>
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-200 dark:bg-white">

        {{-- Sélection des locaux --}}
        <fieldset class="mb-5">
            <legend class="mb-2 text-sm font-bold text-slate-900 dark:text-slate-900">Locaux concernés</legend>

            @forelse ($sites as $site)
            <label class="mb-1 flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50 dark:border-slate-200 dark:hover:bg-slate-100/40">
                <input type="checkbox" wire:model="siteIds" value="{{ $site->id }}" class="rounded">
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium text-slate-900 dark:text-slate-900">{{ $site->name }}</span>
                    <span class="block truncate text-xs text-slate-400 dark:text-slate-500">
                        {{ $site->site_code }}@if ($site->city) — {{ $site->city }}@endif
                    </span>
                </span>
            </label>
            @empty
            <p class="text-sm text-slate-400 dark:text-slate-500">
                Aucun local enregistré.
                <a href="{{ route('client-company.sites') }}" class="font-semibold underline">En ajouter un</a>
            </p>
            @endforelse

            @error('siteIds')
            <p class="mt-1 text-xs font-semibold text-rose-600 dark:text-rose-600">{{ $message }}</p>
            @enderror
        </fieldset>

        {{-- Prestation --}}
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900 dark:text-slate-900">Prestation</span>
                <select wire:model="tradeId"
                    class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-300 dark:bg-white dark:text-slate-900">
                    <option value="">Choisir…</option>
                    @foreach ($trades as $trade)
                    <option value="{{ $trade->id }}">{{ $trade->name }}</option>
                    @endforeach
                </select>
                @error('tradeId')
                <p class="mt-1 text-xs font-semibold text-rose-600 dark:text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900 dark:text-slate-900">Durée par local (min)</span>
                <input type="number" min="15" step="15" wire:model="dureeEstimee"
                    class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-300 dark:bg-white dark:text-slate-900">
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900 dark:text-slate-900">Date</span>
                <input type="date" wire:model="date"
                    class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-300 dark:bg-white dark:text-slate-900">
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900 dark:text-slate-900">Heure</span>
                <input type="time" wire:model="heure"
                    class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-300 dark:bg-white dark:text-slate-900">
            </label>
        </div>

        <label class="mt-4 block">
            <span class="mb-1 block text-sm font-semibold text-slate-900 dark:text-slate-900">Précisions (facultatif)</span>
            <textarea wire:model="commentaire" rows="3"
                class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-300 dark:bg-white dark:text-slate-900"></textarea>
        </label>

        <button type="button" wire:click="creer"
            class="mt-5 w-full rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 sm:w-auto">
            Envoyer la demande
        </button>
    </div>
</div>
