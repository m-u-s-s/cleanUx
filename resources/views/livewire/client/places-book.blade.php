<div class="mx-auto max-w-3xl px-4 py-8">

    <header class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Mes lieux</h1>
        <p class="mt-1 text-sm text-slate-500">
            L'adresse, l'étage, le code — enregistrés une fois, transmis au bon moment.
        </p>
    </header>

    @if ($refus)
    <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        {{ $refus }}
    </div>
    @endif

    {{-- Le formulaire --}}
    <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-slate-500">
            {{ $lieuEnEditionId ? 'Modifier le lieu' : 'Nouveau lieu' }}
        </h2>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Nom du lieu</span>
                <input type="text" wire:model="libelle" placeholder="Chez moi, Maison de maman…"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                @error('libelle')
                <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Adresse</span>
                <input type="text" wire:model="adresse" placeholder="Rue Haute 1"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                @error('adresse')
                <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Ville</span>
                <input type="text" wire:model="ville"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Code postal</span>
                <input type="text" wire:model="codePostal"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
            </label>
        </div>

        <div class="mt-5 rounded-xl bg-slate-50 p-4">
            <p class="mb-3 text-xs font-bold uppercase tracking-wide text-slate-500">
                Accès — visible par le prestataire uniquement à son arrivée sur place
            </p>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-sm font-semibold text-slate-900">Étage / porte</span>
                    <input type="text" wire:model="etage" placeholder="3e étage, porte gauche"
                        class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                </label>

                <label class="flex items-end gap-2 pb-2">
                    <input type="checkbox" wire:model="alarme"
                        class="rounded border-slate-300 text-blue-600">
                    <span class="text-sm text-slate-900">Il y a une alarme à désactiver</span>
                </label>
            </div>

            <label class="mt-4 block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Consignes d'accès</span>
                <textarea wire:model="consignes" rows="2" placeholder="Digicode, boîte à clés, voisin…"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900"></textarea>
            </label>
        </div>

        <div class="mt-5 grid gap-4 sm:grid-cols-3">
            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Produits</span>
                <input type="text" wire:model="produits" placeholder="Sans chlore"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Allergies</span>
                <input type="text" wire:model="allergies"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Animaux</span>
                <input type="text" wire:model="animaux" placeholder="Un chat, qui se cache"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
            </label>
        </div>

        <div class="mt-5 flex flex-wrap gap-3">
            <button type="button" wire:click="enregistrer"
                class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">
                {{ $lieuEnEditionId ? 'Enregistrer les modifications' : 'Ajouter ce lieu' }}
            </button>

            @if ($lieuEnEditionId)
            <button type="button" wire:click="annulerLEdition"
                class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                Annuler
            </button>
            @endif
        </div>
    </div>

    {{-- Les lieux --}}
    <div class="rounded-2xl border border-slate-200 bg-white">
        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-bold uppercase tracking-wide text-slate-500">
            Mes lieux ({{ $lieux->count() }}/{{ $maximum }})
        </h2>

        @forelse ($lieux as $lieu)
        <div class="border-b border-slate-50 px-5 py-4 last:border-0" wire:key="lieu-{{ $lieu->id }}">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-900">
                        {{ $lieu->label }}
                        @if ($lieu->is_default)
                        <span class="ml-2 rounded-full bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-700">
                            Par défaut
                        </span>
                        @endif
                    </p>
                    <p class="text-xs text-slate-500">
                        {{ $lieu->address }}
                        @if ($lieu->city) — {{ $lieu->postal_code }} {{ $lieu->city }} @endif
                    </p>
                    @if ($lieu->floor || $lieu->access_instructions)
                    <p class="mt-1 text-xs text-slate-400">
                        {{ collect([$lieu->floor, $lieu->access_instructions])->filter()->implode(' · ') }}
                    </p>
                    @endif
                </div>

                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    @unless ($lieu->is_default)
                    <button type="button" wire:click="definirParDefaut({{ $lieu->id }})"
                        class="text-xs font-semibold text-blue-600 hover:underline">
                        Par défaut
                    </button>
                    @endunless
                    <button type="button" wire:click="modifier({{ $lieu->id }})"
                        class="text-xs font-semibold text-slate-600 hover:underline">
                        Modifier
                    </button>
                    <button type="button" wire:click="archiver({{ $lieu->id }})"
                        class="text-xs font-semibold text-rose-600 hover:underline">
                        Archiver
                    </button>
                </div>
            </div>
        </div>
        @empty
        <p class="px-5 py-8 text-center text-sm text-slate-500">
            Aucun lieu enregistré. Le premier que vous ajoutez devient votre lieu par défaut.
        </p>
        @endforelse
    </div>

    @if ($archives->isNotEmpty())
    <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50/50 px-5 py-4">
        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Lieux archivés</p>
        <ul class="space-y-1 text-xs text-slate-500">
            @foreach ($archives as $archive)
            <li>{{ $archive->label }} — {{ $archive->address }}</li>
            @endforeach
        </ul>
        <p class="mt-2 text-xs text-slate-400">
            Conservés : vos interventions passées y font référence.
        </p>
    </div>
    @endif
</div>
