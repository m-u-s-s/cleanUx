<div class="py-8 max-w-2xl mx-auto px-4">
    @if (! $booking)
        <div class="text-center py-16">
            <p class="text-slate-500">Mission introuvable.</p>
            <a href="{{ route('dashboard.employe') }}" class="mt-4 inline-block text-indigo-600 hover:underline">← Retour</a>
        </div>
    @elseif ($existing)
        <div class="rounded-2xl border bg-emerald-50 p-8 text-center">
            <p class="text-5xl mb-3">✓</p>
            <h2 class="text-xl font-bold text-slate-900">Évaluation enregistrée</h2>
            <p class="text-sm text-slate-600 mt-2">Vous avez attribué {{ $existing->rating }}/5 à ce client.</p>
            @if ($existing->comment)
                <p class="text-xs text-slate-500 italic mt-3">« {{ $existing->comment }} »</p>
            @endif
            <a href="{{ route('dashboard.employe') }}" class="mt-4 inline-block rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold hover:bg-indigo-500">Retour</a>
        </div>
    @else
        <div class="rounded-2xl bg-white border shadow-sm p-6">
            <div class="text-center mb-6">
                <p class="text-3xl mb-2">⭐</p>
                <h1 class="text-2xl font-black text-slate-900">Évaluer le client</h1>
                <p class="text-sm text-slate-500 mt-1">Mission #{{ $booking->id }}</p>
                @if ($booking->client)
                    <p class="text-sm font-semibold text-slate-700 mt-2">{{ $booking->client->name }}</p>
                @endif
            </div>

            <div class="space-y-5">
                <div>
                    <p class="text-xs uppercase font-bold text-slate-500 mb-2">Note globale</p>
                    <div class="flex justify-center gap-2">
                        @for ($i = 1; $i <= 5; $i++)
                            <button wire:click="$set('rating', {{ $i }})" type="button"
                                    class="text-4xl {{ $rating >= $i ? 'text-amber-400' : 'text-slate-200' }} hover:scale-110 transition">
                                ★
                            </button>
                        @endfor
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3 pt-2">
                    @foreach ([
                        ['punctuality', 'Ponctualité', $punctuality],
                        ['quality', 'Coopération', $quality],
                        ['communication', 'Communication', $communication],
                    ] as $crit)
                        <div>
                            <p class="text-xs font-semibold text-slate-500 mb-1">{{ $crit[1] }}</p>
                            <select wire:model="{{ $crit[0] }}" class="w-full rounded-lg border-slate-300 text-sm">
                                @for ($i = 1; $i <= 5; $i++)
                                    <option value="{{ $i }}">{{ $i }} ★</option>
                                @endfor
                            </select>
                        </div>
                    @endforeach
                </div>

                <div>
                    <label class="text-xs uppercase font-bold text-slate-500">Commentaire (optionnel)</label>
                    <textarea wire:model="comment" class="w-full rounded-lg border-slate-300 text-sm mt-1" rows="3" maxlength="1000"
                              placeholder="Un mot sur ce client ?"></textarea>
                </div>

                <button wire:click="submit"
                        class="w-full rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white py-3 text-sm font-bold">
                    Envoyer mon évaluation
                </button>
                <a href="{{ route('dashboard.employe') }}" class="block text-center text-xs text-slate-500 hover:underline">Plus tard</a>
            </div>
        </div>
    @endif
</div>
