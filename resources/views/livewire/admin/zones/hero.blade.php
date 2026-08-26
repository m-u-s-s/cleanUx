    <x-page-shell eyebrow="Territoire" title="Gestion des zones" subtitle="Pilotage Belgique par zones, règles de service et affectations opérationnelles." />

    @if (session('success'))
        <div role="alert" class="brio-alerte brio-alerte-success">
            {{ session('success') }}
        </div>
    @endif
