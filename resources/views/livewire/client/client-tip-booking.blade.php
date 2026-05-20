<div class="py-8 max-w-2xl mx-auto px-4">

    @if (!$booking)
        <div class="text-center py-16">
            <p class="text-slate-500">Mission introuvable ou non éligible.</p>
            <a href="{{ route('dashboard.client') }}" class="mt-4 inline-block text-indigo-600 hover:underline">← Retour</a>
        </div>
    @elseif ($existingTip)
        <div class="rounded-2xl border bg-emerald-50 p-8 text-center">
            <p class="text-5xl mb-3">✓</p>
            <h2 class="text-xl font-bold text-slate-900">Merci pour votre pourboire !</h2>
            <p class="text-sm text-slate-600 mt-2">Vous avez déjà tippé {{ $existingTip->amountFormatted() }}.</p>
            <p class="text-xs text-slate-500 mt-1">Statut : {{ $existingTip->status }}</p>
            @if ($existingTip->client_bonus_points > 0)
                <p class="text-xs text-indigo-600 mt-2">+{{ $existingTip->client_bonus_points }} points fidélité crédités</p>
            @endif
            <a href="{{ route('dashboard.client') }}" class="mt-4 inline-block rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-semibold hover:bg-indigo-500">Retour</a>
        </div>
    @else
        <div class="rounded-2xl bg-white border shadow-sm p-6">
            <div class="text-center mb-6">
                <p class="text-3xl mb-2">⭐</p>
                <h1 class="text-2xl font-black text-slate-900">Pourboire au prestataire</h1>
                <p class="text-sm text-slate-500 mt-1">Mission #{{ $booking->id }} — {{ number_format(((float) $booking->devis_estime), 2, ',', ' ') }} €</p>
                @if ($booking->employe)
                    <p class="text-sm font-semibold text-slate-700 mt-2">{{ $booking->employe->name }}</p>
                @endif
            </div>

            <div class="space-y-3">
                <p class="text-xs uppercase font-bold text-slate-500">Choisissez un montant</p>
                <div class="grid grid-cols-3 gap-3">
                    @foreach ($suggestions as $s)
                        <button wire:click="selectPreset({{ $s['amount_cents'] }}, '{{ $s['label'] }}', {{ $s['percent'] }})"
                                class="rounded-xl border-2 {{ $selectedAmountCents === $s['amount_cents'] ? 'border-indigo-600 bg-indigo-50' : 'border-slate-200' }} p-4 text-center hover:border-indigo-400 transition">
                            <p class="text-xs font-semibold text-slate-500">{{ $s['label'] }}</p>
                            <p class="text-lg font-black text-slate-900 mt-1">{{ $s['amount_formatted'] }}</p>
                        </button>
                    @endforeach
                </div>

                <div class="flex items-center gap-2 pt-2">
                    <span class="text-xs text-slate-500">ou</span>
                    <input wire:model.blur="customAmount" wire:change="useCustom" type="number" step="0.50" min="1" max="500" placeholder="Montant personnalisé (€)" class="flex-1 rounded-lg border-slate-300 text-sm">
                </div>

                <div class="pt-3">
                    <label class="text-xs uppercase font-bold text-slate-500">Message (optionnel)</label>
                    <textarea wire:model="message" class="w-full rounded-lg border-slate-300 text-sm mt-1" rows="2" maxlength="280" placeholder="Un mot pour le prestataire ?"></textarea>
                </div>

                @if ($selectedAmountCents)
                    <div class="rounded-lg bg-indigo-50 p-4 mt-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-700">Pourboire</span>
                            <span class="font-bold text-indigo-700">{{ number_format($selectedAmountCents / 100, 2, ',', ' ') }} €</span>
                        </div>
                        @php $bonus = (int) floor($selectedAmountCents / 100); @endphp
                        @if ($bonus > 0)
                            <div class="flex justify-between text-xs mt-1">
                                <span class="text-slate-500">Bonus fidélité</span>
                                <span class="text-emerald-600 font-semibold">+{{ $bonus }} pts</span>
                            </div>
                        @endif
                    </div>
                @endif

                <button wire:click="submit" @disabled(!$selectedAmountCents)
                        class="w-full mt-4 rounded-lg {{ $selectedAmountCents ? 'bg-indigo-600 hover:bg-indigo-500' : 'bg-slate-300 cursor-not-allowed' }} text-white py-3 text-sm font-bold">
                    Envoyer le pourboire
                </button>
                <a href="{{ route('dashboard.client') }}" class="block text-center text-xs text-slate-500 hover:underline mt-2">Plus tard</a>
            </div>
        </div>
    @endif
</div>
