{{--
    Compteur, nombre, surface, curseur.

    Le pas et les bornes viennent de la question, jamais d'une constante écrite ici : c'est
    l'administrateur qui sait qu'une surface se compte de 5 en 5 et un nombre d'étages de 1 en 1.

    Les boutons du compteur font 44 px de haut. En dessous, on rate la cible au pouce — et le
    client incrémente deux fois, ou pas du tout.
--}}
@php
    $rules = $question->validation ?? [];
    $unit = $rules['unit'] ?? null;
    $min = $rules['min'] ?? null;
    $max = $rules['max'] ?? null;
    $step = $rules['step'] ?? 1;
@endphp

@if ($this->layout() === 'counter')
    <div class="flex items-center gap-4">
        <button type="button" wire:click="decrement" aria-label="Diminuer"
            class="flex h-11 w-11 items-center justify-center rounded-full border border-slate-300 text-xl leading-none text-slate-700 transition hover:border-slate-500 focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-40"
            @disabled($min !== null && (float) ($value ?? 0) <= (float) $min)>&minus;</button>

        <output class="min-w-[4ch] text-center text-2xl font-semibold tabular-nums text-slate-900">
            {{ $value ?? ($min ?? 0) }}<span class="ml-1 text-base font-normal text-slate-500">{{ $unit }}</span>
        </output>

        <button type="button" wire:click="increment" aria-label="Augmenter"
            class="flex h-11 w-11 items-center justify-center rounded-full border border-slate-300 text-xl leading-none text-slate-700 transition hover:border-slate-500 focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-40"
            @disabled($max !== null && (float) ($value ?? 0) >= (float) $max)>+</button>
    </div>

@elseif ($this->layout() === 'slider')
    <div class="space-y-3">
        <div class="flex items-baseline justify-between">
            <output class="text-2xl font-semibold tabular-nums text-slate-900">
                {{ $value ?? $min ?? 0 }}<span class="ml-1 text-base font-normal text-slate-500">{{ $unit }}</span>
            </output>
            @if ($min !== null && $max !== null)
                <span class="text-xs tabular-nums text-slate-400">{{ $min }} – {{ $max }} {{ $unit }}</span>
            @endif
        </div>

        {{-- Un `range` natif : glissable au doigt, pilotable aux flèches, annoncé par le lecteur d'écran. --}}
        {{-- Le nom est porte par le controle lui-meme : la legende du groupe situe la question,
             elle ne dit pas ce que ce curseur regle. Sans cela, le lecteur d'ecran annonce
             « curseur » et rien d'autre. --}}
        {{--
            `touch-action: pan-y` — LE DOIGT QUI FAIT DÉFILER NE DOIT PAS DÉPLACER LE CURSEUR.

            Un `range` natif capte le geste dans les DEUX axes. Sur mobile, la page est longue et ce
            curseur se trouve en plein milieu : faire simplement défiler pour lire la suite le
            traînait au passage. Constaté en déroulant la commande à la main — le devis est passé de
            45 € à 140 € sans que rien ne soit touché volontairement, et rien ne le signalait.

            `pan-y` rend le défilement vertical au navigateur et ne laisse au curseur que
            l'horizontal — le seul axe où il veut dire quelque chose. Le glissement au doigt, les
            flèches du clavier et le lecteur d'écran continuent de fonctionner.
        --}}
        <input type="range" wire:model.live.debounce.400ms="value"
            id="{{ $question->code }}"
            aria-label="{{ $question->translate('label') }}{{ $unit ? ' ('.$unit.')' : '' }}"
            min="{{ $min ?? 0 }}" max="{{ $max ?? 100 }}" step="{{ $step }}"
            style="touch-action: pan-y;"
            class="h-11 w-full cursor-pointer accent-slate-900">

        {{-- Saisie directe : le curseur est bon pour approcher, mauvais pour viser. --}}
        <label class="flex items-center gap-2 text-sm text-slate-500">
            <span>Valeur exacte</span>
            <input type="number" wire:model.live.debounce.400ms="value" inputmode="decimal"
                aria-label="{{ $question->translate('label') }} — valeur exacte"
                min="{{ $min }}" max="{{ $max }}" step="{{ $step }}"
                class="w-28 rounded-lg border border-slate-300 px-3 py-2 text-right tabular-nums text-slate-900 focus:border-slate-900 focus:outline-none">
        </label>
    </div>

@else
    <input type="number" wire:model.live.debounce.400ms="value" inputmode="decimal"
        id="{{ $question->code }}"
        placeholder="{{ $question->placeholder }}"
        min="{{ $min }}" max="{{ $max }}" step="{{ $step }}"
        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-[15px] tabular-nums text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">
@endif

@if ($question->type === \App\Support\Domain\QuestionType::SURFACE)
    {{-- Peu de gens connaissent leurs mètres carrés ; tout le monde sait mesurer deux côtés. --}}
    <details class="mt-3 rounded-xl bg-slate-50 p-3 text-sm">
        <summary class="cursor-pointer font-medium text-slate-700">Calculer depuis les dimensions</summary>
        <div class="mt-3 flex flex-wrap items-end gap-2">
            <label class="flex flex-col gap-1">
                <span class="text-xs text-slate-500">Longueur (m)</span>
                <input type="number" wire:model="helperLength" inputmode="decimal" step="0.1"
                    aria-label="Longueur en mètres"
                    class="w-24 rounded-lg border border-slate-300 px-3 py-2 tabular-nums">
            </label>
            <span class="pb-3 text-slate-400" aria-hidden="true">&times;</span>
            <label class="flex flex-col gap-1">
                <span class="text-xs text-slate-500">Largeur (m)</span>
                <input type="number" wire:model="helperWidth" inputmode="decimal" step="0.1"
                    aria-label="Largeur en mètres"
                    class="w-24 rounded-lg border border-slate-300 px-3 py-2 tabular-nums">
            </label>
            <button type="button" wire:click="applySurfaceHelper"
                class="min-h-[44px] rounded-lg bg-slate-900 px-4 text-sm font-medium text-white transition hover:bg-slate-800">
                Appliquer
            </button>
        </div>
    </details>
@endif
