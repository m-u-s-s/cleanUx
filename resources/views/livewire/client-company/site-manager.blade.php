<div class="min-h-screen bg-slate-50 p-6">

    {{-- ── Header ── --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900">🏠 Mes locaux</h1>
            <p class="text-sm text-slate-500">Gérez tous vos sites et enregistrez vos locaux pour des réservations en 2 clics</p>
        </div>
        <div class="flex items-center gap-3">
            <div class="relative">
                <input wire:model.live.debounce.300ms="searchQuery"
                    type="text"
                    placeholder="Rechercher un local…"
                    class="w-56 rounded-xl border border-slate-200 bg-white pl-9 pr-3 py-2 text-sm shadow-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                <span class="absolute left-3 top-2.5 text-slate-400">🔍</span>
            </div>
            <button wire:click="openCreate"
                class="flex items-center gap-2 rounded-xl bg-purple-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-purple-700">
                + Ajouter un local
            </button>
        </div>
    </div>

    {{-- ── Stats rapides ── --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @php
            $total     = $sites->count();
            $withActive = $sites->where('active_bookings_count', '>', 0)->count();
        @endphp
        <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
            <p class="text-2xl font-black text-slate-900">{{ $total }}</p>
            <p class="text-xs text-slate-500 mt-0.5">Locaux enregistrés</p>
        </div>
        <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
            <p class="text-2xl font-black text-purple-600">{{ $withActive }}</p>
            <p class="text-xs text-slate-500 mt-0.5">Avec mission active</p>
        </div>
        <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
            <p class="text-2xl font-black text-blue-600">
                {{ $sites->whereNotNull('preferred_provider_id')->count() }}
            </p>
            <p class="text-xs text-slate-500 mt-0.5">Avec prestataire favori</p>
        </div>
        <div class="rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
            <p class="text-2xl font-black text-green-600">
                {{ $sites->sum('surface_m2') }}
            </p>
            <p class="text-xs text-slate-500 mt-0.5">m² total</p>
        </div>
    </div>

    {{-- ── Grille des sites ── --}}
    @if ($sites->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-white py-16 text-center">
            <p class="text-5xl mb-4">🏢</p>
            <p class="text-lg font-bold text-slate-700">Aucun local enregistré</p>
            <p class="mt-1 text-sm text-slate-400 max-w-xs">Ajoutez vos bureaux, entrepôts ou commerces pour réserver un nettoyage en 2 clics.</p>
            <button wire:click="openCreate" class="mt-4 rounded-xl bg-purple-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-purple-700">
                + Ajouter mon premier local
            </button>
        </div>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($sites as $site)
                <div class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md">

                    {{-- Top --}}
                    <div class="mb-3 flex items-start justify-between">
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate font-bold text-slate-900">{{ $site->name }}</h3>
                            <p class="mt-0.5 text-xs text-slate-500">
                                📍 {{ $site->address }}, {{ $site->postal_code }} {{ $site->city }}
                            </p>
                        </div>
                        @if ($site->active_bookings_count > 0)
                            <span class="ml-2 flex-shrink-0 rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-bold text-green-700">
                                {{ $site->active_bookings_count }} mission(s)
                            </span>
                        @endif
                    </div>

                    {{-- Infos --}}
                    <div class="grid grid-cols-2 gap-2 text-xs text-slate-600">
                        @if ($site->surface_m2)
                            <div class="flex items-center gap-1">
                                <span class="text-slate-400">📐</span>
                                {{ number_format($site->surface_m2) }} m²
                            </div>
                        @endif
                        @if ($site->floor_count)
                            <div class="flex items-center gap-1">
                                <span class="text-slate-400">🏢</span>
                                {{ $site->floor_count }} étage(s)
                            </div>
                        @endif
                        <div class="flex items-center gap-1">
                            <span class="text-slate-400">🔄</span>
                            {{ $site->frequencyLabel() }}
                        </div>
                        @if ($site->contact_name)
                            <div class="flex items-center gap-1">
                                <span class="text-slate-400">👤</span>
                                {{ $site->contact_name }}
                            </div>
                        @endif
                    </div>

                    {{-- Prestataire favori --}}
                    @if ($site->preferredProvider)
                        <div class="mt-3 flex items-center gap-2 rounded-xl bg-blue-50 px-3 py-2">
                            <img src="{{ $site->preferredProvider->profile_photo_url }}"
                                 class="h-6 w-6 rounded-full object-cover">
                            <span class="text-xs text-blue-700">
                                ⭐ {{ $site->preferredProvider->name }}
                            </span>
                        </div>
                    @endif

                    {{-- Actions --}}
                    <div class="mt-4 flex items-center gap-2 border-t border-slate-100 pt-3">
                        <a href="{{ route('client-company.bookings.create', ['site' => $site->id]) }}"
                           class="flex-1 rounded-xl bg-purple-600 py-1.5 text-center text-xs font-semibold text-white hover:bg-purple-700">
                            ⚡ Réserver
                        </a>
                        <button wire:click="openEdit({{ $site->id }})"
                            class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
                            ✏️
                        </button>
                        <button wire:click="deleteSite({{ $site->id }})"
                            wire:confirm="Archiver ce local ?"
                            class="rounded-xl border border-red-100 px-3 py-1.5 text-xs font-medium text-red-500 hover:bg-red-50">
                            🗑️
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- ══════════════════════════════════════════
     MODAL — Formulaire local
══════════════════════════════════════════ --}}
@if ($showForm)
    <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm p-4">
        <div class="mx-auto my-8 w-full max-w-2xl rounded-2xl bg-white shadow-2xl">

            {{-- Header --}}
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h3 class="text-lg font-black text-slate-900">
                    {{ $editingId ? '✏️ Modifier le local' : '+ Nouveau local' }}
                </h3>
                <button wire:click="$set('showForm', false)"
                    class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                    ✕
                </button>
            </div>

            <div class="p-6 space-y-5">

                {{-- Infos principales --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-bold text-slate-700 mb-1">Nom du local *</label>
                        <input wire:model="name" type="text" placeholder="Siège social, Entrepôt Nord…"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100">
                        @error('name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-sm font-bold text-slate-700 mb-1">Adresse *</label>
                        <input wire:model="address" type="text" placeholder="Rue de la Loi 16"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100">
                        @error('address') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Code postal *</label>
                        <input wire:model="postalCode" type="text" placeholder="1000"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Ville *</label>
                        <input wire:model="city" type="text" placeholder="Bruxelles"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Surface (m²)</label>
                        <input wire:model="surfaceM2" type="number" placeholder="250"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Nombre d'étages</label>
                        <input wire:model="floorCount" type="number" placeholder="2"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100">
                    </div>
                </div>

                {{-- Fréquence et créneau --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Fréquence de nettoyage</label>
                        <select wire:model="cleaningFrequency"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-purple-500">
                            <option value="one_time">Ponctuel</option>
                            <option value="weekly">Hebdomadaire</option>
                            <option value="biweekly">Bi-mensuel</option>
                            <option value="monthly">Mensuel</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Créneau préféré</label>
                        <input wire:model="preferredTimeSlot" type="text" placeholder="Lun-Ven 8h-10h"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100">
                    </div>
                </div>

                {{-- Contact sur site --}}
                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 space-y-3">
                    <p class="text-sm font-bold text-slate-700">👤 Contact sur site</p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <input wire:model="contactName" type="text" placeholder="Nom"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-purple-500 bg-white">
                        <input wire:model="contactPhone" type="text" placeholder="Téléphone"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-purple-500 bg-white">
                        <input wire:model="contactEmail" type="email" placeholder="Email"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-purple-500 bg-white">
                    </div>
                </div>

                {{-- Prestataire favori --}}
                @if ($providers->isNotEmpty())
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">⭐ Prestataire favori</label>
                        <div class="grid grid-cols-2 gap-2 max-h-36 overflow-y-auto">
                            <label class="flex items-center gap-2 rounded-xl border border-slate-200 p-2 cursor-pointer hover:bg-slate-50
                                {{ is_null($preferredProviderId) ? 'border-slate-400 bg-slate-50' : '' }}">
                                <input type="radio" wire:model="preferredProviderId" value="" class="rounded-full">
                                <span class="text-xs text-slate-500">Pas de préférence</span>
                            </label>
                            @foreach ($providers as $provider)
                                <label class="flex items-center gap-2 rounded-xl border border-slate-200 p-2 cursor-pointer hover:bg-blue-50
                                    {{ $preferredProviderId == $provider->user_id ? 'border-blue-500 bg-blue-50' : '' }}">
                                    <input type="radio" wire:model="preferredProviderId"
                                        value="{{ $provider->user_id }}" class="rounded-full">
                                    <img src="{{ $provider->user->profile_photo_url }}"
                                         class="h-6 w-6 rounded-full object-cover">
                                    <span class="text-xs text-slate-700 truncate">{{ $provider->user->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Instructions d'accès --}}
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">🔑 Instructions d'accès</label>
                    <textarea wire:model="accessInstructions" rows="2"
                        placeholder="Code d'entrée, parking disponible, sonnette…"
                        class="w-full resize-none rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100"></textarea>
                </div>

                {{-- Notes --}}
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">📝 Notes internes</label>
                    <textarea wire:model="notes" rows="2"
                        placeholder="Informations visibles uniquement par votre équipe"
                        class="w-full resize-none rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100"></textarea>
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex gap-3 border-t border-slate-100 p-4">
                <button wire:click="$set('showForm', false)"
                    class="flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
                    Annuler
                </button>
                <button wire:click="saveSite"
                    class="flex-1 rounded-xl bg-purple-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-purple-700">
                    {{ $editingId ? 'Enregistrer les modifications' : 'Créer le local' }}
                </button>
            </div>
        </div>
    </div>
@endif
