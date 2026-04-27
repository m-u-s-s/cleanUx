@props(['title', 'status'])

<div class="rounded-2xl border p-4 bg-white shadow-sm">
    <p class="text-sm text-slate-500">{{ $title }}</p>

    <p class="mt-2 text-xl font-bold
        {{ $status ? 'text-green-600' : 'text-red-600' }}">
        {{ $status ? 'OK' : 'KO' }}
    </p>
</div>