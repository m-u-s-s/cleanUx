{{--
    Le premier niveau du catalogue : les pays.

    Le pays n'organise QUE les zones : il ne porte aucun métier, et cet écran ne sait donc rien du
    catalogue lui-même. On y vient pour ouvrir un marché, pas pour régler une offre.
--}}
<div class="space-y-6">

    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Catalogue</h1>
            <p class="mt-1 text-sm text-slate-500">
                Chaque pays contient ses zones, et chaque zone son propre catalogue de métiers.
                Ouvrez un pays pour gérer ses zones.
            </p>
        </div>

        <button type="button" wire:click="nouveau"
            class="min-h-[44px] rounded-xl bg-slate-900 px-5 text-sm font-medium text-white transition hover:bg-slate-800">
            Ajouter un pays
        </button>
    </header>

    {{-- ─── Les cinq vues du catalogue ──────────────────────────────────────────────────── --}}
    <div class="flex flex-nowrap gap-2 overflow-x-auto border-b border-slate-200">
        @foreach (['pays' => 'Pays', 'zones' => 'Zones', 'metiers' => 'Métiers', 'services' => 'Services', 'marche' => 'Marché'] as $cle => $libelle)
            <button type="button" wire:click="$set('onglet', '{{ $cle }}')"
                    @class([
                        'px-4 py-2 min-h-[44px] inline-flex shrink-0 items-center whitespace-nowrap text-sm font-semibold',
                        'border-b-2 border-indigo-600 text-indigo-700' => $onglet === $cle,
                        'text-slate-500 hover:text-slate-900' => $onglet !== $cle,
                    ])>{{ $libelle }}</button>
        @endforeach
    </div>

    @if ($onglet === 'zones')
        <livewire:admin.gestion-zones />
    @elseif ($onglet === 'metiers')
        <livewire:admin.trades />
    @elseif ($onglet === 'services')
        <livewire:admin.catalogue-services />
    @elseif ($onglet === 'marche')
        <livewire:admin.international-operations-center />
    @endif

    @if ($onglet === 'pays')

    @if ($flash)
        <p class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ $flash }}</p>
    @endif

    {{--
        Le blocage n'est pas une erreur de formulaire : c'est une règle métier qui s'oppose à une
        action. Il porte donc le COMPTE de ce qui bloque, faute de quoi il faudrait ouvrir la base
        pour comprendre — ce à quoi un administrateur n'a pas accès.
    --}}
    @if ($blocage)
        <p class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $blocage }}</p>
    @endif

    {{-- ─── Formulaire ──────────────────────────────────────────────────────────────────── --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="text-sm font-semibold text-slate-900">
            {{ $editionId ? 'Modifier le pays' : 'Nouveau pays' }}
        </h2>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <label class="block">
                <span class="text-xs font-medium text-slate-600">Nom</span>
                <input type="text" wire:model="formulaire.name" placeholder="France"
                    class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                @error('formulaire.name')
                    <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>
                @enderror
            </label>

            <label class="block">
                <span class="text-xs font-medium text-slate-600">Code ISO (2 lettres)</span>
                {{-- `blur` et non `live` : on propose la devise quand le code est COMPLET, pas
                     a chaque lettre. `M` seul ne designe aucun pays. --}}
                <input type="text" wire:model="formulaire.iso_code" wire:blur="deduireLaDevise" maxlength="2" placeholder="FR"
                    class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm uppercase">
                @error('formulaire.iso_code')
                    <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>
                @enderror
            </label>

            <label class="block">
                <span class="text-xs font-medium text-slate-600">Code ISO 3 (facultatif)</span>
                <input type="text" wire:model="formulaire.iso3_code" maxlength="3" placeholder="FRA"
                    class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm uppercase">
                @error('formulaire.iso3_code')
                    <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>
                @enderror
            </label>

            <label class="block">
                <span class="text-xs font-medium text-slate-600">Devise</span>
                <input type="text" wire:model="formulaire.currency_code" maxlength="3" placeholder="proposee depuis le pays"
                    class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm uppercase">
                @error('formulaire.currency_code')
                    <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>
                @enderror
            </label>

            <label class="block">
                <span class="text-xs font-medium text-slate-600">Langue par défaut</span>
                <input type="text" wire:model="formulaire.default_locale" placeholder="fr_FR"
                    class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
            </label>

            <label class="block">
                <span class="text-xs font-medium text-slate-600">Fuseau horaire</span>
                <input type="text" wire:model="formulaire.timezone" placeholder="Europe/Paris"
                    class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
            </label>

            <label class="block">
                <span class="text-xs font-medium text-slate-600">Indicatif téléphonique</span>
                <input type="text" wire:model="formulaire.phone_code" placeholder="+33"
                    class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
            </label>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <button type="button" wire:click="enregistrer"
                class="min-h-[44px] rounded-xl bg-slate-900 px-5 text-sm font-medium text-white transition hover:bg-slate-800">
                {{ $editionId ? 'Enregistrer' : 'Ajouter' }}
            </button>

            @if ($editionId)
                <button type="button" wire:click="nouveau"
                    class="min-h-[44px] rounded-xl px-4 text-sm text-slate-600 transition hover:text-slate-900">
                    Annuler
                </button>
            @endif

            <p class="text-xs text-slate-500">
                Un pays neuf reste <strong>inactif</strong> jusqu’à ce que vous l’activiez.
            </p>
        </div>
    </section>

    {{-- ─── Liste ───────────────────────────────────────────────────────────────────────── --}}
    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3">Pays</th>
                    <th class="px-4 py-3">Devise</th>
                    {{-- Sans ce compte, il faut ouvrir chaque pays pour savoir lequel est en service. --}}
                    <th class="px-4 py-3">Zones</th>
                    <th class="px-4 py-3">État</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100">
                @forelse ($this->pays as $p)
                    <tr wire:key="pays-{{ $p->id }}">
                        <td class="px-4 py-3">
                            <span class="font-medium text-slate-900">{{ $p->name }}</span>
                            <span class="ml-2 text-xs text-slate-500">{{ $p->iso_code }}</span>
                        </td>

                        <td class="px-4 py-3 text-slate-600">{{ $p->currency_code }}</td>

                        <td class="px-4 py-3 text-slate-600">{{ $p->service_zones_count }}</td>

                        <td class="px-4 py-3">
                            @if ($p->is_active)
                                <span class="rounded-full bg-emerald-50 px-2 py-1 text-xs text-emerald-700">Actif</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-600">Inactif</span>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <a href="{{ route('admin.order-engine.zones', $p) }}"
                                    class="inline-flex min-h-[36px] items-center rounded-lg bg-slate-900 px-3 text-xs font-medium text-white transition hover:bg-slate-800">
                                    Gérer les zones
                                </a>

                                <button type="button" wire:click="editer({{ $p->id }})"
                                    class="min-h-[36px] rounded-lg border border-slate-300 px-3 text-xs text-slate-700 transition hover:bg-slate-50">
                                    Modifier
                                </button>

                                <button type="button" wire:click="basculerActivation({{ $p->id }})"
                                    class="min-h-[36px] rounded-lg border border-slate-300 px-3 text-xs text-slate-700 transition hover:bg-slate-50">
                                    {{ $p->is_active ? 'Désactiver' : 'Activer' }}
                                </button>

                                {{--
                                    Le bouton reste toujours présent : c'est la RÉPONSE qui explique
                                    ce qui bloque. Le masquer priverait l'administrateur de la seule
                                    façon d'apprendre pourquoi un pays ne se supprime pas.
                                --}}
                                <button type="button" wire:click="supprimer({{ $p->id }})"
                                    class="min-h-[36px] rounded-lg border border-rose-200 px-3 text-xs text-rose-700 transition hover:bg-rose-50">
                                    Supprimer
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">
                            Aucun pays. Ajoutez-en un pour commencer à créer des zones.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $this->pays->links() }}</div>
    @endif
</div>
