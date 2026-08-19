<div>
    @unless($ouvert)
        <button type="button" wire:click="ouvrir"
                class="rounded-lg border border-rose-300 dark:border-rose-400/40 px-4 py-2 text-sm font-semibold text-rose-700 dark:text-rose-300
                       hover:bg-rose-50">
            Annuler la mission
        </button>
    @else
        <div class="rounded-2xl border border-slate-200 bg-white dark:border-white/10 dark:bg-white/5 p-5 shadow-sm space-y-4">
            <div class="flex items-start justify-between gap-4">
                <h3 class="font-black text-slate-900 dark:text-slate-100">Annuler la mission</h3>
                <button type="button" wire:click="fermer" class="text-sm text-slate-500 dark:text-slate-400">Fermer</button>
            </div>

            @if($erreur)
                <div class="rounded-xl border border-rose-200 bg-rose-50 dark:border-rose-500/30 dark:bg-rose-500/10 px-4 py-3 text-sm font-medium text-rose-800 dark:text-rose-200">
                    {{ $erreur }}
                </div>
            @endif

            @forelse($questions as $question)
                <fieldset class="space-y-2">
                    <legend class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $question['label'] }}</legend>
                    @if($question['help_text'])
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $question['help_text'] }}</p>
                    @endif

                    @foreach($question['options'] as $opt)
                        <label class="flex items-start gap-2 rounded-lg border p-3 text-sm
                                      {{ $optionChoisie === $opt['code'] ? 'border-blue-500 bg-blue-50' : 'border-slate-200' }}">
                            <input type="radio" wire:model.live="optionChoisie" value="{{ $opt['code'] }}" class="mt-0.5">
                            <span class="text-slate-800 dark:text-slate-200">{{ $opt['label'] }}</span>
                        </label>
                    @endforeach
                </fieldset>
            @empty
                <p class="text-sm text-slate-500 dark:text-slate-400">Aucune question disponible pour cette intervention.</p>
            @endforelse

            @if($aiguillage)
                {{-- L'AIGUILLAGE : cette réponse ne mène PAS à une annulation, et on le dit AVANT
                     que la personne appuie. Après, elle a déjà annulé. --}}
                <div class="rounded-xl border border-indigo-200 bg-indigo-50 dark:border-indigo-400/30 dark:bg-indigo-400/10 px-4 py-3 text-sm text-indigo-900 dark:text-indigo-100">
                    {{ $aiguillage }}
                </div>
            @elseif($option)
                @if($option->requires_text)
                    <label class="block">
                        <span class="text-xs font-medium text-slate-600 dark:text-slate-300">Précisez</span>
                        <textarea wire:model="precision" rows="2"
                                  class="mt-1 w-full rounded-xl border-slate-300 dark:border-white/15 text-sm"></textarea>
                    </label>
                @endif

                @if($devis)
                    {{-- LE MONTANT MONTRÉ EST CELUI QU'ON PRÉLÈVE : le même `quote()` que
                         l'exécution, avec le même auteur, donc le même plafond d'exemptions. --}}
                    <div class="rounded-xl border border-slate-200 bg-slate-50 dark:border-white/10 dark:bg-white/5 px-4 py-3 text-sm">
                        @if(($devis['fee_amount_cents'] ?? 0) === 0)
                            <p class="font-semibold text-emerald-800 dark:text-emerald-200">Aucun frais d’annulation.</p>
                        @else
                            <p class="font-semibold text-slate-900 dark:text-slate-100">
                                Frais d’annulation :
                                {{ number_format(($devis['fee_amount_cents'] ?? 0) / 100, 2, ',', ' ') }}
                                {{ $devis['currency'] ?? 'EUR' }}
                            </p>
                            <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                                Remboursé :
                                {{ number_format(($devis['refund_amount_cents'] ?? 0) / 100, 2, ',', ' ') }}
                                {{ $devis['currency'] ?? 'EUR' }}
                            </p>
                        @endif

                        @if($devis['exempt_applied'] ?? false)
                            <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-300">Motif exonérant appliqué.</p>
                        @endif
                    </div>
                @endif

                <button type="button" wire:click="confirmer"
                        wire:confirm="Confirmer l’annulation de cette mission ?"
                        class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white">
                    Confirmer l’annulation
                </button>
            @endif
        </div>
    @endunless
</div>
