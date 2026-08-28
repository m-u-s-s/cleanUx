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
                        <label class="text-xs font-semibold text-slate-700" for="query">Recherche</label>
                        <input id="query" type="text" wire:model.live.debounce.400ms="query"
                               class="mt-1 w-full rounded-xl border-gray-300 text-sm"
                               placeholder="Nom, bio..." />
                    </div>

                    <div>
                        <label class="text-xs font-semibold text-slate-700" for="tradeId">Métier</label>
                        <select id="tradeId" wire:model.live="tradeId" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                            <option value="">Tous métiers</option>
                            @foreach($trades as $trade)
                                <option value="{{ $trade->id }}">{{ $trade->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-semibold text-slate-700" for="postalSearch">Code postal / ville</label>
                        <input id="postalSearch" type="text" wire:model.live.debounce.400ms="postalSearch"
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
                        <span id="groupe-note-minimale" class="text-xs font-semibold text-slate-700">Note minimale</span>
                        <div class="flex gap-1 mt-1" role="group" aria-labelledby="groupe-note-minimale">
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
                            <label class="text-xs font-semibold text-slate-700" for="minPrice">Prix min €</label>
                            <input id="minPrice" type="number" wire:model.live.debounce.400ms="minPrice" min="0" step="5"
                                   class="mt-1 w-full rounded-xl border-gray-300 text-sm" />
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-slate-700" for="maxPrice">Prix max €</label>
                            <input id="maxPrice" type="number" wire:model.live.debounce.400ms="maxPrice" min="0" step="5"
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
                        <div class="rounded-2xl border bg-white p-4 shadow-sm hover:shadow-md transition-shadow flex flex-col">
                            @if($selectionMode)
                                <div class="flex items-start gap-3 cursor-pointer"
                                     wire:click="selectProvider({{ $u->id }})">
                            @else
                                {{--
                                    `route()` PLUTÔT QU'UNE URL ÉCRITE EN DUR (2026-08-05).

                                    Ce lien existait déjà, sous la forme `url('/providers/'.$u->id)`.
                                    Il fonctionne — mais il fige le chemin : le jour où la route
                                    change d'URI, il mène à un 404 sans que rien ne le signale, et
                                    aucun outil ne peut rattacher ce lien à sa route. Un audit
                                    d'accessibilité l'a d'ailleurs compté comme page orpheline.
                                --}}
                                <a href="{{ route('providers.show', $u) }}" class="flex items-start gap-3">
                            @endif
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
                                        <x-money :amount="(float) ((float) $hourlyRate)" :decimals="0" />/h
                                    </span>
                                @endif

                                @if($selectionMode)
                                </div>
                            @else
                                </a>
                            @endif

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

                            @if($selectionMode)
                                <button type="button"
                                        wire:click="selectProvider({{ $u->id }})"
                                        class="mt-4 w-full rounded-xl bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                    Choisir ce prestataire
                                </button>
                            @elseif($this->estPremium)
                                {{-- CHOISIR SON PRESTATAIRE EST UN SERVICE PREMIUM. La reservation
                                     part du parcours habituel : ce nom n'est qu'une preference. --}}
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <button type="button"
                                            wire:click="reserverAvec({{ $u->id }})"
                                            class="brio-btn-primary flex-1 justify-center">
                                        {{ __('Réserver avec') }} {{ \Illuminate\Support\Str::before($u->name, ' ') }}
                                    </button>

                                    <button type="button"
                                            wire:click="basculerPrefere({{ $u->id }})"
                                            class="brio-btn-secondary"
                                            aria-pressed="{{ in_array($u->id, $this->preferes, true) ? 'true' : 'false' }}">
                                        {{ in_array($u->id, $this->preferes, true) ? __('★ Dans mes préférés') : __('☆ Ajouter à mes préférés') }}
                                    </button>
                                </div>
                            @else
                                {{-- L'ANNUAIRE RESTE VISIBLE : on n'enleve rien, on propose. --}}
                                <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                                    {{ __('Choisir votre prestataire est réservé au Premium.') }}
                                    @if(Route::has('premium.offer'))
                                        <a href="{{ route('premium.offer') }}" class="font-semibold underline">{{ __('Découvrir') }}</a>
                                    @endif
                                </p>
                            @endif
                        </div>
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
