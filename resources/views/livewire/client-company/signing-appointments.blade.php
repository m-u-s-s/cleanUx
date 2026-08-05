<div class="mx-auto max-w-4xl px-4 py-6">

    <header class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Signatures sur place</h1>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
            Fixez un rendez-vous pour signer un contrat en présence, dans l'un de vos locaux.
        </p>
    </header>

    {{-- Prise de rendez-vous --}}
    <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="grid gap-4 sm:grid-cols-3">
            <label class="block sm:col-span-3">
                <span class="mb-1 block text-sm font-semibold text-slate-900 dark:text-white">Local (facultatif)</span>
                <select wire:model="siteId"
                    class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    <option value="">Ailleurs / à distance</option>
                    @foreach ($sites as $site)
                    <option value="{{ $site->id }}">{{ $site->name }} — {{ $site->site_code }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900 dark:text-white">Date</span>
                <input type="date" wire:model="date"
                    class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                @error('date')
                <p class="mt-1 text-xs font-semibold text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900 dark:text-white">Heure</span>
                <input type="time" wire:model="heure"
                    class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900 dark:text-white">Note</span>
                <input type="text" wire:model="notes" placeholder="Contrat-cadre…"
                    class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">
            </label>
        </div>

        <button type="button" wire:click="planifier"
            class="mt-4 w-full rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 sm:w-auto">
            Planifier la signature
        </button>
    </div>

    {{-- Rendez-vous existants --}}
    <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
        Rendez-vous
    </h2>

    @forelse ($rendezVous as $rdv)
    <div class="mb-2 flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-800">
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">
                {{ $rdv->scheduled_at->translatedFormat('d F Y à H:i') }}
                @if ($rdv->organizationSite)
                — {{ $rdv->organizationSite->name }}
                @else
                — à distance
                @endif
            </p>
            <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                {{ $rdv->signer?->name }}@if ($rdv->notes) · {{ $rdv->notes }}@endif
            </p>
        </div>

        @if ($rdv->status === \App\Models\SigningAppointment::STATUT_PLANIFIE)
        <button type="button" wire:click="marquerSigne({{ $rdv->id }})"
            class="shrink-0 rounded-lg border border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 dark:border-emerald-700 dark:text-emerald-300 dark:hover:bg-emerald-900/30">
            Marquer signé
        </button>
        @else
        <span class="shrink-0 text-xs font-semibold text-slate-500 dark:text-slate-400">
            {{ $rdv->status === \App\Models\SigningAppointment::STATUT_SIGNE ? 'Signé' : 'Annulé' }}
        </span>
        @endif
    </div>
    @empty
    <p class="text-sm text-slate-500 dark:text-slate-400">Aucun rendez-vous de signature planifié.</p>
    @endforelse
</div>
