<div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">Analytics</p>
            <h3 class="text-xl font-black text-slate-900">Performance métier</h3>
            <p class="text-sm text-slate-500">Services, villes, durées et performance employés.</p>
        </div>

        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">
            Données opérationnelles
        </span>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
        <div class="rounded-2xl border bg-slate-50 p-4">
            <p class="text-sm text-slate-500">Feedback reçu</p>
            <p class="mt-2 text-3xl font-black text-blue-700"><?php echo e($feedbackRate ?? 0); ?>%</p>
        </div>

        <div class="rounded-2xl border bg-slate-50 p-4">
            <p class="text-sm text-slate-500">Durée estimée</p>
            <p class="mt-2 text-2xl font-black text-slate-900">
                <?php echo e($dureeStats['avg_estimated'] ? $dureeStats['avg_estimated'] . ' min' : '—'); ?>

            </p>
        </div>

        <div class="rounded-2xl border bg-slate-50 p-4">
            <p class="text-sm text-slate-500">Durée réelle</p>
            <p class="mt-2 text-2xl font-black text-slate-900">
                <?php echo e($dureeStats['avg_real'] ? $dureeStats['avg_real'] . ' min' : '—'); ?>

            </p>
        </div>

        <div class="rounded-2xl border bg-slate-50 p-4">
            <p class="text-sm text-slate-500">Écart moyen</p>
            <p class="mt-2 text-2xl font-black <?php echo e(($dureeStats['avg_gap'] ?? 0) > 0 ? 'text-red-600' : 'text-emerald-700'); ?>">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!is_null($dureeStats['avg_gap'])): ?>
                    <?php echo e($dureeStats['avg_gap'] > 0 ? '+' : ''); ?><?php echo e($dureeStats['avg_gap']); ?> min
                <?php else: ?>
                    —
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
            <h4 class="mb-4 font-black text-slate-900">🧼 Services les plus demandés</h4>

            <div class="space-y-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $topServices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="flex items-center justify-between rounded-xl bg-white p-3 shadow-sm">
                        <span class="text-sm font-semibold text-slate-700">
                            <?php echo e(ucfirst(str_replace('_', ' ', $service->label ?? 'Service'))); ?>

                        </span>
                        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-black text-blue-700">
                            <?php echo e($service->total); ?>

                        </span>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['title' => 'Aucun service','message' => 'Les services les plus demandés apparaîtront ici.','icon' => '🧼']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Aucun service','message' => 'Les services les plus demandés apparaîtront ici.','icon' => '🧼']); ?>
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

        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
            <h4 class="mb-4 font-black text-slate-900">📍 Villes les plus demandées</h4>

            <div class="space-y-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $topVilles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ville): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="flex items-center justify-between rounded-xl bg-white p-3 shadow-sm">
                        <span class="text-sm font-semibold text-slate-700"><?php echo e($ville->ville); ?></span>
                        <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-black text-indigo-700">
                            <?php echo e($ville->total); ?>

                        </span>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['title' => 'Aucune ville','message' => 'Les villes les plus demandées apparaîtront ici.','icon' => '📍']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Aucune ville','message' => 'Les villes les plus demandées apparaîtront ici.','icon' => '📍']); ?>
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

        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
            <h4 class="mb-4 font-black text-slate-900">🧑‍💼 Performance employés</h4>

            <div class="space-y-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $performanceEmployes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="rounded-xl bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-black text-slate-900"><?php echo e($item['employe']->name); ?></p>
                                <p class="mt-1 text-sm text-slate-500">
                                    <?php echo e($item['missions_terminees']); ?> mission(s) terminée(s)
                                </p>
                            </div>

                            <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-black text-emerald-700">
                                <?php echo e(!is_null($item['avg_note']) ? $item['avg_note'] . '/5' : '—'); ?>

                            </span>
                        </div>

                        <p class="mt-3 text-sm text-slate-600">
                            Écart moyen :
                            <strong>
                                <?php echo e(!is_null($item['avg_gap']) ? ($item['avg_gap'] > 0 ? '+' : '') . $item['avg_gap'] . ' min' : '—'); ?>

                            </strong>
                        </p>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['title' => 'Aucune performance','message' => 'Les performances employés apparaîtront ici.','icon' => '🧑‍💼']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Aucune performance','message' => 'Les performances employés apparaîtront ici.','icon' => '🧑‍💼']); ?>
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
</div><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/admin/dashboard/analytics-panel.blade.php ENDPATH**/ ?>