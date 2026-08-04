{{--
    Le deuxième niveau : les zones d'un pays.

    Le pays n'apparaît nulle part comme champ modifiable : il est le CONTEXTE de cet écran. Tout
    ce qu'on y crée ou modifie lui appartient, et le cloisonnement est tenu par la requête et non
    par ce fichier — une vue ne protège pas les actions.
--}}
<div class="space-y-6">

    {{-- ─── Fil d'Ariane ────────────────────────────────────────────────────────────────── --}}
    <nav class="flex items-center gap-2 text-sm text-slate-500">
        <a href="{{ route('admin.order-engine.catalog') }}" class="hover:text-slate-900">Catalogue</a>
        <span aria-hidden="true">›</span>
        <span class="font-medium text-slate-900">{{ $country->name }}</span>
    </nav>

    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Zones — {{ $country->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">
                Chaque zone porte son propre catalogue de métiers et ses propres prix.
                Ouvrez une zone pour régler ce qu’elle propose.
            </p>
        </div>

        @unless ($country->is_active)
            {{--
                L'information manquerait cruellement ici : on peut passer un long moment à régler
                des zones sans comprendre pourquoi rien n'est joignable.
            --}}
            <p class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <strong>{{ $country->name }} est désactivé.</strong>
                Aucune de ses zones n’est joignable, quel que soit leur réglage propre.
            </p>
        @endunless
    </header>

    @if (session('success'))
        <p class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('success') }}</p>
    @endif

    @if ($blocage)
        <p class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $blocage }}</p>
    @endif

    {{-- ─── Création ────────────────────────────────────────────────────────────────────── --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-900">Nouvelle zone</h2>

        <div class="mt-4 flex flex-wrap items-end gap-4">
            <label class="block min-w-[220px] flex-1">
                <span class="text-xs font-medium text-slate-600">Nom</span>
                <input type="text" wire:model="nouvelleZone.name" placeholder="Bruxelles"
                    class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                @error('nouvelleZone.name')
                    <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>
                @enderror
            </label>

            <label class="block min-w-[160px]">
                <span class="text-xs font-medium text-slate-600">Code</span>
                <input type="text" wire:model="nouvelleZone.code" placeholder="BRU"
                    class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm uppercase">
                @error('nouvelleZone.code')
                    <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>
                @enderror
            </label>

            <button type="button" wire:click="creerZone"
                class="min-h-[44px] rounded-xl bg-slate-900 px-5 text-sm font-medium text-white transition hover:bg-slate-800">
                Créer la zone
            </button>
        </div>

        <p class="mt-3 text-xs text-slate-500">
            Une zone neuve naît <strong>fermée</strong> : réglez son catalogue et ses prix avant de l’ouvrir.
        </p>
    </section>

    {{-- ─── Recherche ───────────────────────────────────────────────────────────────────── --}}
    <label class="block max-w-md">
        <span class="sr-only">Rechercher une zone</span>
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Rechercher une zone…"
            class="w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
    </label>

    {{-- ─── Liste ───────────────────────────────────────────────────────────────────────── --}}
    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Zone</th>
                    <th class="px-4 py-3">Code</th>
                    <th class="px-4 py-3">État</th>
                    <th class="px-4 py-3">Réservable</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
                @forelse ($zones as $zone)
                    <tr wire:key="zone-{{ $zone->id }}">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $zone->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $zone->code }}</td>

                        <td class="px-4 py-3">
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-600">
                                {{ $zone->status }}
                            </span>
                        </td>

                        <td class="px-4 py-3">
                            @if ($zone->is_bookable)
                                <span class="rounded-full bg-emerald-50 px-2 py-1 text-xs text-emerald-700">Oui</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-600">Non</span>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <a href="{{ route('admin.order-engine.catalog.zone', [$country, $zone]) }}"
                                    class="inline-flex min-h-[36px] items-center rounded-lg bg-slate-900 px-3 text-xs font-medium text-white transition hover:bg-slate-800">
                                    Ouvrir le catalogue
                                </a>

                                <button type="button" wire:click="selectZone({{ $zone->id }})"
                                    class="min-h-[36px] rounded-lg border border-slate-300 px-3 text-xs text-slate-700 transition hover:bg-slate-50">
                                    Régler
                                </button>

                                <button type="button" wire:click="supprimerZone({{ $zone->id }})"
                                    class="min-h-[36px] rounded-lg border border-rose-200 px-3 text-xs text-rose-700 transition hover:bg-rose-50">
                                    Supprimer
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">
                            Aucune zone dans ce pays. Créez-en une ci-dessus.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $zones->links() }}</div>

    {{-- ─── Réglage de la zone sélectionnée ─────────────────────────────────────────────── --}}
    @if ($selectedZone)
        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-slate-900">Réglages — {{ $selectedZone->name }}</h2>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <label class="block">
                    <span class="text-xs font-medium text-slate-600">Nom</span>
                    <input type="text" wire:model="name"
                        class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                </label>

                <label class="block">
                    <span class="text-xs font-medium text-slate-600">Statut</span>
                    <select wire:model="status" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                        <option value="draft">Brouillon</option>
                        <option value="active">Active</option>
                        <option value="paused">En pause</option>
                        <option value="archived">Archivée</option>
                    </select>
                </label>

                <label class="block">
                    <span class="text-xs font-medium text-slate-600">Priorité</span>
                    <input type="number" wire:model="priority"
                        class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                </label>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button type="button" wire:click="saveZone"
                    class="min-h-[44px] rounded-xl bg-slate-900 px-5 text-sm font-medium text-white transition hover:bg-slate-800">
                    Enregistrer
                </button>

                {{--
                    Ces deux interrupteurs ne touchent QUE la zone : désactiver Bruxelles ne
                    change rien à la Belgique, exactement comme désactiver la Belgique ne change
                    rien au réglage propre de Bruxelles.
                --}}
                <button type="button" wire:click="toggleZoneBookability"
                    class="min-h-[44px] rounded-xl border border-slate-300 px-4 text-sm text-slate-700 transition hover:bg-slate-50">
                    {{ $selectedZone->is_bookable ? 'Fermer aux réservations' : 'Ouvrir aux réservations' }}
                </button>

                <button type="button" wire:click="toggleZoneVisibility"
                    class="min-h-[44px] rounded-xl border border-slate-300 px-4 text-sm text-slate-700 transition hover:bg-slate-50">
                    {{ $selectedZone->is_visible ? 'Masquer' : 'Rendre visible' }}
                </button>
            </div>
        </section>
    @endif
</div>
