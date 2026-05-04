    <x-page-shell eyebrow="Territoire" title="Gestion des zones" subtitle="Pilotage Belgique par zones, règles de service et affectations opérationnelles." />

    @if (session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif
