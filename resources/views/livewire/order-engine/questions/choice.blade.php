{{--
    Choix simple, multiple, ou oui/non.

    Trois dispositions, choisies par l'administrateur selon le nombre d'options : des cartes quand
    la réponse mérite une description, des pastilles quand elle tient en deux mots, une liste
    déroulante au-delà d'une dizaine — où cartes et pastilles deviennent un mur à faire défiler.

    Les contrôles sont de vrais `input` masqués visuellement : le clavier, le lecteur d'écran et la
    navigation par tabulation fonctionnent sans qu'on ait à les réimplémenter — et une
    réimplémentation est précisément ce qui casse l'accessibilité sans que personne ne le voie.
--}}
@php
    $multiple = $question->type === \App\Support\Domain\QuestionType::MULTI_CHOICE;
    $selected = is_array($value) ? $value : array_filter([$value], fn ($v) => $v !== null && $v !== '');
    $options = $question->options->where('is_active', true);
    $columns = (int) ($question->display['columns'] ?? 1);
@endphp

@if ($this->layout() === 'dropdown')
    <select
        wire:model.live="value"
        id="{{ $question->code }}"
        class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-[15px] text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10"
    >
        <option value="">Choisissez…</option>
        @foreach ($options as $option)
            <option value="{{ $option->value }}">{{ $option->translate('label') }}</option>
        @endforeach
    </select>

@elseif ($this->layout() === 'chips')
    <div class="flex flex-wrap gap-2" role="{{ $multiple ? 'group' : 'radiogroup' }}">
        @foreach ($options as $option)
            @php $isOn = in_array((string) $option->value, array_map('strval', $selected), true); @endphp
            <label @class([
                'cursor-pointer select-none rounded-full border px-4 py-2.5 text-sm font-medium transition',
                'min-h-[44px] inline-flex items-center',
                'border-slate-900 bg-slate-900 text-white' => $isOn,
                'border-slate-300 bg-white text-slate-700 hover:border-slate-400' => ! $isOn,
            ])>
                <input
                    type="{{ $multiple ? 'checkbox' : 'radio' }}"
                    name="{{ $question->code }}"
                    value="{{ $option->value }}"
                    @checked($isOn)
                    @if ($multiple) wire:click="toggleOption('{{ $option->value }}')" @else wire:model.live="value" @endif
                    class="sr-only peer"
                >
                <span class="peer-focus-visible:underline peer-focus-visible:underline-offset-4">{{ $option->translate('label') }}</span>
            </label>
        @endforeach
    </div>

@else
    {{-- Cartes : la disposition par défaut, celle qui laisse de la place à une description. --}}
    <div class="grid gap-2 {{ $columns >= 2 ? 'grid-cols-2' : 'grid-cols-1' }}" role="{{ $multiple ? 'group' : 'radiogroup' }}">
        @foreach ($options as $option)
            @php $isOn = in_array((string) $option->value, array_map('strval', $selected), true); @endphp
            <label @class([
                'relative flex min-h-[56px] cursor-pointer items-start gap-3 rounded-2xl border p-4 transition',
                'border-slate-900 bg-slate-50 ring-1 ring-slate-900' => $isOn,
                'border-slate-200 bg-white hover:border-slate-300' => ! $isOn,
            ])>
                <input
                    type="{{ $multiple ? 'checkbox' : 'radio' }}"
                    name="{{ $question->code }}"
                    value="{{ $option->value }}"
                    @checked($isOn)
                    @if ($multiple) wire:click="toggleOption('{{ $option->value }}')" @else wire:model.live="value" @endif
                    class="mt-0.5 h-5 w-5 shrink-0 border-slate-300 text-slate-900 focus:ring-slate-900"
                >
                <span class="min-w-0">
                    <span class="block text-[15px] font-medium leading-snug text-slate-900">{{ $option->translate('label') }}</span>
                    @if ($option->description)
                        <span class="mt-0.5 block text-sm leading-snug text-slate-500">{{ $option->description }}</span>
                    @endif
                </span>
            </label>
        @endforeach
    </div>
@endif
