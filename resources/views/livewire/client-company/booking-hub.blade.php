<div class="min-h-screen bg-slate-50 p-6">

    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900">📅 Réservations</h1>
            <p class="text-sm text-slate-500">Gérez toutes vos demandes de nettoyage</p>
        </div>
        <button wire:click="$set('showForm', true)"
            class="flex items-center gap-2 rounded-xl bg-purple-600 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-700">
            ⚡ Nouvelle demande
        </button>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            ✅ {{ session('success') }}
        </div>
    @endif

    {{-- Filtres --}}
    <div class="mb-4 flex flex-wrap gap-3">
        <select wire:model.live="filterSite"
            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="">Tous les locaux</option>
            @foreach ($sites as $site)
                <option value="{{ $site->id }}">{{ $site->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="filterStatus"
            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="">Tous statuts</option>
            <option value="pending">En attente</option>
            <option value="pending_approval">Approbation requise</option>
            <option value="confirmed">Confirmée</option>
            <option value="in_progress">En cours</option>
            <option value="completed">Complétée</option>
            <option value="cancelled">Annulée</option>
        </select>
    </div>

    {{-- Liste réservations --}}
    @if ($bookings->isEmpty())
        <div class="flex flex-col items-center rounded-2xl border-2 border-dashed border-slate-200 bg-white py-16 text-center">
            <p class="text-5xl mb-4">📋</p>
            <p class="text-lg font-bold text-slate-700">Aucune réservation</p>
            <p class="mt-1 text-sm text-slate-400">Créez votre première demande de nettoyage</p>
            <button wire:click="$set('showForm', true)"
                class="mt-4 rounded-xl bg-purple-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-purple-700">
                ⚡ Nouvelle demande
            </button>
        </div>
    @else
        <div class="space-y-2">
            @foreach ($bookings as $booking)
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="font-semibold text-slate-900">{{ $booking->reference }}</p>
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- ══ Modal nouvelle demande ══ --}}
@if ($showForm)
    <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-sm p-4">
        <div class="mx-auto my-8 w-full max-w-2xl rounded-2xl bg-white shadow-2xl">

            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <h3 class="text-lg font-black text-slate-900">⚡ Nouvelle demande de nettoyage</h3>
                <button wire:click="$set('showForm', false)" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100">✕</button>
            </div>

            <div class="p-6 space-y-5">

                {{-- Sélection du local --}}
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">🏠 Local concerné *</label>
                    @if ($sites->isEmpty())
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">
                            ⚠️ Aucun local enregistré.
                            <a href="{{ route('client-company.sites') }}" class="font-semibold underline">Ajouter un local →</a>
                        </div>
                    @else
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 max-h-48 overflow-y-auto">
                            @foreach ($sites as $site)
                                <label class="cursor-pointer rounded-xl border p-3 transition
                                    {{ $siteId == $site->id
                                        ? 'border-purple-500 bg-purple-50 ring-2 ring-purple-100'
                                        : 'border-slate-200 hover:border-slate-300' }}">
                                    <input type="radio" wire:model.live="siteId" value="{{ $site->id }}" class="sr-only">
                                    <p class="text-sm font-semibold text-slate-900">{{ $site->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $site->city }}
                                        @if ($site->surface_m2) · {{ $site->surface_m2 }} m² @endif
                                    </p>
                                </label>
                            @endforeach
                        </div>
                    @endif
                    @error('siteId') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                {{-- Pré-remplissage affiché --}}
                @if ($selectedSite)
                    <div class="rounded-xl bg-purple-50 border border-purple-100 px-4 py-3 text-sm text-purple-700">
                        📍 {{ $selectedSite->fullAddress() }}
                        @if ($selectedSite->preferredProvider)
                            · ⭐ {{ $selectedSite->preferredProvider->name }} assigné automatiquement
                        @endif
                    </div>
                @endif

                {{-- Service et planification --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-bold text-slate-700 mb-1">Type de service *</label>
                        <select wire:model="serviceType"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100">
                            <option value="">Choisir un service…</option>
                            <option value="cleaning_standard">🧹 Nettoyage standard</option>
                            <option value="cleaning_deep">🧼 Nettoyage en profondeur</option>
                            <option value="cleaning_windows">🪟 Vitres</option>
                            <option value="cleaning_carpet">🟫 Moquettes / Sols</option>
                            <option value="cleaning_post_construction">🏗️ Après travaux</option>
                            <option value="cleaning_office">💼 Bureaux</option>
                        </select>
                        @error('serviceType') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Date *</label>
                        <input wire:model="scheduledDate" type="date"
                            min="{{ now()->format('Y-m-d') }}"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100">
                        @error('scheduledDate') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Heure *</label>
                        <input wire:model="scheduledTime" type="time"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Durée estimée</label>
                        <select wire:model="durationMin"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-purple-500">
                            <option value="60">1 heure</option>
                            <option value="90">1h30</option>
                            <option value="120">2 heures</option>
                            <option value="180">3 heures</option>
                            <option value="240">4 heures</option>
                            <option value="480">Journée complète</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Priorité</label>
                        <select wire:model="priority"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-purple-500">
                            <option value="basse">⚪ Basse</option>
                            <option value="normale">🔵 Normale</option>
                            <option value="haute">🟠 Haute</option>
                            <option value="urgente">🔴 Urgente</option>
                        </select>
                    </div>
                </div>

                {{-- Champs B2B --}}
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 rounded-xl bg-slate-50 p-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">N° bon de commande</label>
                        <input wire:model="purchaseOrderRef" type="text" placeholder="PO-2026-001"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-purple-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-1">Centre de coût</label>
                        <input wire:model="costCenter" type="text" placeholder="MKTG-BRXL"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm outline-none focus:border-purple-500">
                    </div>
                </div>

                {{-- Instructions accès --}}
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-1">🔑 Instructions d'accès</label>
                    <textarea wire:model="siteInstructions" rows="2"
                        placeholder="Code d'entrée, parking, contact sur place…"
                        class="w-full resize-none rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-100"></textarea>
                </div>

                {{-- Prestataire préféré --}}
                @if ($availableProviders->isNotEmpty() && ! $selectedSite?->preferred_provider_id)
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Prestataire préféré (optionnel)</label>
                        <select wire:model="preferredProvider"
                            class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-purple-500">
                            <option value="">Attribution automatique</option>
                            @foreach ($availableProviders as $provider)
                                <option value="{{ $provider->user_id }}">{{ $provider->user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            <div class="flex gap-3 border-t border-slate-100 p-4">
                <button wire:click="$set('showForm', false)"
                    class="flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50">
                    Annuler
                </button>
                <button wire:click="createBooking"
                    class="flex-1 rounded-xl bg-purple-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-purple-700">
                    Envoyer la demande
                </button>
            </div>
        </div>
    </div>
@endif
