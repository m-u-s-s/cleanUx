<?php if (isset($component)) { $__componentOriginal12a5063a2f74bc3084facd42cc51c606 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal12a5063a2f74bc3084facd42cc51c606 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-shell','data' => ['title' => '📅 Mes rendez-vous','subtitle' => 'Gérez vos interventions, suivez l’employé, modifiez un créneau ou laissez un avis après la mission.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('page-shell'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => '📅 Mes rendez-vous','subtitle' => 'Gérez vos interventions, suivez l’employé, modifiez un créneau ou laissez un avis après la mission.']); ?>
     <?php $__env->slot('actions', null, []); ?> 
        <a
            href="<?php echo e(route('client.rendezvous.create')); ?>"
            class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700">
            ➕ Nouveau rendez-vous
        </a>
     <?php $__env->endSlot(); ?>


    <div class="bg-white rounded-2xl shadow-sm border p-5">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Recherche</label>
                <input
                    type="text"
                    wire:model.live.debounce.350ms="search"
                    placeholder="Service, ville, adresse, employé..."
                    class="w-full rounded-xl border-gray-300 shadow-sm text-sm">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Statut</label>
                <select wire:model.live="filtreStatus" class="w-full rounded-xl border-gray-300 shadow-sm text-sm">
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
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Tri</label>
                <select wire:model.live="tri" class="w-full rounded-xl border-gray-300 shadow-sm text-sm">
                    <option value="asc">Plus proche d’abord</option>
                    <option value="desc">Plus récent d’abord</option>
                </select>
            </div>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($editRdvId): ?>
    <div class="bg-yellow-50 p-5 border border-yellow-300 rounded-2xl shadow space-y-4">
        <div>
            <h4 class="font-semibold text-yellow-900">🔁 Replanifier le rendez-vous</h4>
            <p class="text-sm text-yellow-700">
                Choisis une nouvelle date et un créneau disponible. Le rendez-vous repassera en attente de confirmation.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="text-sm font-medium text-gray-700">Nouvelle date</label>
                <input
                    type="date"
                    wire:model.live="editDate"
                    class="w-full text-sm border-gray-300 rounded-lg px-3 py-2">
            </div>

            <div>
                <label class="text-sm font-medium text-gray-700">Heure sélectionnée</label>
                <input
                    type="time"
                    wire:model="editHeure"
                    class="w-full text-sm border-gray-300 rounded-lg px-3 py-2">
            </div>
        </div>

        <div>
            <p class="text-sm font-semibold text-gray-800 mb-2">Créneaux disponibles</p>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($creneauxDisponibles)): ?>
            <div class="flex flex-wrap gap-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $creneauxDisponibles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slot): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <button
                    type="button"
                    wire:click="$set('editHeure', '<?php echo e($slot['heure']); ?>')"
                    class="px-3 py-2 rounded-xl border text-sm
                            <?php echo e($editHeure === $slot['heure'] ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-blue-50'); ?>">
                    <?php echo e($slot['heure']); ?>

                    <span class="block text-xs opacity-80">
                        <?php echo e($slot['same_employee'] ? 'Même employé' : $slot['employe_name']); ?>

                    </span>
                </button>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <?php else: ?>
            <p class="text-sm text-red-600">
                Aucun créneau disponible pour cette date.
            </p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <div class="bg-white border rounded-xl p-4 text-sm text-gray-700 space-y-1">
            <p>
                💰 <span class="font-medium">Impact devis :</span>
                <?php echo e($impactDevisMessage ?? 'Le devis sera recalculé si nécessaire.'); ?>

            </p>
            <p>
                👤 <span class="font-medium">Employé :</span>
                le système garde le même employé si possible, sinon il propose un autre employé disponible.
            </p>
        </div>

        <div class="flex flex-wrap gap-3">
            <button
                wire:click="enregistrerModif"
                class="bg-blue-600 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-blue-700">
                ✅ Confirmer la replanification
            </button>

            <button
                wire:click="fermerEdition"
                class="px-4 py-2 rounded-xl border text-sm text-gray-700 bg-white hover:bg-gray-50">
                Annuler
            </button>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="space-y-4">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $rendezVous; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rdv): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <div class="border rounded-2xl p-4 shadow-sm bg-gray-50 space-y-4">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                <div>
                    <h4 class="font-semibold text-gray-800 text-lg">
                        <?php echo e($rdv->service_display_name); ?>

                    </h4>
                    <p class="text-sm text-gray-600">
                        📅 <?php echo e($rdv->date); ?> à <?php echo e($rdv->heure); ?>

                    </p>
                    <p class="text-sm text-gray-600">
                        🧑‍💼 <?php echo e($rdv->employe->name ?? 'Employé à confirmer'); ?>

                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => ['status' => $rdv->status]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($rdv->status)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $attributes = $__attributesOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $component = $__componentOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__componentOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
                    <?php if (isset($component)) { $__componentOriginal203f5a9932b19b3fc18de88ddfaf9f46 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal203f5a9932b19b3fc18de88ddfaf9f46 = $attributes; } ?>
<?php $component = App\View\Components\PriorityBadge::resolve(['priority' => $rdv->priorite] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('priority-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(App\View\Components\PriorityBadge::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal203f5a9932b19b3fc18de88ddfaf9f46)): ?>
<?php $attributes = $__attributesOriginal203f5a9932b19b3fc18de88ddfaf9f46; ?>
<?php unset($__attributesOriginal203f5a9932b19b3fc18de88ddfaf9f46); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal203f5a9932b19b3fc18de88ddfaf9f46)): ?>
<?php $component = $__componentOriginal203f5a9932b19b3fc18de88ddfaf9f46; ?>
<?php unset($__componentOriginal203f5a9932b19b3fc18de88ddfaf9f46); ?>
<?php endif; ?>
                </div>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rdv->recurring_series_id): ?>
            <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-3 text-sm text-indigo-800">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-semibold">🔁 Série récurrente</span>
                    <span>Position : #<?php echo e($rdv->series_position ?? '—'); ?></span>
                    <span>Statut série : <?php echo e(ucfirst($rdv->series_status ?? 'active')); ?></span>
                </div>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-700">
                <div class="space-y-1">
                    <p><span class="font-medium">Type de lieu :</span> <?php echo e(ucfirst($rdv->type_lieu ?? '—')); ?></p>
                    <p><span class="font-medium">Fréquence :</span> <?php echo e(ucfirst(str_replace('_', ' ', $rdv->frequence ?? '—'))); ?></p>
                    <p><span class="font-medium">Surface :</span> <?php echo e($rdv->surface ?? '—'); ?></p>
                    <p><span class="font-medium">Durée estimée :</span> <?php echo e($rdv->duree_estimee ? $rdv->duree_estimee . ' min' : '—'); ?></p>
                </div>

                <div class="space-y-1">
                    <p><span class="font-medium">Adresse :</span> <?php echo e($rdv->adresse ?? '—'); ?></p>
                    <p><span class="font-medium">Ville :</span> <?php echo e($rdv->ville ?? '—'); ?></p>
                    <p><span class="font-medium">Code postal :</span> <?php echo e($rdv->postal_code_display); ?></p>
                    <p><span class="font-medium">Téléphone :</span> <?php echo e($rdv->telephone_client ?? '—'); ?></p>
                </div>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rdv->commentaire_client): ?>
            <div class="text-sm text-gray-700 bg-white border rounded-xl p-3">
                <span class="font-medium">Remarque :</span> <?php echo e($rdv->commentaire_client); ?>

            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div class="bg-white border rounded-xl p-4 space-y-4">
                <p class="text-sm font-semibold text-slate-800">🧭 Suivi de mission</p>

                <div class="flex flex-wrap gap-2 text-xs">
                    <span class="px-3 py-1 rounded-full <?php echo e(in_array($rdv->status, ['en_attente','confirme','en_route','sur_place','termine']) ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500'); ?>">
                        Demande reçue
                    </span>
                    <span class="px-3 py-1 rounded-full <?php echo e(in_array($rdv->status, ['confirme','en_route','sur_place','termine']) ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'); ?>">
                        Confirmée
                    </span>
                    <span class="px-3 py-1 rounded-full <?php echo e(in_array($rdv->status, ['en_route','sur_place','termine']) ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500'); ?>">
                        En route
                    </span>
                    <span class="px-3 py-1 rounded-full <?php echo e(in_array($rdv->status, ['sur_place','termine']) ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-500'); ?>">
                        Sur place
                    </span>
                    <span class="px-3 py-1 rounded-full <?php echo e($rdv->status === 'termine' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'); ?>">
                        Terminée
                    </span>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rdv->mission): ?>
                <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('client.mission-tracking', ['mission' => $rdv->mission]);

$__key = 'mission-tracking-'.$rdv->mission->id;

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-210964101-0', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key);

echo $__html;

unset($__html);
unset($__key);
unset($__name);
unset($__params);
unset($__split);
if (isset($__slots)) unset($__slots);
?>
                <?php else: ?>
                <p class="text-sm text-slate-500">Le suivi mission détaillé apparaîtra dès qu’une mission opérationnelle sera synchronisée.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div class="flex flex-wrap gap-2 pt-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rdv->mission): ?>
                <a href="<?php echo e(route('missions.tracking.live', $rdv->mission)); ?>"
                    class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    📍 Suivre la mission
                </a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rdv->mission): ?>
                <a
                    href="<?php echo e(route('client.missions.tracking', $rdv->mission)); ?>"
                    class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">
                    Suivre mon employé
                </a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rdv->canStillBeEditedByClient()): ?>
                <button wire:click="modifier(<?php echo e($rdv->id); ?>)"
                    class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    🔁 Replanifier
                </button>

                <button wire:click="demanderAnnulation(<?php echo e($rdv->id); ?>)"
                    class="rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100">
                    Annuler
                </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rdv->recurring_series_id): ?>
                <a href="<?php echo e(route('client.rendezvous.series.edit', $rdv->id)); ?>"
                    class="rounded-xl border px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">
                    🗓️ Gérer la série
                </a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rdv->status === 'termine' && $rdv->feedback): ?>
                <span class="rounded-xl bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700">
                    💬 Feedback laissé
                </span>
                <?php elseif($rdv->status === 'termine'): ?>
                <a href="<?php echo e(route('feedback.create', $rdv->id)); ?>"
                    class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    ⭐ Laisser un avis
                </a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['title' => 'Aucun rendez-vous trouvé','message' => 'Essayez un autre filtre ou créez un nouveau rendez-vous.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Aucun rendez-vous trouvé','message' => 'Essayez un autre filtre ou créez un nouveau rendez-vous.']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal074a021b9d42f490272b5eefda63257c)): ?>
<?php $attributes = $__attributesOriginal074a021b9d42f490272b5eefda63257c; ?>
<?php unset($__attributesOriginal074a021b9d42f490272b5eefda63257c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal074a021b9d42f490272b5eefda63257c)): ?>
<?php $component = $__componentOriginal074a021b9d42f490272b5eefda63257c; ?>
<?php unset($__componentOriginal074a021b9d42f490272b5eefda63257c); ?>
<?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <div class="mt-4">
        <?php echo e($rendezVous->links()); ?>

    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cancelRdvId): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">
        <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl space-y-4">
            <div>
                <h3 class="text-lg font-semibold text-slate-900">Confirmer l’annulation</h3>
                <p class="mt-1 text-sm text-slate-500">Ajoute une raison si tu veux garder une trace côté support.</p>
            </div>

            <textarea
                wire:model.defer="cancelReason"
                rows="4"
                placeholder="Raison d’annulation (facultatif)..."
                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-500 focus:outline-none"></textarea>

            <div class="flex flex-wrap justify-end gap-3">
                <button type="button" wire:click="fermerAnnulation" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700">Retour</button>
                <button type="button" wire:click="confirmerAnnulation" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white">Confirmer l’annulation</button>
            </div>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal12a5063a2f74bc3084facd42cc51c606)): ?>
<?php $attributes = $__attributesOriginal12a5063a2f74bc3084facd42cc51c606; ?>
<?php unset($__attributesOriginal12a5063a2f74bc3084facd42cc51c606); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal12a5063a2f74bc3084facd42cc51c606)): ?>
<?php $component = $__componentOriginal12a5063a2f74bc3084facd42cc51c606; ?>
<?php unset($__componentOriginal12a5063a2f74bc3084facd42cc51c606); ?>
<?php endif; ?><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/client/mes-rendez-vous-client.blade.php ENDPATH**/ ?>