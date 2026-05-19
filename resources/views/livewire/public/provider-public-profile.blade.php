<div class="py-10 bg-slate-50 min-h-screen">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        {{-- Header --}}
        <div class="rounded-3xl bg-white border shadow-sm p-8">
            <div class="flex flex-col md:flex-row items-start md:items-center gap-6">
                @if($profile->photo_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($profile->photo_path) }}"
                         alt="{{ $provider->name }}"
                         class="h-24 w-24 rounded-full object-cover border-2 border-slate-200" />
                @else
                    <div class="h-24 w-24 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-700 text-3xl font-bold">
                        {{ strtoupper(substr($provider->name, 0, 1)) }}
                    </div>
                @endif

                <div class="flex-1">
                    <h1 class="text-3xl font-black text-slate-900">{{ $provider->name }}</h1>

                    <div class="flex items-center gap-4 mt-2">
                        @if($profile->rating_avg !== null && $profile->rating_count > 0)
                            <div class="flex items-center gap-2">
                                <div class="text-3xl text-amber-400">
                                    {{ str_repeat('★', (int) round((float) $profile->rating_avg)) }}{{ str_repeat('☆', max(0, 5 - (int) round((float) $profile->rating_avg))) }}
                                </div>
                                <span class="text-lg font-bold text-slate-900">{{ number_format((float) $profile->rating_avg, 1, ',', ' ') }}</span>
                                <span class="text-sm text-slate-500">({{ $profile->rating_count }} avis)</span>
                            </div>
                        @else
                            <span class="text-sm text-slate-400">Aucun avis pour l'instant</span>
                        @endif
                    </div>

                    @if($profile->bio)
                        <p class="text-sm text-slate-600 mt-3 max-w-2xl">{{ $profile->bio }}</p>
                    @endif

                    <div class="flex flex-wrap gap-2 mt-3">
                        @foreach($provider->trades as $trade)
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                {{ $trade->name }}
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>

            @if($profile->rating_count > 0 && ! empty($profile->rating_distribution))
                <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6 pt-6 border-t">
                    {{-- Distribution --}}
                    <div>
                        <p class="text-sm font-bold text-slate-700 mb-3">Répartition des notes</p>
                        @foreach([5, 4, 3, 2, 1] as $star)
                            @php
                                $cnt = (int) ($profile->rating_distribution[(string) $star] ?? 0);
                                $pct = $profile->rating_count > 0 ? round(($cnt / $profile->rating_count) * 100) : 0;
                            @endphp
                            <div class="flex items-center gap-3 mb-1.5">
                                <span class="text-xs font-semibold text-slate-600 w-8">{{ $star }} ★</span>
                                <div class="flex-1 bg-slate-100 rounded-full h-2 overflow-hidden">
                                    <div class="bg-amber-400 h-2" style="width: {{ $pct }}%"></div>
                                </div>
                                <span class="text-xs text-slate-500 w-10 text-right">{{ $cnt }}</span>
                            </div>
                        @endforeach
                    </div>

                    {{-- Dimensions --}}
                    @if(! empty($profile->rating_dimensions))
                        <div>
                            <p class="text-sm font-bold text-slate-700 mb-3">Détails</p>
                            @foreach($profile->rating_dimensions as $key => $info)
                                @php
                                    $labels = [
                                        'punctuality' => 'Ponctualité',
                                        'quality' => 'Qualité',
                                        'communication' => 'Communication',
                                        'value' => 'Qualité/prix',
                                    ];
                                @endphp
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-sm text-slate-600">{{ $labels[$key] ?? $key }}</span>
                                    <span class="text-sm font-bold text-amber-600">
                                        {{ number_format((float) $info['avg'], 1, ',', ' ') }} / 5
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Filtres --}}
        <div class="flex flex-wrap items-center justify-between gap-3 px-2">
            <h2 class="text-xl font-bold text-slate-900">
                Avis ({{ $ratings->total() }})
            </h2>
            <div class="flex flex-wrap gap-2">
                <select wire:change="setSort($event.target.value)" class="rounded-xl border-slate-300 text-sm">
                    <option value="recent" @selected($sort === 'recent')>Plus récents</option>
                    <option value="highest" @selected($sort === 'highest')>Meilleures notes</option>
                    <option value="lowest" @selected($sort === 'lowest')>Notes les plus basses</option>
                </select>
                <div class="inline-flex rounded-xl border bg-white">
                    <button wire:click="setFilter(null)"
                            class="px-3 py-1 text-xs font-semibold {{ $filterMinRating === null ? 'bg-indigo-600 text-white' : 'text-slate-600' }}">
                        Tous
                    </button>
                    @foreach([5, 4, 3] as $min)
                        <button wire:click="setFilter({{ $min }})"
                                class="px-3 py-1 text-xs font-semibold {{ $filterMinRating === $min ? 'bg-indigo-600 text-white' : 'text-slate-600' }}">
                            {{ $min }}★+
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Liste avis --}}
        <div class="space-y-4">
            @forelse($ratings as $r)
                <div class="rounded-2xl bg-white border p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-3">
                                <span class="text-amber-400 text-lg">
                                    {{ str_repeat('★', (int) ($r->rating ?? $r->note)) }}{{ str_repeat('☆', max(0, 5 - (int) ($r->rating ?? $r->note))) }}
                                </span>
                                <span class="text-sm font-semibold text-slate-700">{{ $r->client?->name ?? 'Client' }}</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">
                                {{ optional($r->published_at)->format('d/m/Y') }}
                            </p>
                        </div>
                    </div>

                    @if($r->effectiveComment())
                        <p class="text-sm text-slate-700 mt-3">{{ $r->effectiveComment() }}</p>
                    @endif

                    @if($r->provider_response)
                        <div class="mt-4 ml-6 rounded-2xl bg-slate-50 border-l-4 border-indigo-400 p-4">
                            <p class="text-xs font-bold text-indigo-700 uppercase">
                                Réponse de {{ $provider->name }}
                            </p>
                            <p class="text-sm text-slate-700 mt-1">{{ $r->provider_response }}</p>
                            <p class="text-xs text-slate-400 mt-1">
                                {{ optional($r->provider_responded_at)->format('d/m/Y') }}
                            </p>
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-2xl bg-white border border-dashed p-10 text-center text-slate-400">
                    Aucun avis ne correspond à votre filtre.
                </div>
            @endforelse
        </div>

        <div>{{ $ratings->links() }}</div>
    </div>
</div>
