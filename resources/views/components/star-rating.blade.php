@props(['model' => null, 'rating' => 0, 'readonly' => false])

<div
    x-data="{
        rating: @entangle($attributes->wire('model')).defer || {{ $rating }},
        hover: null,
        isReadonly: {{ $readonly ? 'true' : 'false' }}
    }"
    class="flex gap-1"
>
    @for ($i = 1; $i <= 5; $i++)
        <button
            type="button"
            x-on:click="if (!isReadonly) rating = {{ $i }}"
            x-on:mouseover="if (!isReadonly) hover = {{ $i }}"
            x-on:mouseleave="if (!isReadonly) hover = null"
            x-on:keydown.enter.prevent="if (!isReadonly) rating = {{ $i }}"
            class="text-2xl transition-transform transform focus:outline-none"
            :class="(hover ?? rating) >= {{ $i }} ? 'text-accent-amber scale-110' : 'text-gray-300'"
            :disabled="isReadonly"
            :tabindex="isReadonly ? -1 : 0"
            aria-label="Note {{ $i }}"
        >★</button>
    @endfor
</div>
