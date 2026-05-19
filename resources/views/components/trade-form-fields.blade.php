@props([
    'schema' => null,           // schema normalisé (cf. App\Support\TradeFormSchema::validate)
    'wireModelPrefix' => 'tradeFormAnswers',
    'readonly' => false,         // pour l'aperçu admin
    'showPricing' => true,
])

@php
    $fields = $schema['fields'] ?? [];
    $disabledAttr = $readonly ? 'disabled' : '';
@endphp

@if(empty($fields))
    <div class="rounded-md border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500 italic">
        Aucun champ configuré pour ce métier.
    </div>
@else
    <div class="space-y-4">
        @foreach($fields as $field)
            @php
                $key = $field['key'];
                $modelPath = $wireModelPrefix . '.' . $key;
                $required = (bool) ($field['required'] ?? false);
                $hasPricing = !empty($field['pricing']) || !empty(collect($field['options'] ?? [])->where('price_delta', '!=', 0)->count());
            @endphp

            <div class="rounded-lg border border-slate-200 bg-white p-4">
                <label class="block text-sm font-semibold text-slate-900">
                    {{ $field['label'] }}
                    @if($required)<span class="text-rose-600">*</span>@endif
                    @if(!empty($field['unit']))
                        <span class="ml-1 text-xs font-normal text-slate-500">({{ $field['unit'] }})</span>
                    @endif
                </label>

                @if(!empty($field['help']))
                    <p class="mt-1 text-xs text-slate-500">{{ $field['help'] }}</p>
                @endif

                <div class="mt-2">
                    @switch($field['type'])

                        @case('number')
                            <input
                                type="number"
                                wire:model.live.debounce.300ms="{{ $modelPath }}"
                                @if(isset($field['min'])) min="{{ $field['min'] }}" @endif
                                @if(isset($field['max'])) max="{{ $field['max'] }}" @endif
                                @if(isset($field['step'])) step="{{ $field['step'] }}" @endif
                                {{ $disabledAttr }}
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            />
                            @break

                        @case('boolean')
                            <label class="inline-flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    wire:model.live="{{ $modelPath }}"
                                    {{ $disabledAttr }}
                                    class="rounded text-blue-600"
                                />
                                <span class="text-sm text-slate-700">Activer</span>
                            </label>
                            @break

                        @case('select')
                            <select
                                wire:model.live="{{ $modelPath }}"
                                {{ $disabledAttr }}
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            >
                                <option value="">— Choisir —</option>
                                @foreach($field['options'] ?? [] as $opt)
                                    <option value="{{ $opt['value'] }}">
                                        {{ $opt['label'] }}
                                        @if($showPricing && ($opt['price_delta'] ?? 0) != 0)
                                            ({{ $opt['price_delta'] > 0 ? '+' : '' }}{{ number_format($opt['price_delta'], 2, ',', ' ') }} €)
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @break

                        @case('multiselect')
                            <div class="space-y-1">
                                @foreach($field['options'] ?? [] as $opt)
                                    <label class="flex items-center gap-2 text-sm text-slate-700">
                                        <input
                                            type="checkbox"
                                            value="{{ $opt['value'] }}"
                                            wire:model.live="{{ $modelPath }}"
                                            {{ $disabledAttr }}
                                            class="rounded text-blue-600"
                                        />
                                        <span>
                                            {{ $opt['label'] }}
                                            @if($showPricing && ($opt['price_delta'] ?? 0) != 0)
                                                <span class="text-xs text-slate-500">
                                                    ({{ $opt['price_delta'] > 0 ? '+' : '' }}{{ number_format($opt['price_delta'], 2, ',', ' ') }} €)
                                                </span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @break

                        @case('text')
                            <input
                                type="text"
                                wire:model.live.debounce.300ms="{{ $modelPath }}"
                                @if(isset($field['max_length'])) maxlength="{{ $field['max_length'] }}" @endif
                                {{ $disabledAttr }}
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            />
                            @break

                        @case('textarea')
                            <textarea
                                wire:model.live.debounce.500ms="{{ $modelPath }}"
                                rows="3"
                                @if(isset($field['max_length'])) maxlength="{{ $field['max_length'] }}" @endif
                                {{ $disabledAttr }}
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            ></textarea>
                            @break

                    @endswitch

                    @error($modelPath)
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                @if($showPricing && $hasPricing && !empty($field['pricing']))
                    @php
                        $p = $field['pricing'];
                        $hint = match($p['modifier']) {
                            'per_unit' => '+ '.number_format($p['value'], 2, ',', ' ').' € / '.($field['unit'] ?: 'unité'),
                            'fixed'    => '+ '.number_format($p['value'], 2, ',', ' ').' € si coché',
                            'percent'  => '+ '.number_format($p['value'], 0).'% du prix de base',
                            default    => '',
                        };
                    @endphp
                    <p class="mt-2 text-xs text-blue-600">💶 {{ $hint }}</p>
                @endif
            </div>
        @endforeach
    </div>
@endif
