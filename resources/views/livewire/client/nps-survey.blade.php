<div class="py-12 max-w-2xl mx-auto px-4">
    @if ($submitted)
        <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-8 text-center">
            <p class="text-5xl mb-3">✓</p>
            <h1 class="text-2xl font-black text-slate-900">Merci pour votre avis !</h1>
            <p class="text-sm text-slate-600 mt-2">Votre retour aide CleanUx à s'améliorer.</p>
            <a href="{{ route('dashboard.client') }}" class="mt-4 inline-block rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold hover:bg-indigo-500">
                Retour au tableau de bord
            </a>
        </div>
    @else
        <div class="rounded-2xl bg-white border shadow-sm p-8">
            <div class="text-center mb-8">
                <p class="text-4xl mb-3">⭐</p>
                <h1 class="text-2xl font-black text-slate-900">Votre avis compte</h1>
                <p class="text-sm text-slate-500 mt-2">
                    Sur une échelle de 0 à 10, à quel point recommanderiez-vous CleanUx à un proche ?
                </p>
            </div>

            <div class="flex justify-center gap-1 mb-2 flex-wrap">
                @for ($i = 0; $i <= 10; $i++)
                    @php
                        $bg = $score === $i
                            ? ($i <= 6 ? 'bg-rose-500 text-white' : ($i <= 8 ? 'bg-amber-500 text-white' : 'bg-emerald-500 text-white'))
                            : 'bg-slate-100 text-slate-700 hover:bg-slate-200';
                    @endphp
                    <button wire:click="selectScore({{ $i }})"
                            class="w-10 h-10 rounded-lg font-bold text-sm transition {{ $bg }}">
                        {{ $i }}
                    </button>
                @endfor
            </div>

            <div class="flex justify-between text-xs text-slate-500 mb-6 px-1">
                <span>Pas du tout probable</span>
                <span>Très probable</span>
            </div>

            @if ($score !== null)
                <div class="rounded-lg bg-slate-50 p-4 mb-6 text-center">
                    @if ($score <= 6)
                        <p class="text-sm text-rose-700 font-semibold">🙁 Détracteur — désolé pour cette expérience.</p>
                    @elseif ($score <= 8)
                        <p class="text-sm text-amber-700 font-semibold">😐 Passif — qu'aimeriez-vous voir améliorer ?</p>
                    @else
                        <p class="text-sm text-emerald-700 font-semibold">😄 Promoteur — merci, votre satisfaction nous tient à cœur !</p>
                    @endif
                </div>

                <div class="mb-6">
                    <label class="text-xs font-bold text-slate-600">Commentaire (optionnel)</label>
                    <textarea wire:model="comment" maxlength="2000" rows="4"
                              class="w-full rounded-lg border-slate-300 text-sm mt-1"
                              placeholder="Dites-nous ce qui vous a plu ou ce qu'on peut améliorer..."></textarea>
                </div>

                <button wire:click="submit"
                        class="w-full rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white py-3 text-sm font-bold">
                    Envoyer mon avis
                </button>
            @endif
        </div>
    @endif
</div>
