@if (session('success'))
    <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">
        {{ session('success') }}
    </div>
@endif
