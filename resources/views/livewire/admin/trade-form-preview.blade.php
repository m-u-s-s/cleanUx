<div class="space-y-3">
    @if(!empty($schemaErrors))
        <div class="rounded-md border border-rose-300 bg-rose-50 p-3 text-sm text-rose-700">
            <p class="font-semibold">Schema invalide :</p>
            <ul class="mt-1 list-disc pl-5 text-xs">
                @foreach($schemaErrors as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @elseif(!$this->hasTradeFormSchema())
        <div class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm text-slate-500 italic">
            Aucun schema chargé. Saisissez un JSON valide pour voir l'aperçu.
        </div>
    @else
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-xs text-blue-800">
            <strong>Aperçu interactif</strong> — modifiez les valeurs pour voir le calcul de prix en live.
        </div>

        <x-trade-form-fields
            :schema="$tradeFormSchema"
            wire-model-prefix="tradeFormAnswers"
            :show-pricing="true"
        />

        @php($delta = $this->tradeFormPriceDelta)
        @if(!empty($delta['breakdown']))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 space-y-1">
                <p class="text-xs font-semibold text-emerald-800">Delta prix calculé (base {{ number_format($basePrice, 2, ',', ' ') }} €)</p>
                @foreach($delta['breakdown'] as $line)
                    <div class="flex justify-between text-xs text-emerald-900">
                        <span>{{ $line['label'] }}</span>
                        <span class="font-mono">{{ $line['delta'] > 0 ? '+' : '' }}{{ number_format($line['delta'], 2, ',', ' ') }} €</span>
                    </div>
                @endforeach
                <div class="border-t border-emerald-200 pt-1 mt-1 flex justify-between text-sm font-bold text-emerald-900">
                    <span>Total ajusté</span>
                    <span class="font-mono">{{ number_format($basePrice + $delta['total'], 2, ',', ' ') }} €</span>
                </div>
            </div>
        @endif
    @endif
</div>
