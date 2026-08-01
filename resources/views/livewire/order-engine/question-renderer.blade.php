{{--
    Une question du parcours de commande.

    Le même balisage sert le client et l'aperçu du constructeur : c'est ce qui garantit que
    l'administrateur voit exactement ce que verra le client, et non une imitation qui divergera.

    Accessibilité : chaque groupe est un `fieldset` avec sa `legend`, l'aide et l'erreur sont
    rattachées au champ par `aria-describedby`, et l'état fautif est annoncé par `aria-invalid`.
    Les contrôles sont natifs — un client au lecteur d'écran ou au clavier seul doit pouvoir
    commander, pas seulement consulter.
--}}
<fieldset
    class="cx-question space-y-3 py-5 border-b border-slate-200 last:border-0"
    data-question="{{ $question->code }}"
    @if ($error) aria-invalid="true" @endif
    aria-describedby="{{ $question->code }}-help {{ $error ? $question->code.'-error' : '' }}"
>
    <legend class="w-full">
        <span class="block text-[17px] font-semibold leading-snug text-slate-900">
            {{ $question->translate('label') }}
            @if ($question->is_required)
                <span class="text-slate-400 font-normal" aria-hidden="true">*</span>
                <span class="sr-only">(obligatoire)</span>
            @endif
        </span>

        @if ($question->translate('help_text'))
            <span id="{{ $question->code }}-help" class="mt-1 block text-sm leading-relaxed text-slate-500">
                {{ $question->translate('help_text') }}
            </span>
        @endif
    </legend>

    <div @class(['opacity-60' => $unknown])>
        @include('livewire.order-engine.questions.'.$this->partial())
    </div>

    {{--
        La porte de sortie (loi 6). Discrète mais toujours atteignable : une question sans
        échappatoire est un mur, et un client bloqué ne revient pas. Elle n'annule pas la
        commande — elle élargit la fourchette annoncée.
    --}}
    @if ($question->allows_unknown)
        <div class="pt-1">
            @if ($unknown)
                <p class="inline-flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    <span aria-hidden="true">≈</span>
                    À évaluer sur place — l’estimation reste une fourchette.
                    <button type="button" wire:click="$set('unknown', false)"
                        class="ml-1 font-medium underline underline-offset-2 hover:text-amber-950 focus-visible:outline-2 focus-visible:outline-offset-2">
                        Répondre finalement
                    </button>
                </p>
            @else
                <button type="button" wire:click="markUnknown"
                    class="text-sm font-medium text-slate-500 underline underline-offset-4 transition hover:text-slate-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500">
                    Je ne sais pas — à évaluer sur place
                </button>
            @endif
        </div>
    @endif

    {{-- L'erreur n'apparaît qu'une fois le champ quitté, et dit quoi faire. --}}
    @if ($error && $touched)
        <p id="{{ $question->code }}-error" role="alert" class="text-sm font-medium text-rose-700">
            {{ $error }}
        </p>
    @endif
</fieldset>
