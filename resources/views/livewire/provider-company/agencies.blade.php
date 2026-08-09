<div class="mx-auto max-w-4xl">

    <header class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Nos implantations</h1>
        <p class="mt-1 text-sm text-slate-500">
            Vos dépôts et antennes — d'où partent vos équipes. À ne pas confondre avec les
            <a href="{{ route('provider-company.sites') }}" class="font-semibold text-blue-600 hover:underline">sites
                de vos clients</a>, qui sont les lieux où vous intervenez.
        </p>
    </header>

    {{-- Création --}}
    <div class="mb-8 rounded-2xl border border-slate-200 bg-white p-5">
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Nom de l'implantation</span>
                <input type="text" wire:model="nom" placeholder="Dépôt Bruxelles" data-test="agency-name"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                @error('nom')
                <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Ville</span>
                <input type="text" wire:model="ville" placeholder="Bruxelles"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Adresse</span>
                <input type="text" wire:model="adresse" placeholder="Rue de la Loi 12"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Code postal</span>
                <input type="text" wire:model="codePostal" placeholder="1000"
                    class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
            </label>

            <label class="block sm:col-span-2">
                <span class="mb-1 block text-sm font-semibold text-slate-900">Zone de service</span>
                <select wire:model="zoneId" class="w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900">
                    <option value="">Aucune</option>
                    @foreach ($zones as $zone)
                    <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <button type="button" wire:click="creer" data-test="agency-create"
            class="mt-4 w-full rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 sm:w-auto">
            Créer l'implantation
        </button>
    </div>

    {{-- Liste --}}
    <div class="space-y-4">
        @forelse ($agences as $agence)
        <div class="rounded-2xl border border-slate-200 bg-white p-5" wire:key="agence-{{ $agence->id }}">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">{{ $agence->name }}</h2>
                    <p class="text-sm text-slate-500">
                        {{ trim(($agence->postal_code ?? '') . ' ' . ($agence->city ?? '')) ?: 'Adresse à compléter' }}
                        @if ($agence->serviceZone)
                        — zone {{ $agence->serviceZone->name }}
                        @endif
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    @if ($agence->status === 'archived')
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500">Archivée</span>
                    <button type="button" wire:click="reactiver({{ $agence->id }})"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        Rouvrir
                    </button>
                    @else
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Active</span>
                    <button type="button" wire:click="archiver({{ $agence->id }})"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        Archiver
                    </button>
                    @endif
                    <button type="button" wire:click="ouvrirLeRattachement({{ $agence->id }})"
                        class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                        Équipes
                    </button>
                </div>
            </div>

            @if ($agence->fieldTeams->isNotEmpty())
            <p class="mt-3 text-sm text-slate-600">
                {{ $agence->fieldTeams->pluck('name')->join(', ') }}
            </p>
            @else
            <p class="mt-3 text-sm text-slate-400">Aucune équipe rattachée.</p>
            @endif

            @if ($agenceOuverteId === $agence->id)
            <div class="mt-4 space-y-2 border-t border-slate-100 pt-4">
                @foreach ($equipes as $equipe)
                <div class="flex items-center justify-between gap-3" wire:key="rattach-{{ $agence->id }}-{{ $equipe->id }}">
                    <span class="text-sm text-slate-700">{{ $equipe->name }}</span>
                    @if ((int) $equipe->provider_agency_id === $agence->id)
                    <button type="button" wire:click="rattacherEquipe({{ $agence->id }}, {{ $equipe->id }}, true)"
                        class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        Détacher
                    </button>
                    @else
                    <button type="button" wire:click="rattacherEquipe({{ $agence->id }}, {{ $equipe->id }})"
                        class="rounded-lg bg-blue-600 px-3 py-1 text-xs font-semibold text-white hover:bg-blue-700">
                        Rattacher
                    </button>
                    @endif
                </div>
                @endforeach

                @if ($equipes->isEmpty())
                <p class="text-sm text-slate-400">
                    Aucune équipe terrain. Créez-en une depuis
                    <a href="{{ route('provider-company.field-teams') }}" class="font-semibold text-blue-600 hover:underline">Équipes
                        terrain</a>.
                </p>
                @endif
            </div>
            @endif
        </div>
        @empty
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center">
            <p class="text-sm font-semibold text-slate-900">Aucune implantation déclarée</p>
            <p class="mt-1 text-sm text-slate-500">
                Une société qui n'a qu'un seul point de départ n'en déclare aucune : c'est le cas le
                plus courant, et il ne demande rien. Déclarez-les dès que vos équipes partent de
                plusieurs endroits — la répartition en tient compte.
            </p>
        </div>
        @endforelse
    </div>

    <p class="mt-6 text-xs text-slate-400">{{ $membres }} membre(s) actif(s) dans la société.</p>
</div>
