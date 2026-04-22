@php
    $currentLocale = app()->getLocale();
    $languages = [
        'fr' => 'FR',
        'nl' => 'NL',
        'en' => 'EN',
    ];
@endphp

<div class="flex items-center gap-1 rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
    @foreach($languages as $locale => $label)
        <form method="POST" action="{{ route('locale.switch') }}">
            @csrf
            <input type="hidden" name="locale" value="{{ $locale }}">
            <button type="submit"
                class="rounded-lg px-2.5 py-1 text-xs font-semibold transition {{ $currentLocale === $locale ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">
                {{ $label }}
            </button>
        </form>
    @endforeach
</div>
