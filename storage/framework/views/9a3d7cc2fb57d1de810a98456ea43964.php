<div class="space-y-5">
    <div class="bg-white border rounded-2xl shadow-sm p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div>
                <label class="text-xs font-semibold text-slate-500 uppercase">Priorité</label>
                <select wire:model.live="priorite" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                    <option value="">Toutes</option>
                    <option value="normale">Normale</option>
                    <option value="haute">Haute</option>
                    <option value="urgente">Urgente</option>
                </select>
            </div>

            <div>
                <label class="text-xs font-semibold text-slate-500 uppercase">Statut</label>
                <select wire:model.live="filtreStatus" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                    <option value="">Tous</option>
                    <option value="en_attente">En attente</option>
                    <option value="confirme">Confirmé</option>
                    <option value="en_route">En route</option>
                    <option value="sur_place">Sur place</option>
                    <option value="termine">Terminé</option>
                    <option value="refuse">Refusé</option>
                </select>
            </div>

            <div>
                <label class="text-xs font-semibold text-slate-500 uppercase">Tri</label>
                <select wire:model.live="tri" class="mt-1 w-full rounded-xl border-slate-200 text-sm">
                    <option value="asc">Plus proche d’abord</option>
                    <option value="desc">Plus récent d’abord</option>
                </select>
            </div>

            <div>
                <label class="text-xs font-semibold text-slate-500 uppercase">Recherche</label>
                <input
                    type="text"
                    wire:model.live.debounce.350ms="search"
                    class="mt-1 w-full rounded-xl border-slate-200 text-sm"
                    placeholder="Client, adresse, ville...">
            </div>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $rendezVous; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rdv): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <?php
    $statusLabel = [
    'en_attente' => 'En attente',
    'confirme' => 'Confirmé',
    'en_route' => 'En route',
    'sur_place' => 'Sur place',
    'termine' => 'Terminé',
    'refuse' => 'Refusé',
    ][$rdv->status] ?? ucfirst($rdv->status ?? '—');

    $statusClass = [
    'en_attente' => 'bg-amber-50 text-amber-700 border-amber-200',
    'confirme' => 'bg-blue-50 text-blue-700 border-blue-200',
    'en_route' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
    'sur_place' => 'bg-purple-50 text-purple-700 border-purple-200',
    'termine' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'refuse' => 'bg-red-50 text-red-700 border-red-200',
    ][$rdv->status] ?? 'bg-slate-50 text-slate-700 border-slate-200';

    $mapsQuery = urlencode(trim(($rdv->adresse ?? '').' '.($rdv->ville ?? '')));
    $photosAvantCount = is_array($rdv->photos_avant) ? count($rdv->photos_avant) : 0;
    $photosApresCount = is_array($rdv->photos_apres) ? count($rdv->photos_apres) : 0;
    ?>

    <div class="bg-white border rounded-2xl shadow-sm overflow-hidden <?php echo e($selectedRendezVous?->id === $rdv->id ? 'ring-2 ring-indigo-300' : ''); ?>">
        <div class="p-5 space-y-4">
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-bold <?php echo e($statusClass); ?>">
                            <?php echo e($statusLabel); ?>

                        </span>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rdv->priorite): ?>
                        <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold bg-slate-50 text-slate-700">
                            Priorité : <?php echo e(ucfirst($rdv->priorite)); ?>

                        </span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rdv->mission): ?>
                        <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold bg-indigo-50 text-indigo-700">
                            Mission #<?php echo e($rdv->mission->id); ?>

                        </span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <h3 class="text-lg font-bold text-slate-900">
                        <?php echo e($rdv->service_display_name ?? 'Mission de nettoyage'); ?>

                    </h3>

                    <p class="text-sm text-slate-500">
                        <?php echo e($rdv->date?->format('d/m/Y') ?? 'Date inconnue'); ?>

                        à <?php echo e(substr((string) $rdv->heure, 0, 5)); ?>

                        · <?php echo e($rdv->client?->name ?? 'Client inconnu'); ?>

                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rdv->adresse || $rdv->ville): ?>
                    <a
                        href="https://www.google.com/maps/search/?api=1&query=<?php echo e($mapsQuery); ?>"
                        target="_blank"
                        class="inline-flex items-center rounded-xl bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">
                        🗺️ Google Maps
                    </a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rdv->telephone_client): ?>
                    <a
                        href="tel:<?php echo e($rdv->telephone_client); ?>"
                        class="inline-flex items-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                        📞 Appeler
                    </a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                <div class="rounded-xl bg-slate-50 border p-3">
                    <p class="text-xs text-slate-500 font-semibold uppercase">Adresse</p>
                    <p class="mt-1 font-medium text-slate-800">
                        <?php echo e($rdv->adresse ?? '—'); ?> <?php echo e($rdv->ville ? ', '.$rdv->ville : ''); ?>

                    </p>
                </div>

                <div class="rounded-xl bg-slate-50 border p-3">
                    <p class="text-xs text-slate-500 font-semibold uppercase">Timing</p>
                    <p class="mt-1 text-slate-800">
                        Départ : <?php echo e($rdv->mission_started_at?->format('H:i') ?? '—'); ?><br>
                        Arrivée : <?php echo e($rdv->mission_arrived_at?->format('H:i') ?? '—'); ?>

                    </p>
                </div>

                <div class="rounded-xl bg-slate-50 border p-3">
                    <p class="text-xs text-slate-500 font-semibold uppercase">Preuves</p>
                    <p class="mt-1 text-slate-800">
                        Avant : <?php echo e($photosAvantCount); ?> photo(s)<br>
                        Après : <?php echo e($photosApresCount); ?> photo(s)
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 pt-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rdv->status === 'en_attente'): ?>
                <button wire:click="mettreAJourStatut(<?php echo e($rdv->id); ?>, 'confirme')" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    ✅ Confirmer
                </button>

                <button wire:click="mettreAJourStatut(<?php echo e($rdv->id); ?>, 'refuse')" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                    ❌ Refuser
                </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rdv->status === 'confirme'): ?>
                <button wire:click="mettreAJourStatut(<?php echo e($rdv->id); ?>, 'en_route')" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    🚗 Je suis en route
                </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rdv->status === 'en_route'): ?>
                <button wire:click="ouvrirCheckInMission(<?php echo e($rdv->id); ?>)" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    📍 Je suis arrivé
                </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rdv->status === 'sur_place'): ?>
                <button wire:click="ouvrirRapportFinMission(<?php echo e($rdv->id); ?>)" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    ✅ Terminer la mission
                </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rdv->mission): ?>
                <button
                    wire:click="<?php echo e($selectedRendezVous?->id === $rdv->id ? 'clearSelectedRdv' : 'selectRdv('.$rdv->id.')'); ?>"
                    class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    <?php echo e($selectedRendezVous?->id === $rdv->id ? 'Fermer détails' : 'Voir détails mission'); ?>

                </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Route::has('employe.incident')): ?>
                <a href="<?php echo e(route('employe.incident')); ?>" class="rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100">
                    🚨 Incident
                </a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rdv->remarque_terrain || $rdv->incident_terrain): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rdv->remarque_terrain): ?>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                    <span class="font-bold">Remarque terrain :</span>
                    <?php echo e($rdv->remarque_terrain); ?>

                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rdv->incident_terrain): ?>
                <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                    <span class="font-bold">Incident :</span>
                    <?php echo e($rdv->incident_terrain); ?>

                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <div class="bg-white border border-dashed rounded-2xl p-8 text-center">
        <p class="text-lg font-bold text-slate-800">Aucune mission trouvée</p>
        <p class="text-sm text-slate-500 mt-1">Change les filtres ou vérifie ton planning.</p>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div>
        <?php echo e($rendezVous->links()); ?>

    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showCheckInModal): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
        <div class="bg-white w-full max-w-2xl rounded-xl shadow-xl p-6 space-y-4">
            <div>
                <h3 class="text-xl font-semibold text-gray-800">Check-in terrain</h3>
                <p class="text-sm text-gray-500">Validez l’arrivée, l’état d’accès et capturez les éléments de départ.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = [
                'acces_ok' => 'Accès OK',
                'materiel_ok' => 'Matériel OK',
                'zone_securisee' => 'Zone sécurisée',
                'etat_initial_capture' => 'État initial capturé',
                'client_present' => 'Client présent',
                ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <label class="flex items-center gap-2 rounded-lg border p-3 text-sm text-slate-700">
                    <input type="checkbox" wire:model="terrain_checklist.<?php echo e($key); ?>">
                    <span><?php echo e($label); ?></span>
                </label>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Remarque terrain</label>
                <textarea wire:model="remarque_terrain" rows="3" class="w-full border rounded px-3 py-2 text-sm" placeholder="Code d’accès, difficulté d’accès, état des lieux..."></textarea>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['remarque_terrain'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 text-sm"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Photos avant intervention</label>
                <input type="file" wire:model="photos_avant" multiple accept="image/*" class="w-full text-sm border rounded px-3 py-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['photos_avant.*'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 text-sm"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($photos_avant): ?>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $photos_avant; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $photo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <img src="<?php echo e($photo->temporaryUrl()); ?>" alt="Aperçu photo avant intervention" class="w-full h-28 object-cover rounded border">
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div class="flex justify-end gap-3 pt-2">
                <button wire:click="fermerCheckInMission" class="px-4 py-2 rounded border text-sm text-gray-700 hover:bg-gray-50">
                    Annuler
                </button>
                <button wire:click="sauverCheckInMission" class="px-4 py-2 rounded bg-indigo-600 text-white text-sm hover:bg-indigo-700">
                    Enregistrer le check-in
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showRapportModal): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4 overflow-y-auto">
        <div class="bg-white w-full max-w-3xl rounded-xl shadow-xl p-6 space-y-4 my-8">
            <div>
                <h3 class="text-xl font-semibold text-gray-800">Rapport de fin de mission</h3>
                <p class="text-sm text-gray-500">Ajoutez le compte-rendu complet avant de clôturer la mission.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Durée réelle (minutes)</label>
                    <input type="number" min="15" wire:model="duree_reelle" class="w-full border rounded px-3 py-2 text-sm">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['duree_reelle'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 text-sm"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <label class="flex items-center gap-2 rounded-lg border p-3 text-sm text-slate-700 mt-6 md:mt-0">
                    <input type="checkbox" wire:model="client_presence_confirmee">
                    <span>Présence client confirmée</span>
                </label>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Commentaire de fin de mission</label>
                <textarea wire:model="commentaire_fin_mission" rows="4" class="w-full border rounded px-3 py-2 text-sm" placeholder="Résumé du travail effectué, état final, recommandations..."></textarea>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['commentaire_fin_mission'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 text-sm"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Incident ou anomalie terrain</label>
                <textarea wire:model="incident_terrain" rows="3" class="w-full border rounded px-3 py-2 text-sm" placeholder="Détaillez ici un incident, un litige ou une anomalie constatée."></textarea>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['incident_terrain'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 text-sm"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Photos après intervention</label>
                <input type="file" wire:model="photos_apres" multiple accept="image/*" class="w-full text-sm border rounded px-3 py-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['photos_apres.*'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 text-sm"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($photos_apres): ?>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $photos_apres; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $photo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <img src="<?php echo e($photo->temporaryUrl()); ?>" alt="Aperçu photo après intervention" class="w-full h-28 object-cover rounded border">
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div x-data="cleanuxSignaturePad($wire)" class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="block text-sm font-medium text-gray-700">Signature client (optionnelle)</label>
                    <button type="button" @click="clear()" class="text-sm text-slate-500 hover:text-slate-700">Effacer</button>
                </div>
                <canvas x-ref="canvas" x-init="init()" class="w-full h-44 rounded-xl border bg-slate-50"></canvas>
                <p class="text-xs text-slate-500">Le client peut signer au doigt ou à la souris directement sur cet espace.</p>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button wire:click="fermerRapportFinMission" class="px-4 py-2 rounded border text-sm text-gray-700 hover:bg-gray-50">
                    Annuler
                </button>

                <button wire:click="sauverRapportFinMission" class="px-4 py-2 rounded bg-emerald-600 text-white text-sm hover:bg-emerald-700">
                    Enregistrer et terminer
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if (! $__env->hasRenderedOnce('87bd81cd-df53-42be-a3cf-838e2592f021')): $__env->markAsRenderedOnce('87bd81cd-df53-42be-a3cf-838e2592f021'); ?>
    <script>
        window.cleanuxSignaturePad = function($wire) {
            return {
                canvas: null,
                ctx: null,
                drawing: false,
                init() {
                    this.canvas = this.$refs.canvas;
                    this.ctx = this.canvas.getContext('2d');
                    const ratio = window.devicePixelRatio || 1;
                    const rect = this.canvas.getBoundingClientRect();
                    this.canvas.width = rect.width * ratio;
                    this.canvas.height = rect.height * ratio;
                    this.ctx.scale(ratio, ratio);
                    this.ctx.lineWidth = 2;
                    this.ctx.lineCap = 'round';
                    this.ctx.strokeStyle = '#0f172a';

                    const start = (x, y) => {
                        this.drawing = true;
                        this.ctx.beginPath();
                        this.ctx.moveTo(x, y);
                    };
                    const draw = (x, y) => {
                        if (!this.drawing) return;
                        this.ctx.lineTo(x, y);
                        this.ctx.stroke();
                        $wire.set('client_signature_data', this.canvas.toDataURL('image/png'));
                    };
                    const stop = () => {
                        this.drawing = false;
                    };
                    const pos = (e) => {
                        const r = this.canvas.getBoundingClientRect();
                        if (e.touches && e.touches[0]) {
                            return [e.touches[0].clientX - r.left, e.touches[0].clientY - r.top];
                        }
                        return [e.clientX - r.left, e.clientY - r.top];
                    };

                    this.canvas.addEventListener('mousedown', e => {
                        const [x, y] = pos(e);
                        start(x, y);
                    });
                    this.canvas.addEventListener('mousemove', e => {
                        const [x, y] = pos(e);
                        draw(x, y);
                    });
                    window.addEventListener('mouseup', stop);
                    this.canvas.addEventListener('touchstart', e => {
                        e.preventDefault();
                        const [x, y] = pos(e);
                        start(x, y);
                    }, {
                        passive: false
                    });
                    this.canvas.addEventListener('touchmove', e => {
                        e.preventDefault();
                        const [x, y] = pos(e);
                        draw(x, y);
                    }, {
                        passive: false
                    });
                    window.addEventListener('touchend', stop);
                },
                clear() {
                    if (!this.ctx || !this.canvas) return;
                    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                    $wire.set('client_signature_data', null);
                }
            }
        }
    </script>
    <?php endif; ?>
</div><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/employe/mes-rendez-vous.blade.php ENDPATH**/ ?>