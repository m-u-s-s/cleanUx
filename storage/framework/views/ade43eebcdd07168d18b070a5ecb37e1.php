<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-blue-900">🧭 Chef d’équipe opérationnel</h2>
                <p class="text-sm text-gray-500">Répartition des segments, suivi par membre, renfort et clôture globale.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[320px_minmax(0,1fr)] gap-6">
            <div class="bg-white rounded-2xl border shadow-sm p-4 space-y-4">
                <div>
                    <label class="text-sm font-semibold text-slate-700">Lot / chantier</label>
                    <select wire:model.live="selectedBatchId" class="mt-1 w-full rounded-xl border-slate-300">
                        <option value="">Choisir un lot</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $batches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $batch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($batch->id); ?>">#<?php echo e($batch->id); ?> — <?php echo e($batch->name ?? $batch->batch_type); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>
                </div>

                <div>
                    <label class="text-sm font-semibold text-slate-700">Segment</label>
                    <select wire:model.live="selectedSegmentId" class="mt-1 w-full rounded-xl border-slate-300">
                        <option value="">Choisir un segment</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $segments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $segment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($segment->id); ?>"><?php echo e($segment->segment_label ?? ('Segment #'.$segment->id)); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedSegment): ?>
                    <div class="rounded-xl border bg-slate-50 p-3 text-sm">
                        <p class="font-semibold text-slate-800"><?php echo e($selectedSegment->segment_label ?? ('Segment #'.$selectedSegment->id)); ?></p>
                        <p class="text-slate-500"><?php echo e($selectedSegment->segment_date); ?> · <?php echo e($selectedSegment->estimated_minutes); ?> min</p>
                        <p class="text-slate-500">Mission #<?php echo e($selectedSegment->mission_id ?? '—'); ?></p>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div class="space-y-6">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedSegment): ?>
                    <div class="bg-white rounded-2xl border shadow-sm p-5 space-y-4">
                        <h3 class="text-lg font-bold text-slate-900">Affectation fine des segments</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-semibold text-slate-700">Membre</label>
                                <select wire:model="selectedAssigneeId" class="mt-1 w-full rounded-xl border-slate-300">
                                    <option value="">Choisir un membre</option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ($selectedSegment->fieldTeam->members ?? collect()); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $member): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($member->user_id); ?>"><?php echo e($member->user->name ?? ('User #'.$member->user_id)); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <button wire:click="assignSelectedSegment" class="w-full inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 transition">
                                    Affecter le segment
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border shadow-sm p-5 space-y-4">
                        <h3 class="text-lg font-bold text-slate-900">Statut membre par membre</h3>
                        <div class="space-y-3">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $selectedSegment->assignments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $assignment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <div class="rounded-xl border p-4 space-y-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="font-semibold text-slate-900"><?php echo e($assignment->user->name ?? ('User #'.$assignment->user_id)); ?></p>
                                            <p class="text-sm text-slate-500"><?php echo e($assignment->assignment_role); ?> · <?php echo e($assignment->status); ?></p>
                                        </div>
                                        <button wire:click="updateSelectedMemberStatus(<?php echo e($assignment->id); ?>)" class="inline-flex items-center rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700 transition">
                                            Mettre à jour
                                        </button>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                        <input wire:model="progressPercent" type="number" min="0" max="100" class="rounded-xl border-slate-300" placeholder="Progression %">
                                        <input wire:model="minutesSpent" type="number" min="0" class="rounded-xl border-slate-300" placeholder="Minutes passées">
                                        <input wire:model="blockingReason" type="text" class="rounded-xl border-slate-300" placeholder="Blocage éventuel">
                                    </div>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <p class="text-sm text-slate-500">Aucune affectation membre sur ce segment.</p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border shadow-sm p-5 space-y-4">
                        <h3 class="text-lg font-bold text-slate-900">Demande de renfort</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <input wire:model="requestedMembers" type="number" min="1" class="rounded-xl border-slate-300" placeholder="Membres demandés">
                            <input wire:model="requestedMinutes" type="number" min="15" class="rounded-xl border-slate-300" placeholder="Minutes estimées">
                            <select wire:model="reinforcementPriority" class="rounded-xl border-slate-300">
                                <option value="normale">Normale</option>
                                <option value="haute">Haute</option>
                                <option value="urgente">Urgente</option>
                            </select>
                        </div>
                        <textarea wire:model="reinforcementReason" rows="3" class="w-full rounded-xl border-slate-300" placeholder="Pourquoi un renfort est nécessaire ?"></textarea>
                        <button wire:click="requestReinforcement" class="inline-flex items-center rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-600 transition">
                            Envoyer la demande de renfort
                        </button>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-2xl border border-dashed p-8 text-center text-slate-500">
                        Sélectionnez un lot puis un segment pour ouvrir le cockpit chef d’équipe.
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <div class="bg-white rounded-2xl border shadow-sm p-5 space-y-4">
                    <h3 class="text-lg font-bold text-slate-900">Demandes de renfort récentes</h3>
                    <div class="space-y-3">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $reinforcementRequests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $request): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <div class="rounded-xl border p-4">
                                <p class="font-semibold text-slate-900"><?php echo e($request->priority); ?> · <?php echo e($request->status); ?></p>
                                <p class="text-sm text-slate-500"><?php echo e($request->reason); ?></p>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <p class="text-sm text-slate-500">Aucune demande récente.</p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/employe/team-lead-operations-center.blade.php ENDPATH**/ ?>