<div class="py-12 max-w-4xl mx-auto px-4">
    <div class="text-center mb-8">
        <p class="text-3xl mb-2">💡</p>
        <h1 class="text-3xl font-black text-slate-900">Centre d'aide</h1>
        <p class="text-sm text-slate-500 mt-2">Trouvez rapidement les réponses à vos questions.</p>
    </div>

    <div class="mb-6">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Rechercher une question..."
               class="w-full rounded-xl border-slate-300 text-sm py-3 px-4 shadow-sm">
    </div>

    <div class="flex flex-wrap gap-2 mb-6">
        <button wire:click="$set('category', '')" class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $category === '' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700' }}">
            Toutes
        </button>
        @foreach ($allCategories as $key => $cat)
            <button wire:click="$set('category', '{{ $key }}')" class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $category === $key ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700' }}">
                {{ $cat['label'] }}
            </button>
        @endforeach
    </div>

    @if (empty($faqs))
        <div class="text-center py-16 text-slate-400">
            <p class="text-5xl mb-2">🔍</p>
            <p>Aucun résultat. Essayez d'autres mots-clés ou contactez le support.</p>
        </div>
    @else
        <div class="space-y-6">
            @foreach ($faqs as $key => $cat)
                <div>
                    <h2 class="text-xs uppercase font-bold text-indigo-600 mb-3">{{ $cat['label'] }}</h2>
                    <div class="space-y-2">
                        @foreach ($cat['items'] as $i => $item)
                            <details class="rounded-xl bg-white border shadow-sm group">
                                <summary class="cursor-pointer px-5 py-4 font-semibold text-slate-900 list-none flex justify-between items-center hover:bg-slate-50">
                                    <span>{{ $item['q'] }}</span>
                                    <span class="transition-transform group-open:rotate-180">▼</span>
                                </summary>
                                <div class="px-5 pb-4 text-sm text-slate-600">
                                    {{ $item['a'] }}
                                </div>
                            </details>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-12 rounded-2xl bg-indigo-50 p-6 text-center">
        <h2 class="font-bold text-slate-900">Vous ne trouvez pas votre réponse ?</h2>
        <p class="text-sm text-slate-600 mt-1">Notre équipe vous répond sous 24h.</p>
        <a href="mailto:support@cleanux.com" class="inline-block mt-3 rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold hover:bg-indigo-500">
            Contacter le support
        </a>
    </div>
</div>
