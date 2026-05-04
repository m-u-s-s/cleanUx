    <x-page-shell eyebrow="International" title="International exploitable" subtitle="Active les marchés, configure les règles locales et pilote la readiness pays par pays." />

    @if (session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif
