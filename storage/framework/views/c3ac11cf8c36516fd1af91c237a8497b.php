<div class="space-y-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Automatisation missions & charge</h1>
            <p class="text-sm text-slate-600">Mesure la charge réelle équipe/partenaire et génère automatiquement les missions depuis les ordres de service et les lots.</p>
        </div>

        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">Date</label>
                <input type="date" wire:model.live="selectedDate" class="mt-1 rounded-xl border-slate-300 text-sm" />
            </div>
            <button wire:click="refreshSnapshots" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Recalculer la charge</button>
            <button wire:click="runPending" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Générer les missions approuvées</button>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Ordres de service approuvés</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500">
                            <th class="pb-3">Réf</th>
                            <th class="pb-3">Compte</th>
                            <th class="pb-3">Site</th>
                            <th class="pb-3">Génération</th>
                            <th class="pb-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $approvedPendingWorkOrders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $workOrder): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr>
                                <td class="py-3 font-medium text-slate-900"><?php echo e($workOrder->reference); ?></td>
                                <td class="py-3"><?php echo e($workOrder->organizationAccount->name ?? '—'); ?></td>
                                <td class="py-3"><?php echo e($workOrder->organizationSite->name ?? '—'); ?></td>
                                <td class="py-3">
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700"><?php echo e($workOrder->generation_status ?? 'pending'); ?></span>
                                </td>
                                <td class="py-3 text-right">
                                    <button wire:click="generateFromWorkOrder(<?php echo e($workOrder->id); ?>)" class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white">Générer</button>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr><td colspan="5" class="py-6 text-center text-slate-500">Aucun ordre approuvé à générer.</td></tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Lots récents</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500">
                            <th class="pb-3">Lot</th>
                            <th class="pb-3">Compte</th>
                            <th class="pb-3">Statut</th>
                            <th class="pb-3">Missions</th>
                            <th class="pb-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $recentBatches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $batch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr>
                                <td class="py-3 font-medium text-slate-900"><?php echo e($batch->reference ?? $batch->batch_code); ?></td>
                                <td class="py-3"><?php echo e($batch->organizationAccount->name ?? '—'); ?></td>
                                <td class="py-3"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700"><?php echo e($batch->generation_status ?? $batch->status); ?></span></td>
                                <td class="py-3"><?php echo e($batch->generated_missions_count ?? 0); ?></td>
                                <td class="py-3 text-right">
                                    <button wire:click="materializeBatch(<?php echo e($batch->id); ?>)" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white">Matérialiser</button>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr><td colspan="5" class="py-6 text-center text-slate-500">Aucun lot récent.</td></tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Charge équipes</h2>
            <div class="mt-4 space-y-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $fieldTeamSnapshots; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $snapshot): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="rounded-xl border border-slate-100 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-900"><?php echo e($snapshot->fieldTeam->name ?? 'Équipe'); ?></p>
                                <p class="text-xs text-slate-500"><?php echo e($snapshot->planned_segments_count); ?> segments • <?php echo e($snapshot->planned_minutes); ?> min • <?php echo e($snapshot->assigned_members_count); ?> membres</p>
                            </div>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700"><?php echo e(number_format((float) $snapshot->utilization_percent, 1, ',', ' ')); ?>%</span>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <p class="text-sm text-slate-500">Aucun snapshot équipe pour cette date.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">Charge partenaires</h2>
            <div class="mt-4 space-y-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $partnerSnapshots; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $snapshot): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="rounded-xl border border-slate-100 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-900"><?php echo e($snapshot->servicePartner->name ?? 'Partenaire'); ?></p>
                                <p class="text-xs text-slate-500"><?php echo e($snapshot->planned_segments_count); ?> segments • <?php echo e($snapshot->planned_minutes); ?> min • capacité <?php echo e($snapshot->daily_capacity); ?> min</p>
                            </div>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700"><?php echo e(number_format((float) $snapshot->utilization_percent, 1, ',', ' ')); ?>%</span>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <p class="text-sm text-slate-500">Aucun snapshot partenaire pour cette date.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </section>
    </div>
</div>
<?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/admin/automation-mission-generation-center.blade.php ENDPATH**/ ?>