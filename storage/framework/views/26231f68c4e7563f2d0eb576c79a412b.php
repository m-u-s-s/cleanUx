<?php if (isset($component)) { $__componentOriginal12a5063a2f74bc3084facd42cc51c606 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal12a5063a2f74bc3084facd42cc51c606 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-shell','data' => ['title' => '🕘 Historique','subtitle' => 'Retrouvez vos missions terminées, rapports de fin, photos après intervention et feedbacks.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('page-shell'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => '🕘 Historique','subtitle' => 'Retrouvez vos missions terminées, rapports de fin, photos après intervention et feedbacks.']); ?>
     <?php $__env->slot('actions', null, []); ?> 
        <a
            href="<?php echo e(route('client.rendezvous.create', ['prefill' => 'last'])); ?>"
            class="inline-flex items-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700">
            ➕ Reprendre le dernier type de prestation
        </a>
     <?php $__env->endSlot(); ?>

    <div class="bg-white rounded-2xl shadow border p-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Recherche</label>
                <input
                    type="text"
                    wire:model.live="search"
                    placeholder="Service, ville, adresse..."
                    class="w-full border-gray-300 rounded-lg shadow-sm">
            </div>

            <div class="flex items-end">
                <button
                    wire:click="$set('tri', '<?php echo e($tri === 'asc' ? 'desc' : 'asc'); ?>')"
                    class="inline-flex items-center px-4 py-2 rounded-lg border bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Trier : <?php echo e($tri === 'asc' ? 'Croissant' : 'Décroissant'); ?>

                </button>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $historique; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rdv): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <div class="border rounded-2xl p-4 bg-gray-50 text-sm text-gray-700 space-y-4">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                <div>
                    <p class="font-medium text-gray-800 text-lg">
                        <?php echo e($rdv->service_display_name); ?>

                    </p>
                    <p><?php echo e($rdv->date); ?> à <?php echo e($rdv->heure); ?></p>
                    <p>🧑‍💼 <?php echo e($rdv->employe->name ?? '—'); ?></p>
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

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <p><span class="font-medium">Adresse :</span> <?php echo e($rdv->adresse ?? '—'); ?>, <?php echo e($rdv->ville ?? '—'); ?></p>
                    <p><span class="font-medium">Type de lieu :</span> <?php echo e(ucfirst($rdv->type_lieu ?? '—')); ?></p>
                    <p><span class="font-medium">Durée estimée :</span> <?php echo e($rdv->duree_estimee ? $rdv->duree_estimee . ' min' : '—'); ?></p>
                    <p><span class="font-medium">Durée réelle :</span> <?php echo e($rdv->duree_reelle ? $rdv->duree_reelle . ' min' : '—'); ?></p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="rounded-xl bg-white border p-4">
                        <p class="text-xs font-bold uppercase text-slate-500">Durée prévue</p>
                        <p class="mt-1 text-lg font-black text-slate-900">
                            <?php echo e($rdv->duree_estimee ? $rdv->duree_estimee.' min' : '—'); ?>

                        </p>
                    </div>

                    <div class="rounded-xl bg-white border p-4">
                        <p class="text-xs font-bold uppercase text-slate-500">Durée réelle</p>
                        <p class="mt-1 text-lg font-black text-slate-900">
                            <?php echo e($rdv->duree_reelle ? $rdv->duree_reelle.' min' : '—'); ?>

                        </p>
                    </div>

                    <div class="rounded-xl bg-white border p-4">
                        <p class="text-xs font-bold uppercase text-slate-500">Qualité</p>
                        <p class="mt-1 text-lg font-black text-emerald-700">
                            <?php echo e($rdv->mission?->quality_score ? $rdv->mission->quality_score.'/100' : 'Validée'); ?>

                        </p>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rdv->mission && Route::has('missions.report.pdf')): ?>
                    <a href="<?php echo e(route('missions.report.pdf', $rdv->mission)); ?>"
                        class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        📄 Télécharger le rapport
                    </a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div>
                    <p><span class="font-medium">Fréquence :</span> <?php echo e(ucfirst(str_replace('_', ' ', $rdv->frequence ?? '—'))); ?></p>
                    <p><span class="font-medium">Surface :</span> <?php echo e($rdv->surface ?? '—'); ?></p>
                </div>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rdv->commentaire_fin_mission): ?>
            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3">
                <span class="font-medium text-emerald-800">Rapport de fin d’intervention :</span>
                <p class="mt-1 text-emerald-900"><?php echo e($rdv->commentaire_fin_mission); ?></p>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rdv->feedback): ?>
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-3">
                <span class="font-medium text-amber-800">Votre feedback :</span>
                <p class="mt-1">Note : <?php echo e($rdv->feedback->note ?? '—'); ?>/5</p>
                <p><?php echo e($rdv->feedback->commentaire ?? 'Aucun commentaire.'); ?></p>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rdv->feedback->reponse_admin): ?>
                <div class="mt-2 pt-2 border-t border-amber-200">
                    <span class="font-medium text-amber-800">Réponse admin :</span>
                    <p><?php echo e($rdv->feedback->reponse_admin); ?></p>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div class="flex flex-wrap gap-3 text-sm">
                <a href="<?php echo e(route('client.rendezvous.create', ['source_rdv' => $rdv->id])); ?>" class="text-slate-700 underline">
                    🔁 Reprendre cette prestation
                </a>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$rdv->feedback): ?>
                <a href="<?php echo e(route('feedback.create', $rdv->id)); ?>" class="text-blue-600 underline">
                    💬 Laisser un feedback
                </a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($rdv->photos_avant) || !empty($rdv->photos_apres)): ?>
            <div class="rounded-2xl bg-white border p-4 space-y-4">
                <div>
                    <p class="text-sm font-bold text-slate-900">📷 Preuves photo</p>
                    <p class="text-xs text-slate-500">Photos prises avant et après la mission.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <p class="text-xs font-bold uppercase text-slate-500 mb-2">Avant intervention</p>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($rdv->photos_avant)): ?>
                        <div class="grid grid-cols-2 gap-3">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $rdv->photos_avant; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $photo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <a href="<?php echo e(asset('storage/'.$photo)); ?>" target="_blank">
                                <img src="<?php echo e(asset('storage/'.$photo)); ?>"
                                    class="h-28 w-full rounded-xl object-cover border hover:opacity-90">
                            </a>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <?php else: ?>
                        <p class="text-sm text-slate-400 italic">Aucune photo avant.</p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div>
                        <p class="text-xs font-bold uppercase text-slate-500 mb-2">Après intervention</p>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($rdv->photos_apres)): ?>
                        <div class="grid grid-cols-2 gap-3">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $rdv->photos_apres; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $photo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <a href="<?php echo e(asset('storage/'.$photo)); ?>" target="_blank">
                                <img src="<?php echo e(asset('storage/'.$photo)); ?>"
                                    class="h-28 w-full rounded-xl object-cover border hover:opacity-90">
                            </a>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <?php else: ?>
                        <p class="text-sm text-slate-400 italic">Aucune photo après.</p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($rdv->terrain_checklist)): ?>
            <div class="rounded-2xl bg-white border p-4">
                <p class="text-sm font-bold text-slate-900 mb-3">✅ Checklist intervention</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $rdv->terrain_checklist; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $checked): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="flex items-center justify-between rounded-xl border p-3 text-sm">
                        <span class="text-slate-700">
                            <?php echo e(ucfirst(str_replace('_', ' ', $key))); ?>

                        </span>

                        <span class="font-bold <?php echo e($checked ? 'text-emerald-600' : 'text-slate-400'); ?>">
                            <?php echo e($checked ? 'Validé' : 'Non validé'); ?>

                        </span>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['title' => 'Aucune mission terminée','message' => 'Votre historique apparaîtra ici après vos premières interventions terminées.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Aucune mission terminée','message' => 'Votre historique apparaîtra ici après vos premières interventions terminées.']); ?>
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
        <?php echo e($historique->links()); ?>

    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal12a5063a2f74bc3084facd42cc51c606)): ?>
<?php $attributes = $__attributesOriginal12a5063a2f74bc3084facd42cc51c606; ?>
<?php unset($__attributesOriginal12a5063a2f74bc3084facd42cc51c606); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal12a5063a2f74bc3084facd42cc51c606)): ?>
<?php $component = $__componentOriginal12a5063a2f74bc3084facd42cc51c606; ?>
<?php unset($__componentOriginal12a5063a2f74bc3084facd42cc51c606); ?>
<?php endif; ?><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/client/historique-client.blade.php ENDPATH**/ ?>