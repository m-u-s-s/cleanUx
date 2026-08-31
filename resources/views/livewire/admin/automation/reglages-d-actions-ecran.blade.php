{{--
    LE SEUL ECRAN QUI ARME LE MOTEUR. Une action « autonome » s'execute seule ; « a valider »
    propose et attend un humain (voir FileDePropositions). Rendre autonome une action qui touche
    au domaine passe par une confirmation renforcee : `wire:confirm.prompt`, reserve par ce depot
    a exactement ce futur (voir WireConfirmPasseParLaModaleTest) plutot que la modale de verre
    ordinaire, utilisee ailleurs pour des actions a un seul clic.
--}}
<div class="space-y-6">
    <x-page-shell
        eyebrow="Automatisation"
        title="Réglages d'actions"
        subtitle="Ce que le moteur peut faire seul, action par action — et ce qui reste à valider."
    >
        <x-slot:actions>
            <a href="{{ route('admin.automation') }}" class="brio-btn brio-btn-secondary">← Règles</a>
        </x-slot:actions>
    </x-page-shell>

    <x-table-shell title="Actions du registre" subtitle="Autonome : le moteur agit seul. À valider : il propose, un administrateur tranche.">
        <table class="min-w-full brio-table">
            <thead>
                <tr>
                    <th>Action</th>
                    <th>Touche le domaine</th>
                    <th>État</th>
                    <th class="text-right">Bascule</th>
                </tr>
            </thead>
            <tbody>
                @forelse($actions as $cle => $action)
                    @php($estAutonome = $autonomies[$cle] ?? false)
                    <tr>
                        <td>
                            <div class="font-semibold" style="color: var(--brio-ink);">{{ $action['libelle'] }}</div>
                            <div class="text-xs" style="color: var(--brio-muted);">{{ $cle }}</div>
                        </td>
                        <td>
                            <span class="brio-chip brio-teinte" style="--teinte: {{ $action['touche_au_domaine'] ? 'var(--brio-warning)' : 'var(--brio-muted)' }};">
                                {{ $action['touche_au_domaine'] ? 'Oui' : 'Non' }}
                            </span>
                        </td>
                        <td>
                            <span class="brio-chip brio-teinte" style="--teinte: {{ $estAutonome ? 'var(--brio-success)' : 'var(--brio-info)' }};">
                                {{ $estAutonome ? 'Autonome' : 'À valider' }}
                            </span>
                        </td>
                        <td>
                            <div class="flex justify-end">
                                @if($estAutonome)
                                    <button type="button" class="brio-btn brio-btn-secondary" wire:click="basculer('{{ $cle }}', false)">Repasser à valider</button>
                                @else
                                    <button type="button" class="brio-btn brio-btn-primary" wire:click="basculer('{{ $cle }}', true)">Rendre autonome</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            <x-empty-state title="Aucune action" message="Le registre d'actions est vide." icon="⚙️" />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-table-shell>

    {{-- LA CONDITION EST DEHORS, et ce n'est pas cosmetique : `@teleport` rend un
         `<template x-teleport>` qu'Alpine clone A SON INITIALISATION, emis vide s'il n'y a
         rien a montrer au premier rendu (piege deja paye sur ce depot). --}}
    @if($actionEnConfirmation !== null)
        @teleport('body')
            <div class="brio-modal-fond grid place-items-center p-4" x-data x-on:keydown.escape.window="$wire.annulerConfirmation()">
                <div class="brio-modal brio-modal-danger" role="alertdialog" aria-modal="true" aria-labelledby="titre-confirmation-autonomie">
                    <h2 id="titre-confirmation-autonomie" class="brio-modal-titre">Rendre cette action autonome ?</h2>
                    <p class="brio-modal-texte">
                        « {{ $actions[$actionEnConfirmation]['libelle'] ?? $actionEnConfirmation }} » touche au domaine métier.
                        Une fois autonome, le moteur l'exécutera seul sur les entités concernées, sans qu'un
                        administrateur ne tranche au préalable.
                    </p>
                    <div class="brio-modal-actions">
                        {{-- LE REFUS PORTE LE FOCUS. Une modale qui s'ouvre sur son bouton le
                             plus consequent transforme une touche Entree en autonomie accordee. --}}
                        <button type="button" x-init="$el.focus()" wire:click="annulerConfirmation" class="brio-btn brio-btn-nu">
                            Annuler
                        </button>

                        <button
                            type="button"
                            class="brio-btn brio-btn-primary"
                            wire:confirm.prompt="Tapez OUI pour confirmer : le moteur agira seul, sans validation humaine.|OUI"
                            wire:click="confirmerAutonomie"
                        >
                            Confirmer l'autonomie
                        </button>
                    </div>
                </div>
            </div>
        @endteleport
    @endif
</div>
