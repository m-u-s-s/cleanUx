<div class="space-y-4">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-7">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $jours; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $jour): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <section class="flex min-h-[18rem] flex-col rounded-[1.75rem] border p-4 shadow-sm <?php echo e($jour['is_focus'] ? 'border-sky-300 bg-sky-50/50' : 'border-slate-200 bg-slate-50/70'); ?> <?php echo e($jour['is_today'] ? 'ring-2 ring-indigo-100' : ''); ?>">
                <div class="mb-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-black <?php echo e($jour['is_focus'] ? 'text-sky-900' : 'text-slate-900'); ?>">
                                <?php echo e($jour['short_label']); ?>

                            </p>
                            <p class="text-xs text-slate-500">
                                <?php echo e($jour['rdvs']->count()); ?> intervention(s) • <?php echo e($jour['total_hours']); ?> h
                            </p>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($jour['is_today']): ?>
                            <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-[11px] font-black text-indigo-700">Aujourd’hui</span>
                        <?php elseif($jour['is_focus']): ?>
                            <span class="rounded-full bg-sky-100 px-2.5 py-1 text-[11px] font-black text-sky-700">Focus</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2 text-[11px] font-bold">
                        <span class="rounded-full bg-white px-2.5 py-1 text-slate-600">
                            <?php echo e($jour['active_count']); ?> actives
                        </span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($jour['urgent_count'] > 0): ?>
                            <span class="rounded-full bg-rose-100 px-2.5 py-1 text-rose-700">
                                <?php echo e($jour['urgent_count']); ?> urgente(s)
                            </span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($jour['unassigned_count'] > 0): ?>
                            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-amber-700">
                                <?php echo e($jour['unassigned_count']); ?> sans employé
                            </span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>

                <div class="flex-1 space-y-3">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $jour['rdvs']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rdv): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php if (isset($component)) { $__componentOriginal4d75d367143f601da4a137bc504e2da6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal4d75d367143f601da4a137bc504e2da6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.rdv-planning-card','data' => ['rdv' => $rdv]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('rdv-planning-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['rdv' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($rdv)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal4d75d367143f601da4a137bc504e2da6)): ?>
<?php $attributes = $__attributesOriginal4d75d367143f601da4a137bc504e2da6; ?>
<?php unset($__attributesOriginal4d75d367143f601da4a137bc504e2da6); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal4d75d367143f601da4a137bc504e2da6)): ?>
<?php $component = $__componentOriginal4d75d367143f601da4a137bc504e2da6; ?>
<?php unset($__componentOriginal4d75d367143f601da4a137bc504e2da6); ?>
<?php endif; ?>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <div class="flex h-full items-center justify-center rounded-3xl border border-dashed border-slate-300 bg-white/70 p-4 text-center text-sm text-slate-500">
                            Aucune intervention sur ce créneau.
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </section>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/admin/agenda-hebdomadaire.blade.php ENDPATH**/ ?>