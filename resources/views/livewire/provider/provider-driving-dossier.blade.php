{{--
    Le dossier de conduite : ce qui manque, et ce que ça empêche.

    L'écran est bâti autour d'une idée : un prestataire qui ne reçoit plus de missions doit
    APPRENDRE POURQUOI ici, pas le deviner. La plateforme a déjà connu le compte actif mais jamais
    vérifié — application ouverte, téléphone muet, et personne pour faire le lien.
--}}
<div class="mx-auto max-w-3xl space-y-6 p-4">

    <header>
        <h1 class="text-2xl font-semibold text-slate-900">Conduite et véhicule</h1>
        <p class="mt-1 text-sm text-slate-500">
            @if ($metiersConcernes)
                Exigé pour&nbsp;: {{ implode(', ', $metiersConcernes) }}.
            @else
                Les pièces demandées dépendent des métiers que vous avez déclarés.
            @endif
        </p>
    </header>

    @if ($message)
        <p class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ $message }}</p>
    @endif

    @if ($erreur)
        <p class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-900">{{ $erreur }}</p>
    @endif

    @if ($exigences->isEmpty())
        <p class="rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-600">
            Aucun de vos métiers ne demande de permis ni de véhicule. Rien à faire ici.
        </p>
    @else
        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold text-slate-900">Vos pièces</h2>

            <ul class="mt-4 space-y-3">
                @foreach ($exigences as $exigence)
                    @php $piece = $deposees[$exigence['type']] ?? null; @endphp
                    <li class="flex items-start justify-between gap-4 rounded-xl border border-slate-200 px-4 py-3">
                        <span>
                            <span class="block text-[15px] font-medium text-slate-900">{{ $exigence['label'] }}</span>
                            <span class="mt-0.5 block text-xs text-slate-500">{{ $exigence['help'] }}</span>
                            @if ($piece?->rejection_reason)
                                <span class="mt-1 block text-xs text-rose-700">Refusée&nbsp;: {{ $piece->rejection_reason }}</span>
                            @endif
                            @if ($piece?->expires_at)
                                <span class="mt-1 block text-xs text-slate-500">Valide jusqu’au {{ $piece->expires_at->format('d/m/Y') }}</span>
                            @endif
                        </span>

                        {{-- L'état vient du serveur, y compris « refusée » : sans lui, on ne peut pas corriger. --}}
                        <span @class([
                            'shrink-0 rounded-full px-3 py-1 text-xs font-medium',
                            'bg-slate-100 text-slate-600' => $piece === null,
                            'bg-amber-100 text-amber-800' => $piece?->status === 'pending_review',
                            'bg-emerald-100 text-emerald-800' => $piece?->status === 'approved',
                            'bg-rose-100 text-rose-800' => $piece?->status === 'rejected',
                        ])>
                            @switch($piece?->status)
                                @case('approved') Validée @break
                                @case('rejected') À redéposer @break
                                @case('pending_review') En relecture @break
                                @default Manquante
                            @endswitch
                        </span>
                    </li>
                @endforeach
            </ul>

            <form wire:submit="deposer" class="mt-5 space-y-3 border-t border-slate-200 pt-5">
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-slate-700">Pièce à déposer</span>
                    <select wire:model="typeDocument" class="w-full rounded-xl border-slate-300">
                        @foreach ($exigences as $exigence)
                            <option value="{{ $exigence['type'] }}">{{ $exigence['label'] }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-slate-700">Fichier (PDF ou photo)</span>
                    <input type="file" wire:model="fichier" accept=".pdf,image/*"
                        class="w-full rounded-xl border border-slate-300 p-2 text-sm">
                    @error('fichier') <span class="mt-1 block text-sm text-rose-700">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-slate-700">Valide jusqu’au (si la pièce a une date)</span>
                    <input type="date" wire:model="expiresAt" class="w-full rounded-xl border-slate-300">
                    @error('expiresAt') <span class="mt-1 block text-sm text-rose-700">{{ $message }}</span> @enderror
                </label>

                <button type="submit"
                    class="min-h-[44px] rounded-xl bg-slate-900 px-5 text-sm font-medium text-white transition hover:bg-slate-800">
                    Envoyer
                </button>
            </form>
        </section>
    @endif

    @if ($dossierVehicule['requis'])
        <section class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="text-lg font-semibold text-slate-900">Votre véhicule</h2>
            <p class="mt-0.5 text-sm text-slate-500">
                Il doit avoir moins de {{ $dossierVehicule['limite'] }} ans. L’âge est calculé depuis la
                date de PREMIÈRE immatriculation — celle qui figure sur la carte grise.
            </p>

            {{-- Le verdict est affiché AVANT que le prestataire se demande pourquoi rien n'arrive. --}}
            @if (! $dossierVehicule['conforme'])
                <p class="mt-3 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-900">
                    {{ $dossierVehicule['motif'] }}
                </p>
            @elseif ($dossierVehicule['age'] !== null)
                <p class="mt-3 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    Véhicule conforme — {{ str_replace('.', ',', (string) round($dossierVehicule['age'], 1)) }} ans.
                </p>
            @endif

            <form wire:submit="enregistrerLeVehicule" class="mt-4 grid gap-3 sm:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-slate-700">Plaque</span>
                    <input type="text" wire:model="plate" class="w-full rounded-xl border-slate-300 uppercase">
                    @error('plate') <span class="mt-1 block text-sm text-rose-700">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-slate-700">Première immatriculation</span>
                    <input type="date" wire:model="registeredAt" class="w-full rounded-xl border-slate-300">
                    @error('registeredAt') <span class="mt-1 block text-sm text-rose-700">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-slate-700">Marque</span>
                    <input type="text" wire:model="brand" class="w-full rounded-xl border-slate-300">
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-slate-700">Modèle</span>
                    <input type="text" wire:model="model" class="w-full rounded-xl border-slate-300">
                </label>

                <div class="sm:col-span-2">
                    <button type="submit"
                        class="min-h-[44px] rounded-xl bg-slate-900 px-5 text-sm font-medium text-white transition hover:bg-slate-800">
                        Enregistrer mon véhicule
                    </button>
                </div>
            </form>
        </section>
    @endif
</div>
