<div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Aujourd’hui</p>
                <h3 class="text-xl font-black text-slate-900">Interventions du jour</h3>
                <p class="text-sm text-slate-500">Planning opérationnel immédiat.</p>
            </div>

            <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-black text-blue-700">
                <?php echo e($interventionsDuJour->count() ?? 0); ?> RDV
            </span>
        </div>

        <div class="space-y-4">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $interventionsDuJour; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rdv): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="font-black text-slate-900">
                                <?php echo e($rdv->client->name ?? 'Client'); ?>

                            </p>

                            <p class="mt-1 text-sm text-slate-600">
                                <?php echo e($rdv->service_display_name ?: 'Service non précisé'); ?>

                            </p>

                            <p class="mt-2 text-sm text-slate-500">
                                📅 <?php echo e($rdv->date?->format('d/m/Y') ?? $rdv->date); ?>

                                · 🕒 <?php echo e(substr((string) $rdv->heure, 0, 5)); ?>

                            </p>

                            <p class="mt-1 text-sm text-slate-500">
                                📍 <?php echo e($rdv->ville ?? 'Ville inconnue'); ?>

                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
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

                    <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-200 pt-4">
                        <button wire:click="ouvrirMission(<?php echo e($rdv->id); ?>)"
                            class="rounded-xl bg-blue-600 px-3 py-2 text-xs font-bold text-white hover:bg-blue-700">
                            👁️ Voir détail
                        </button>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($rdv->status, ['en_attente', 'confirme', 'en_route', 'sur_place'])): ?>
                            <button wire:click="ouvrirPlanning(<?php echo e($rdv->id); ?>)"
                                class="rounded-xl bg-amber-50 px-3 py-2 text-xs font-bold text-amber-700 ring-1 ring-amber-200 hover:bg-amber-100">
                                🗓️ Replanifier
                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['title' => 'Aucune intervention aujourd’hui','message' => 'Le planning du jour est vide.','icon' => '📆']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Aucune intervention aujourd’hui','message' => 'Le planning du jour est vide.','icon' => '📆']); ?>
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
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">Charge terrain</p>
                <h3 class="text-xl font-black text-slate-900">Charge des employés</h3>
                <p class="text-sm text-slate-500">Vue rapide des surcharges du jour.</p>
            </div>
        </div>

        <div class="space-y-3">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $chargeEmployes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <?php
                    $minutes = $item['minutes'] ?? 0;
                    $tone = $minutes >= 480 ? 'red' : ($minutes >= 300 ? 'amber' : 'emerald');
                    $label = $minutes >= 480 ? 'Surchargé' : ($minutes >= 300 ? 'Chargé' : 'OK');
                    $barWidth = min(100, round(($minutes / 480) * 100));
                ?>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="font-black text-slate-900"><?php echo e($item['employe']->name); ?></p>
                            <p class="text-sm text-slate-500">
                                <?php echo e($item['count']); ?> intervention(s) · <?php echo e($minutes); ?> min · <?php echo e($item['hours']); ?> h
                            </p>
                        </div>

                        <span class="rounded-full px-3 py-1 text-xs font-black
                            <?php echo e($tone === 'red' ? 'bg-red-50 text-red-700 ring-1 ring-red-200' : ''); ?>

                            <?php echo e($tone === 'amber' ? 'bg-amber-50 text-amber-700 ring-1 ring-amber-200' : ''); ?>

                            <?php echo e($tone === 'emerald' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : ''); ?>">
                            <?php echo e($label); ?>

                        </span>
                    </div>

                    <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-200">
                        <div class="h-full rounded-full
                            <?php echo e($tone === 'red' ? 'bg-red-500' : ''); ?>

                            <?php echo e($tone === 'amber' ? 'bg-amber-500' : ''); ?>

                            <?php echo e($tone === 'emerald' ? 'bg-emerald-500' : ''); ?>"
                            style="width: <?php echo e($barWidth); ?>%">
                        </div>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['title' => 'Aucun employé trouvé','message' => 'Les charges employés apparaîtront ici.','icon' => '👥']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Aucun employé trouvé','message' => 'Les charges employés apparaîtront ici.','icon' => '👥']); ?>
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
    </div>
</div>

<div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div class="rounded-3xl border border-red-200 bg-white p-6 shadow-sm">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-red-600">Priorité</p>
                <h3 class="text-xl font-black text-slate-900">Interventions urgentes</h3>
                <p class="text-sm text-slate-500">Demandes à traiter rapidement.</p>
            </div>

            <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-black text-red-700">
                <?php echo e($urgences->count() ?? 0); ?> urgence(s)
            </span>
        </div>

        <div class="space-y-4">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $urgences; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rdv): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <div class="rounded-2xl border border-red-100 bg-red-50/60 p-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="font-black text-slate-900"><?php echo e($rdv->client->name ?? 'Client'); ?></p>
                            <p class="mt-1 text-sm text-slate-600"><?php echo e($rdv->service_display_name ?: 'Service non précisé'); ?></p>
                            <p class="mt-2 text-sm text-slate-500">
                                📅 <?php echo e($rdv->date?->format('d/m/Y') ?? $rdv->date); ?> · 🕒 <?php echo e(substr((string) $rdv->heure, 0, 5)); ?>

                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
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

                    <div class="mt-4 flex flex-wrap gap-2 border-t border-red-100 pt-4">
                        <button wire:click="ouvrirMission(<?php echo e($rdv->id); ?>)"
                            class="rounded-xl bg-red-600 px-3 py-2 text-xs font-bold text-white hover:bg-red-700">
                            👁️ Voir détail
                        </button>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($rdv->status, ['en_attente', 'confirme', 'en_route', 'sur_place'])): ?>
                            <button wire:click="ouvrirPlanning(<?php echo e($rdv->id); ?>)"
                                class="rounded-xl bg-white px-3 py-2 text-xs font-bold text-red-700 ring-1 ring-red-200 hover:bg-red-50">
                                🗓️ Replanifier
                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['title' => 'Aucune urgence','message' => 'Aucune intervention urgente pour le moment.','icon' => '🚨']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Aucune urgence','message' => 'Aucune intervention urgente pour le moment.','icon' => '🚨']); ?>
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
    </div>

    <div class="rounded-3xl border border-emerald-200 bg-white p-6 shadow-sm">
        <div class="mb-5 flex items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-600">Qualité</p>
                <h3 class="text-xl font-black text-slate-900">Missions terminées</h3>
                <p class="text-sm text-slate-500">Dernières interventions clôturées.</p>
            </div>
        </div>

        <div class="space-y-4">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $missionsTerminees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rdv): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <div class="rounded-2xl border border-emerald-100 bg-emerald-50/60 p-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="font-black text-slate-900"><?php echo e($rdv->client->name ?? 'Client'); ?></p>
                            <p class="mt-1 text-sm text-slate-600"><?php echo e($rdv->service_display_name ?: 'Service non précisé'); ?></p>
                            <p class="mt-2 text-sm text-slate-500">
                                ✅ Terminée · <?php echo e($rdv->date?->format('d/m/Y') ?? $rdv->date); ?>

                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
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

                    <div class="mt-4 border-t border-emerald-100 pt-4">
                        <button wire:click="ouvrirMission(<?php echo e($rdv->id); ?>)"
                            class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700">
                            👁️ Voir détail
                        </button>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['title' => 'Aucune mission terminée','message' => 'Les missions clôturées apparaîtront ici.','icon' => '✅']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Aucune mission terminée','message' => 'Les missions clôturées apparaîtront ici.','icon' => '✅']); ?>
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
    </div>
</div><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/admin/dashboard/operations-overview.blade.php ENDPATH**/ ?>