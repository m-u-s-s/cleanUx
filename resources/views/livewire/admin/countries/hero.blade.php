    <x-page-shell eyebrow="International" title="Pilotage des pays" subtitle="Activation des marchés, paramètres locaux et supervision de la couverture géographique par pays." />

    @if (session('success'))
        <div role="alert" class="brio-alerte brio-alerte-success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div role="alert" class="brio-alerte brio-alerte-danger">
            {{ session('error') }}
        </div>
    @endif
