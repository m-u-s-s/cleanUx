<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Marketplace</p>
                <h1 class="text-3xl font-black text-slate-900">Trouver un prestataire</h1>
                <p class="text-sm text-slate-500 mt-1">
                    Filtrez par métier, rating, prix, zone — réservez en quelques clics.
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

            {{-- Sidebar filtres --}}
            <aside class="lg:col-span-1 space-y-4">

                <div class="rounded-2xl border bg-white p-4 shadow-sm space-y-3">
                    <h2 class="text-sm font-bold uppercase text-slate-500">Filtres</h2>

                    <div>
                        <label class="text-xs font-semibold text-slate-700">Recherche</label>
                        <input type="text" wire:model.live.debounce.400ms="query"
                               class="mt-1 w-full rounded-xl border-gray-300 text-sm"
                               placeholder="Nom, bio..." />
                    </div>

                    <div>
                        <label class="text-xs font-semibold text-slate-700">Métier</label>
                        <select wire:model.live="tradeId" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                            <option value="">Tous métiers</option>
                            @foreach($trades as $trade)
                                <option value="{{ $trade->id }}">{{ $trade->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-semibold text-slate-700">Code postal / ville</label>
                        <input type="text" wire:model.live.debounce.400ms="postalSearch"
                               class="mt-1 w-full rounded-xl border-gray-300 text-sm"
                               placeholder="Ex: 1000 ou Bruxelles" />
                        @if(! empty($postalSuggestions))
                            <div class="mt-1 rounded-xl border bg-white shadow-sm max-h-48 overflow-auto">
                                @foreach($postalSuggestions as $s)
                                    <button type="button"
                                            wire:click="pickPostal('{{ $s['code'] }}', @js($s['city_name']))"
                                            class="w-full text-left px-3 py-2 text-sm hover:bg-slate-50">
                                        <span class="font-mono font-bold">{{ $s['code'] }}</span>
                                        <span class="text-slate-600">{{ $s['city_name'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                        @if($postalCode)
                            <button wire:click="clearPostal"
                                    class="text-xs text-red-600 hover:underline mt-1">
                                ✕ Effacer
                            </button>
                        @endif
                    </div>

                    <div>
                        <label class="text-xs font-semibold text-slate-700">Note minimale</label>
                        <div class="flex gap-1 mt-1">
                            @foreach([null, 3, 4, 4.5] as $val)
                                <button type="button"
                                        wire:click="$set('minRating', @js($val))"
                                        class="rounded-lg px-2 py-1 text-xs font-semibold border {{ $minRating == $val ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-700' }}">
                                    {{ $val === null ? 'Tous' : $val . '★+' }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs font-semibold text-slate-700">Prix min €</label>
                            <input type="number" wire:model.live.debounce.400ms="minPrice" min="0" step="5"
                                   class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-slate-700">Prix max €</label>
                            <input type="number" wire:model.live.debounce.400ms="maxPrice" min="0" step="5"
                                   class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model.live="onlineOnly" class="rounded" />
                        En ligne maintenant
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model.live="hasPhotoOnly" class="rounded" />
                        Avec photo
                    </label>

                    <button wire:click="resetFilters"
                            class="w-full rounded-xl border px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        Réinitialiser
                    </button>
                </div>

            </aside>

            {{-- Résultats --}}
            <div class="lg:col-span-3 space-y-4">

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-slate-600">
                        <span class="font-bold">{{ $results->total() }}</span> prestataire(s) trouvés
                    </p>
                    <select wire:model.live="sort" class="rounded-xl border-gray-300 text-sm">
                        <option value="rating">Meilleur rating</option>
                        <option value="popularity">Plus populaires</option>
                        <option value="price_asc">Prix croissant</option>
                        <option value="price_desc">Prix décroissant</option>
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    @forelse($results as $u)
                        @php
                            $profile = $u->providerProfile;
                            $rating = $u->profile_rating_avg ?? $profile?->rating_avg;
                            $ratingCount = $u->profile_rating_count ?? $profile?->rating_count ?? 0;
                            $hourlyRate = $u->profile_hourly_rate ?? $profile?->hourly_rate;
                            $isOnline = (bool) ($u->profile_is_online ?? $profile?->is_online);
                            $bio = $u->profile_bio ?? $profile?->bio;
                            $photoPath = $u->profile_photo_path ?? $profile?->photo_path;
                        @endphp
                        <a href="{{ url('/providers/'.$u->id) }}"
                           class="rounded-2xl border bg-white p-4 shadow-sm hover:shadow-md transition-shadow">
                            <div class="flex items-start gap-3">
                                @if($photoPath)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($photoPath) }}"
                                         alt="{{ $u->name }}"
                                         class="h-16 w-16 rounded-full object-cover border" />
                                @else
                                    <div class="h-16 w-16 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 text-xl font-bold">
                                        {{ strtoupper(substr($u->name, 0, 1)) }}
                                    </div>
                                @endif
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <p class="font-bold text-slate-900 truncate">{{ $u->name }}</p>
                                        @if($isOnline)
                                            <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500" title="En ligne"></span>
                                        @endif
                                    </div>
                                    @if($rating !== null)
                                        <p class="text-sm mt-1">
                                            <span class="text-amber-400">★</span>
                                            <span class="font-bold">{{ number_format((float) $rating, 1, ',', ' ') }}</span>
                                            <span class="text-xs text-slate-500">({{ $ratingCount }} avis)</span>
                                        </p>
                                    @else
                                        <p class="text-xs text-slate-400 mt-1">Aucun avis</p>
                                    @endif
                                </div>
                                @if($hourlyRate !== null)
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-700">
                                        {{ number_format((float) $hourlyRate, 0, ',', ' ') }} €/h
                                    </span>
                                @endif
                            </div>

                            @if($bio)
                                <p class="text-xs text-slate-600 mt-3 line-clamp-2">{{ $bio }}</p>
                            @endif

                            @if($u->trades && $u->trades->count() > 0)
                                <div class="flex flex-wrap gap-1 mt-3">
                                    @foreach($u->trades->take(3) as $trade)
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">
                                            {{ $trade->name }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </a>
                    @empty
                        <div class="md:col-span-2 xl:col-span-3 rounded-2xl border-2 border-dashed border-slate-200 p-10 text-center text-slate-400">
                            Aucun prestataire ne correspond aux filtres.
                        </div>
                    @endforelse
                </div>

                <div>{{ $results->links() }}</div>
            </div>
        </div>
    </div>
</div>
