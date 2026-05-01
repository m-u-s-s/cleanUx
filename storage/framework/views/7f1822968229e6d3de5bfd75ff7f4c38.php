<div class="p-4 md:p-6 space-y-6">
    <div>
        <div class="cu-hero">
            <div class="relative cu-toolbar gap-4">
                <div class="max-w-3xl">
                    <span class="cu-eyebrow">Pilotage opérationnel</span>
                    <h2 class="mt-3 text-2xl font-black tracking-tight text-slate-900 md:text-3xl">
                        Centre missions
                    </h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 md:text-base">
                        Vue consolidée des missions, des urgences, des non-assignées et de la charge opérationnelle.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow border p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <input type="text" wire:model.live="search" placeholder="Service, client, employé, ville..."
                class="w-full border-gray-300 rounded-lg shadow-sm">

            <select wire:model.live="filtreEmploye" class="w-full border-gray-300 rounded-lg shadow-sm">
                <option value="">— Tous les employés —</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $employes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $employe): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($employe->id); ?>"><?php echo e($employe->name); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </select>

            <select wire:model.live="filtreStatus" class="w-full border-gray-300 rounded-lg shadow-sm">
                <option value="">— Tous les statuts —</option>
                <option value="en_attente">En attente</option>
                <option value="confirme">Confirmé</option>
                <option value="en_route">En route</option>
                <option value="sur_place">Sur place</option>
                <option value="termine">Terminé</option>
                <option value="refuse">Refusé</option>
            </select>

            <select wire:model.live="filtrePriorite" class="w-full border-gray-300 rounded-lg shadow-sm">
                <option value="">— Toutes les priorités —</option>
                <option value="normale">Normale</option>
                <option value="haute">Haute</option>
                <option value="urgente">Urgente</option>
            </select>


        </div>
    </div>

    <div class="space-y-4">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $missions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rdv): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <div class="bg-white border rounded-2xl shadow-sm p-4">
            <div class="flex flex-col md:flex-row md:justify-between gap-3">
                <div>
                    <p class="font-semibold text-slate-900 text-lg">
                        <?php echo e($rdv->service_display_name); ?>

                    </p>
                    <p class="text-sm text-gray-600">
                        📅 <?php echo e($rdv->date); ?> à <?php echo e($rdv->heure); ?>

                    </p>
                    <p class="text-sm text-gray-600">
                        👤 <?php echo e($rdv->client->name ?? '—'); ?> • 🧑‍💼 <?php echo e($rdv->employe->name ?? '—'); ?>

                    </p>
                    <p class="text-sm text-gray-600">
                        📍 <?php echo e($rdv->adresse ?? '—'); ?>, <?php echo e($rdv->ville ?? '—'); ?>

                    </p>
                </div>

                <div class="flex items-start gap-2">
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

                <button
                    type="button"
                    wire:click="dispatchRendezVous(<?php echo e($rdv->id); ?>)"
                    class="rounded-xl bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    ⚡ Dispatch auto
                </button>
                <button
                    type="button"
                    wire:click="previewDispatch(<?php echo e($rdv->id); ?>)"
                    class="rounded-xl border px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    👀 Voir scoring
                </button>


                <!-- modal -->
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($dispatchPreviewRdvId): ?>
                <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 px-4">
                    <div class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl space-y-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">Scoring dispatch</h3>
                                <p class="text-sm text-slate-500">Classement des employés disponibles.</p>
                            </div>

                            <button wire:click="closeDispatchPreview" class="text-slate-500 hover:text-slate-800">
                                ✕
                            </button>
                        </div>

                        <div class="space-y-2">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_2 = true; $__currentLoopData = $dispatchPreview; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
                            <div class="flex items-center justify-between rounded-xl border p-3">
                                <div>
                                    <p class="font-medium text-slate-900"><?php echo e($row['name']); ?></p>
                                    <p class="text-xs <?php echo e($row['available'] ? 'text-emerald-600' : 'text-red-600'); ?>">
                                        <?php echo e($row['available'] ? 'Disponible' : 'Indisponible'); ?>

                                    </p>
                                </div>

                                <div class="text-right">
                                    <p class="text-xl font-bold text-indigo-700"><?php echo e($row['score']); ?></p>
                                    <p class="text-xs text-slate-500">score</p>
                                </div>
                            </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
                            <p class="text-sm text-slate-500">Aucun employé trouvé.</p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            </div>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <div class="bg-white border rounded-xl p-6 text-center text-gray-500 italic">
            Aucune mission trouvée.
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <div>
        <?php echo e($missions->links()); ?>

    </div>
</div><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/admin/missions-admin.blade.php ENDPATH**/ ?>