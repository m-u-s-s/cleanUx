<div class="mx-auto max-w-4xl">

    <header class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Recrutement</h1>
        <p class="mt-1 text-sm text-slate-500">
            Vos offres et les candidatures reçues. Embaucher envoie l'invitation.
        </p>
    </header>

    @if ($refus)
    <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        {{ $refus }}
    </div>
    @endif

    @if ($peutGerer)
    {{-- Nouvelle offre --}}
    <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-500">Nouvelle offre</h2>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Intitulé</span>
                <input type="text" wire:model="titre" placeholder="Agent d'entretien (H/F)"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                @error('titre')
                <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Métier</span>
                <select wire:model="tradeId"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                    <option value="">Tous métiers</option>
                    @foreach ($metiers as $metier)
                    <option value="{{ $metier->id }}">{{ $metier->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <label class="mt-4 block">
            <span class="mb-1 block text-sm font-semibold text-slate-900">Description</span>
            <textarea wire:model="description" rows="3"
                class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900"></textarea>
        </label>

        <button type="button" wire:click="ouvrirUneOffre"
            class="mt-4 w-full rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 sm:w-auto">
            Créer le brouillon
        </button>

        <p class="mt-3 text-xs text-slate-500">
            On ne publie pas en créant : une offre à moitié écrite attire des candidatures à moitié
            pertinentes, qu'il faudra trier quand même.
        </p>
    </div>
    @endif

    {{-- L'offre ouverte et ses candidatures --}}
    @if ($offreOuverte)
    <div class="mb-8 rounded-2xl border border-blue-200 bg-white p-5">
        <div class="mb-4">
            <p class="text-base font-bold text-slate-900">{{ $offreOuverte->title }}</p>
            <p class="text-xs text-slate-500">{{ $offreOuverte->reference }}</p>
        </div>

        <div class="divide-y divide-slate-100 rounded-xl border border-slate-100">
            @forelse ($offreOuverte->applications as $candidature)
            <div class="flex items-center justify-between gap-3 px-4 py-3">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-900">{{ $candidature->full_name }}</p>
                    <p class="truncate text-xs text-slate-500">
                        {{ $candidature->email }}
                        @if ($candidature->phone) · {{ $candidature->phone }} @endif
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    @if ($candidature->status === \App\Models\JobApplication::STATUS_HIRED)
                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                        Invitation envoyée
                    </span>
                    @elseif ($candidature->status === \App\Models\JobApplication::STATUS_REJECTED)
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                        Écartée
                    </span>
                    @elseif ($peutGerer)
                    @if ($candidature->status === \App\Models\JobApplication::STATUS_RECEIVED)
                    <button type="button" wire:click="statuerSurLaCandidature({{ $candidature->id }}, 'shortlist')"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        Retenir
                    </button>
                    @endif
                    <button type="button" wire:click="statuerSurLaCandidature({{ $candidature->id }}, 'hire')"
                        class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                        Embaucher
                    </button>
                    <button type="button" wire:click="statuerSurLaCandidature({{ $candidature->id }}, 'reject')"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        Écarter
                    </button>
                    @endif
                </div>
            </div>
            @empty
            <p class="px-4 py-6 text-center text-sm text-slate-500">Aucune candidature.</p>
            @endforelse
        </div>
    </div>
    @endif

    {{-- Les offres --}}
    <div class="rounded-2xl border border-slate-200 bg-white">
        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-bold uppercase tracking-wide text-slate-500">
            Vos offres
        </h2>

        @forelse ($offres as $offre)
        <div class="flex items-center justify-between gap-3 border-b border-slate-50 px-5 py-3 last:border-0">
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-slate-900">{{ $offre->title }}</p>
                <p class="text-xs text-slate-500">
                    {{ $offre->trade?->name ?? 'Tous métiers' }}
                    · {{ $offre->applications_count }} candidature(s)
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-3">
                @if ($offre->status === \App\Models\JobPosting::STATUS_PUBLISHED)
                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Publiée</span>
                @elseif ($offre->status === \App\Models\JobPosting::STATUS_CLOSED)
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Fermée</span>
                @else
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Brouillon</span>
                @endif

                @if ($peutGerer && $offre->status === \App\Models\JobPosting::STATUS_DRAFT)
                <button type="button" wire:click="publier({{ $offre->id }})"
                    class="text-xs font-semibold text-blue-600 hover:underline">
                    Publier
                </button>
                @elseif ($peutGerer && $offre->status === \App\Models\JobPosting::STATUS_PUBLISHED)
                <button type="button" wire:click="fermer({{ $offre->id }})"
                    class="text-xs font-semibold text-slate-600 hover:underline">
                    Fermer
                </button>
                @endif

                <button type="button" wire:click="consulterLOffre({{ $offre->id }})"
                    class="text-xs font-semibold text-blue-600 hover:underline">
                    Candidatures
                </button>
            </div>
        </div>
        @empty
        <p class="px-5 py-8 text-center text-sm text-slate-500">
            Aucune offre. Jusqu'ici, tout le recrutement se faisait hors de la plateforme.
        </p>
        @endforelse
    </div>
</div>
