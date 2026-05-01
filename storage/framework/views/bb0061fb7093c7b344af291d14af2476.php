<div class="space-y-6 p-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Coordination chantier</h1>
        <p class="text-sm text-slate-600">Vision chef d’équipe sur les lots multi-jours et la progression des segments terrain.</p>
    </div>

    <div class="space-y-4">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $leadBatches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $batch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <div class="text-xs uppercase tracking-wide text-slate-500"><?php echo e($batch->reference); ?></div>
                        <h2 class="text-lg font-semibold text-slate-900"><?php echo e($batch->name); ?></h2>
                        <p class="text-sm text-slate-600">
                            <?php echo e($batch->organizationAccount->name ?? 'Sans compte'); ?> · <?php echo e($batch->organizationSite->name ?? 'Sans site'); ?>

                        </p>
                    </div>
                    <div class="text-sm text-slate-600">
                        <?php echo e(optional($batch->starts_on)->format('d/m/Y')); ?> → <?php echo e(optional($batch->ends_on)->format('d/m/Y')); ?>

                    </div>
                </div>

                <div class="mt-5 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $batch->days; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $day): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="rounded-xl border border-slate-200 p-4 bg-slate-50">
                            <div class="flex items-center justify-between gap-3">
                                <div class="font-semibold text-slate-900"><?php echo e($day->service_date?->format('d/m/Y')); ?></div>
                                <div class="text-xs rounded-full px-2 py-1 bg-white border border-slate-200 text-slate-600"><?php echo e($day->status); ?></div>
                            </div>
                            <div class="mt-3 text-sm text-slate-600">Segments : <?php echo e($day->segments->count()); ?></div>
                            <div class="mt-2 space-y-2">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $day->segments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $segment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <div class="rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm">
                                        <div class="font-medium text-slate-900"><?php echo e($segment->title); ?></div>
                                        <div class="text-slate-500"><?php echo e($segment->status); ?> · Équipe <?php echo e($segment->crew_size); ?></div>
                                    </div>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-10 text-center text-slate-500">
                Aucun chantier coordonné pour le moment.
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/employe/coordination-chantier.blade.php ENDPATH**/ ?>