{{--
    NOS LOCATIONS — LE COMPTOIR DE L'ADMINISTRATEUR.

    Quatre onglets pour les quatre choses qu'une agence tient : le parc, les médias, les agences,
    les locations. Rien d'autre n'existe ailleurs — c'est la demande, et c'est aussi ce qui rend le
    module remplaçable d'un bloc.
--}}
<div class="py-6">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Nos locations</p>
                <h1 class="text-2xl font-black text-slate-900">Parc, tarifs, garanties &amp; réservations</h1>
                <p class="text-sm text-slate-500">Ce module ne touche pas à Fleet : ici les véhicules sont des produits vendus aux clients.</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        @if ($flash)
            <p class="rounded-xl bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ $flash }}</p>
        @endif

        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs font-bold uppercase text-slate-500">Véhicules</p>
                <p class="text-2xl font-black text-slate-900">{{ $kpis['parc'] }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs font-bold uppercase text-slate-500">En vitrine</p>
                <p class="text-2xl font-black text-emerald-600">{{ $kpis['en_vitrine'] }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs font-bold uppercase text-slate-500">Locations en cours</p>
                <p class="text-2xl font-black text-indigo-600">{{ $kpis['locations_en_cours'] }}</p>
            </div>
            <div class="rounded-2xl border bg-white p-4 shadow-sm">
                <p class="text-xs font-bold uppercase text-slate-500">Agences ouvertes</p>
                <p class="text-2xl font-black text-slate-900">{{ $kpis['agences'] }}</p>
            </div>
        </div>

        <div class="flex flex-nowrap gap-2 overflow-x-auto border-b border-slate-200">
            @foreach (['parc' => 'Parc & tarifs', 'medias' => 'Photos & 360°', 'agences' => 'Agences', 'locations' => 'Réservations'] as $cle => $libelle)
                <button wire:click="$set('tab', '{{ $cle }}')"
                    @class([
                        'px-4 py-2 min-h-[44px] inline-flex shrink-0 items-center whitespace-nowrap text-sm font-semibold',
                        'border-b-2 border-indigo-600 text-indigo-700' => $tab === $cle,
                        'text-slate-500 hover:text-slate-900' => $tab !== $cle,
                    ])>{{ $libelle }}</button>
            @endforeach
        </div>

        {{-- ══ PARC & TARIFS ═══════════════════════════════════════════════════ --}}
        @if ($tab === 'parc')
            <div class="grid gap-6 lg:grid-cols-3">

                <form wire:submit="enregistrerLeVehicule" class="space-y-4 rounded-2xl border bg-white p-5 shadow-sm lg:col-span-1">
                    <h2 class="text-sm font-black uppercase text-slate-900">
                        {{ $vehiculeEnEdition ? 'Modifier le véhicule' : 'Ajouter un véhicule' }}
                    </h2>

                    <div class="grid grid-cols-2 gap-3">
                        <label class="block"><span class="text-xs font-semibold text-slate-600">Marque</span>
                            <input type="text" wire:model="fiche.brand" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                            @error('fiche.brand') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="block"><span class="text-xs font-semibold text-slate-600">Modèle</span>
                            <input type="text" wire:model="fiche.model" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                            @error('fiche.model') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="block"><span class="text-xs font-semibold text-slate-600">Plaque</span>
                            <input type="text" wire:model="fiche.plate" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm uppercase">
                        </label>
                        <label class="block"><span class="text-xs font-semibold text-slate-600">Année</span>
                            <input type="number" wire:model="fiche.year" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                        </label>
                        <label class="block"><span class="text-xs font-semibold text-slate-600">Catégorie</span>
                            <select wire:model="fiche.category" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                                @foreach (['citadine', 'compacte', 'berline', 'suv', 'monospace', 'utilitaire', 'premium'] as $cat)
                                    <option value="{{ $cat }}">{{ ucfirst($cat) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block"><span class="text-xs font-semibold text-slate-600">Boîte</span>
                            <select wire:model="fiche.transmission" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                                <option value="manuelle">Manuelle</option>
                                <option value="automatique">Automatique</option>
                            </select>
                        </label>
                        <label class="block"><span class="text-xs font-semibold text-slate-600">Énergie</span>
                            <select wire:model="fiche.fuel" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                                @foreach (['essence', 'diesel', 'hybride', 'electrique', 'gpl'] as $energie)
                                    <option value="{{ $energie }}">{{ ucfirst($energie) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block"><span class="text-xs font-semibold text-slate-600">Places</span>
                            <input type="number" wire:model="fiche.seats" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                        </label>
                        <label class="block"><span class="text-xs font-semibold text-slate-600">Portes</span>
                            <input type="number" wire:model="fiche.doors" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                        </label>
                        <label class="block"><span class="text-xs font-semibold text-slate-600">Bagages</span>
                            <input type="number" wire:model="fiche.luggage" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                        </label>
                    </div>

                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xs font-black uppercase text-slate-700">Prix &amp; garantie</p>
                        <p class="mb-2 text-xs text-slate-500">
                            Sans garantie&nbsp;: caution pleine. Avec&nbsp;: un supplément par jour, et une caution
                            réduite. Ce sont les deux chiffres que verra le client.
                        </p>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block"><span class="text-xs font-semibold text-slate-600">Prix / jour</span>
                                <input type="number" step="0.01" wire:model="fiche.daily_price" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                                @error('fiche.daily_price') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>
                            <label class="block"><span class="text-xs font-semibold text-slate-600">Caution</span>
                                <input type="number" step="0.01" wire:model="fiche.deposit" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                            </label>
                            <label class="block"><span class="text-xs font-semibold text-slate-600">Garantie / jour</span>
                                <input type="number" step="0.01" wire:model="fiche.waiver_daily_price" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                            </label>
                            <label class="block"><span class="text-xs font-semibold text-slate-600">Caution avec garantie</span>
                                <input type="number" step="0.01" wire:model="fiche.waiver_deposit" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                            </label>
                            <label class="block"><span class="text-xs font-semibold text-slate-600">Km inclus / jour</span>
                                <input type="number" wire:model="fiche.included_km_per_day" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                            </label>
                            <label class="block"><span class="text-xs font-semibold text-slate-600">Km supp. (prix)</span>
                                <input type="number" step="0.01" wire:model="fiche.extra_km_price" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                            </label>
                        </div>
                    </div>

                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xs font-black uppercase text-slate-700">Conditions</p>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="block"><span class="text-xs font-semibold text-slate-600">Jours min.</span>
                                <input type="number" wire:model="fiche.min_rental_days" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                            </label>
                            <label class="block"><span class="text-xs font-semibold text-slate-600">Jours max.</span>
                                <input type="number" wire:model="fiche.max_rental_days" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                                @error('fiche.max_rental_days') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>
                            <label class="block"><span class="text-xs font-semibold text-slate-600">Âge min. conducteur</span>
                                <input type="number" wire:model="fiche.min_driver_age" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                            </label>
                            <label class="block"><span class="text-xs font-semibold text-slate-600">Permis (années)</span>
                                <input type="number" wire:model="fiche.min_license_years" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                            </label>
                        </div>
                    </div>

                    <label class="block"><span class="text-xs font-semibold text-slate-600">Agence de retrait</span>
                        <select wire:model="fiche.pickup_point_id" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                            <option value="">— À définir —</option>
                            @foreach ($agences as $point)
                                <option value="{{ $point->id }}">{{ $point->name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block"><span class="text-xs font-semibold text-slate-600">Description</span>
                        <textarea wire:model="fiche.description" rows="3" class="mt-1 w-full rounded-xl border-slate-300 text-sm"></textarea>
                    </label>

                    <div class="flex gap-2">
                        <button type="submit" class="min-h-[44px] flex-1 rounded-xl bg-indigo-600 px-4 text-sm font-bold text-white hover:bg-indigo-700">
                            {{ $vehiculeEnEdition ? 'Enregistrer' : 'Créer (fermé)' }}
                        </button>
                        @if ($vehiculeEnEdition)
                            <button type="button" wire:click="reinitialiserLaFiche" class="min-h-[44px] rounded-xl border px-4 text-sm font-semibold text-slate-700">Annuler</button>
                        @endif
                    </div>
                </form>

                <div class="lg:col-span-2">
                    <input type="text" wire:model.live.debounce.400ms="recherche" placeholder="Rechercher une marque, un modèle, une plaque…"
                        class="mb-3 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">

                    <div class="overflow-x-auto rounded-2xl border bg-white shadow-sm">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-4 py-2 text-left">Véhicule</th>
                                    <th class="px-4 py-2 text-left">Prix / jour</th>
                                    <th class="px-4 py-2 text-left">Caution</th>
                                    <th class="px-4 py-2 text-left">Agence</th>
                                    <th class="px-4 py-2 text-left">Vitrine</th>
                                    <th class="px-4 py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @forelse ($vehicules as $vehicule)
                                    <tr>
                                        <td class="px-4 py-2">
                                            <span class="font-semibold text-slate-900">{{ $vehicule->nomComplet() }}</span>
                                            <span class="block text-xs text-slate-500">{{ $vehicule->plate }} · {{ $vehicule->category }}</span>
                                        </td>
                                        <td class="px-4 py-2 font-mono text-xs">{{ number_format($vehicule->daily_price_cents / 100, 2, ',', ' ') }} {{ $vehicule->currency }}</td>
                                        <td class="px-4 py-2 font-mono text-xs">{{ number_format($vehicule->deposit_cents / 100, 0, ',', ' ') }}</td>
                                        <td class="px-4 py-2 text-xs">{{ $vehicule->pickupPoint?->name ?? '—' }}</td>
                                        <td class="px-4 py-2">
                                            <button type="button" wire:click="basculerLActivation({{ $vehicule->id }})"
                                                @class([
                                                    'rounded-full px-3 py-1 text-xs font-bold',
                                                    'bg-emerald-100 text-emerald-800' => $vehicule->is_active,
                                                    'bg-slate-200 text-slate-700' => ! $vehicule->is_active,
                                                ])>{{ $vehicule->is_active ? 'En vitrine' : 'Fermé' }}</button>
                                        </td>
                                        <td class="px-4 py-2 text-right text-xs">
                                            <button type="button" wire:click="editerLeVehicule({{ $vehicule->id }})" class="text-indigo-600 hover:underline">Modifier</button>
                                            <button type="button" wire:click="supprimerLeVehicule({{ $vehicule->id }})" class="ml-2 text-rose-600 hover:underline">Retirer</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Aucun véhicule. Ajoutez-en un pour ouvrir la location.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        <div class="p-3">{{ $vehicules->links() }}</div>
                    </div>
                </div>
            </div>
        @endif

        {{-- ══ PHOTOS & 360° ═══════════════════════════════════════════════════ --}}
        @if ($tab === 'medias')
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="rounded-2xl border bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-black uppercase text-slate-900">Choisir un véhicule</h2>
                    <ul class="mt-3 max-h-96 space-y-1 overflow-y-auto">
                        @foreach ($vehicules as $vehicule)
                            <li>
                                <button type="button" wire:click="choisirLeVehiculePourMedias({{ $vehicule->id }})"
                                    @class([
                                        'w-full rounded-xl px-3 py-2 text-left text-sm',
                                        'bg-indigo-50 font-bold text-indigo-800' => $vehiculePourMedias === $vehicule->id,
                                        'hover:bg-slate-50' => $vehiculePourMedias !== $vehicule->id,
                                    ])>{{ $vehicule->nomComplet() }}</button>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="space-y-6 lg:col-span-2">
                    @if (! $vehiculeMedias)
                        <p class="rounded-2xl border border-dashed p-10 text-center text-slate-400">Sélectionnez un véhicule.</p>
                    @else
                        <div class="rounded-2xl border bg-white p-5 shadow-sm">
                            <h3 class="text-sm font-black uppercase text-slate-900">Galerie</h3>
                            <p class="mb-3 text-xs text-slate-500">La première image sert de vignette au catalogue.</p>

                            <div class="mb-3 grid grid-cols-4 gap-2">
                                @foreach ($vehiculeMedias->galerie as $photo)
                                    <div class="relative">
                                        <img src="{{ $photo->url() }}" alt="" class="aspect-square w-full rounded-lg object-cover">
                                        <button type="button" wire:click="supprimerUnMedia({{ $photo->id }})"
                                            class="absolute right-1 top-1 rounded-full bg-black/70 px-2 text-xs text-white">×</button>
                                    </div>
                                @endforeach
                            </div>

                            <input type="file" wire:model="photos" multiple accept="image/*" class="text-sm">
                            @error('photos.*') <span class="block text-xs text-rose-600">{{ $message }}</span> @enderror
                            <button type="button" wire:click="ajouterDesPhotos" class="mt-2 min-h-[44px] rounded-xl bg-slate-900 px-4 text-sm font-bold text-white">Ajouter</button>
                        </div>

                        <div class="rounded-2xl border bg-white p-5 shadow-sm">
                            <h3 class="text-sm font-black uppercase text-slate-900">Rotation 360° (photos)</h3>
                            <p class="mb-3 text-xs text-slate-500">
                                24 à 36 photos prises tout autour du véhicule. <strong>L’ordre des fichiers est le
                                sens de rotation</strong> — la séquence est remplacée en entier à chaque envoi, une
                                rotation n’ayant de sens que complète.
                            </p>
                            <p class="mb-2 text-xs font-semibold text-slate-700">{{ $vehiculeMedias->rotation360->count() }} image(s) en place</p>

                            <input type="file" wire:model="rotation" multiple accept="image/*" class="text-sm">
                            @error('rotation.*') <span class="block text-xs text-rose-600">{{ $message }}</span> @enderror
                            <button type="button" wire:click="remplacerLaRotation" class="mt-2 min-h-[44px] rounded-xl bg-slate-900 px-4 text-sm font-bold text-white">Remplacer la rotation</button>
                        </div>

                        <div class="rounded-2xl border bg-white p-5 shadow-sm">
                            <h3 class="text-sm font-black uppercase text-slate-900">Modèle 3D (glTF)</h3>
                            <p class="mb-3 text-xs text-slate-500">
                                Un fichier <code>.glb</code> ou <code>.gltf</code> par véhicule. S’il existe, il
                                remplace la rotation photo sur la fiche client.
                            </p>
                            <p class="mb-2 text-xs font-semibold text-slate-700">
                                {{ $vehiculeMedias->modele3d->isNotEmpty() ? 'Un modèle est en place.' : 'Aucun modèle.' }}
                            </p>

                            <input type="file" wire:model="modele3d" accept=".glb,.gltf" class="text-sm">
                            @error('modele3d') <span class="block text-xs text-rose-600">{{ $message }}</span> @enderror
                            <button type="button" wire:click="remplacerLeModele3d" class="mt-2 min-h-[44px] rounded-xl bg-slate-900 px-4 text-sm font-bold text-white">Envoyer le modèle</button>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- ══ AGENCES ═════════════════════════════════════════════════════════ --}}
        @if ($tab === 'agences')
            <div class="grid gap-6 lg:grid-cols-3">
                <form wire:submit="enregistrerLAgence" class="space-y-3 rounded-2xl border bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-black uppercase text-slate-900">{{ $agenceEnEdition ? 'Modifier l’agence' : 'Nouvelle agence' }}</h2>

                    <label class="block"><span class="text-xs font-semibold text-slate-600">Nom</span>
                        <input type="text" wire:model="agence.name" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                        @error('agence.name') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>
                    <label class="block"><span class="text-xs font-semibold text-slate-600">Adresse</span>
                        <input type="text" wire:model="agence.address" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                        @error('agence.address') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="block"><span class="text-xs font-semibold text-slate-600">Code postal</span>
                            <input type="text" wire:model="agence.postal_code" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                        </label>
                        <label class="block"><span class="text-xs font-semibold text-slate-600">Ville</span>
                            <input type="text" wire:model="agence.city" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                        </label>
                        <label class="block"><span class="text-xs font-semibold text-slate-600">Pays (ISO)</span>
                            <input type="text" maxlength="2" wire:model="agence.country_code" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm uppercase">
                        </label>
                        <label class="block"><span class="text-xs font-semibold text-slate-600">Téléphone</span>
                            <input type="text" wire:model="agence.phone" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                        </label>
                        <label class="block"><span class="text-xs font-semibold text-slate-600">Latitude</span>
                            <input type="number" step="0.0000001" wire:model="agence.lat" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                        </label>
                        <label class="block"><span class="text-xs font-semibold text-slate-600">Longitude</span>
                            <input type="number" step="0.0000001" wire:model="agence.lng" class="mt-1 w-full min-h-[44px] rounded-xl border-slate-300 text-sm">
                        </label>
                    </div>
                    <label class="block"><span class="text-xs font-semibold text-slate-600">Consignes d’accès</span>
                        <textarea wire:model="agence.instructions" rows="3" class="mt-1 w-full rounded-xl border-slate-300 text-sm"></textarea>
                    </label>

                    <div class="flex gap-2">
                        <button type="submit" class="min-h-[44px] flex-1 rounded-xl bg-indigo-600 px-4 text-sm font-bold text-white">Enregistrer</button>
                        @if ($agenceEnEdition)
                            <button type="button" wire:click="reinitialiserLAgence" class="min-h-[44px] rounded-xl border px-4 text-sm font-semibold">Annuler</button>
                        @endif
                    </div>
                </form>

                <div class="overflow-x-auto rounded-2xl border bg-white shadow-sm lg:col-span-2">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-2 text-left">Agence</th>
                                <th class="px-4 py-2 text-left">Adresse</th>
                                <th class="px-4 py-2 text-left">Véhicules</th>
                                <th class="px-4 py-2 text-left">État</th>
                                <th class="px-4 py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse ($agences as $point)
                                <tr>
                                    <td class="px-4 py-2 font-semibold">{{ $point->name }}</td>
                                    <td class="px-4 py-2 text-xs text-slate-600">{{ $point->adresseComplete() }}</td>
                                    <td class="px-4 py-2 text-xs">{{ $point->vehicles()->count() }}</td>
                                    <td class="px-4 py-2">
                                        <button type="button" wire:click="basculerLAgence({{ $point->id }})"
                                            @class([
                                                'rounded-full px-3 py-1 text-xs font-bold',
                                                'bg-emerald-100 text-emerald-800' => $point->is_active,
                                                'bg-slate-200 text-slate-700' => ! $point->is_active,
                                            ])>{{ $point->is_active ? 'Ouverte' : 'Fermée' }}</button>
                                    </td>
                                    <td class="px-4 py-2 text-right text-xs">
                                        <button type="button" wire:click="editerLAgence({{ $point->id }})" class="text-indigo-600 hover:underline">Modifier</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">Aucune agence.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- ══ RÉSERVATIONS ════════════════════════════════════════════════════ --}}
        @if ($tab === 'locations')
            <div class="space-y-3">
                <select wire:model.live="filtreStatut" class="min-h-[44px] rounded-xl border-slate-300 text-sm">
                    <option value="">Tous les statuts</option>
                    @foreach (['draft' => 'Brouillon', 'confirmed' => 'Confirmée', 'picked_up' => 'Retirée', 'returned' => 'Rendue', 'cancelled' => 'Annulée'] as $cle => $libelle)
                        <option value="{{ $cle }}">{{ $libelle }}</option>
                    @endforeach
                </select>

                <div class="overflow-x-auto rounded-2xl border bg-white shadow-sm">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-4 py-2 text-left">Référence</th>
                                <th class="px-4 py-2 text-left">Véhicule</th>
                                <th class="px-4 py-2 text-left">Conducteur</th>
                                <th class="px-4 py-2 text-left">Période</th>
                                <th class="px-4 py-2 text-left">Total</th>
                                <th class="px-4 py-2 text-left">Statut</th>
                                <th class="px-4 py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse ($locations as $reservation)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $reservation->reference }}</td>
                                    <td class="px-4 py-2 text-xs">{{ $reservation->vehicle?->nomComplet() }}</td>
                                    <td class="px-4 py-2 text-xs">{{ $reservation->nomDuConducteur() }}</td>
                                    <td class="px-4 py-2 text-xs">
                                        {{ $reservation->starts_at?->format('d/m H:i') }} → {{ $reservation->ends_at?->format('d/m H:i') }}
                                        <span class="block text-slate-400">{{ $reservation->days }} j · {{ $reservation->estAvecGarantie() ? 'avec garantie' : 'sans garantie' }}</span>
                                    </td>
                                    <td class="px-4 py-2 font-mono text-xs">{{ number_format($reservation->total_cents / 100, 2, ',', ' ') }} {{ $reservation->currency }}</td>
                                    <td class="px-4 py-2 text-xs">{{ $reservation->status }}</td>
                                    <td class="px-4 py-2 text-right text-xs">
                                        @if ($reservation->status === 'confirmed')
                                            <button type="button" wire:click="marquerRetiree({{ $reservation->id }})" class="text-indigo-600 hover:underline">Remis au client</button>
                                        @elseif ($reservation->status === 'picked_up')
                                            <button type="button" wire:click="marquerRendue({{ $reservation->id }})" class="text-emerald-600 hover:underline">Rendu</button>
                                        @endif
                                        @if (in_array($reservation->status, ['draft', 'confirmed'], true))
                                            <button type="button" wire:click="annulerLaLocation({{ $reservation->id }})" class="ml-2 text-rose-600 hover:underline">Annuler</button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">Aucune réservation.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="p-3">{{ $locations->links() }}</div>
                </div>
            </div>
        @endif
    </div>
</div>
