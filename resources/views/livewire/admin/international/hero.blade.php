    <x-page-shell eyebrow="International" title="International exploitable" subtitle="Active les marchés, configure les règles locales et pilote la readiness pays par pays." />

    @if (session('success'))
        <div role="alert" class="brio-alerte brio-alerte-success">
            {{ session('success') }}
        </div>
    @endif
