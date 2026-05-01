<x-page-shell
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="rounded-2xl border bg-white p-4 shadow-sm">
        <p class="text-xs font-bold uppercase text-slate-500">Ouverts</p>
        <p class="text-2xl font-black text-amber-600">
            <?php echo e($claims->where('status', 'open')->count()); ?>

        </p>
    </div>

    <div class="rounded-2xl border bg-white p-4 shadow-sm">
        <p class="text-xs font-bold uppercase text-slate-500">En traitement</p>
        <p class="text-2xl font-black text-blue-600">
            <?php echo e($claims->where('status', 'in_review')->count()); ?>

        </p>
    </div>

    <div class="rounded-2xl border bg-white p-4 shadow-sm">
        <p class="text-xs font-bold uppercase text-slate-500">Résolus</p>
        <p class="text-2xl font-black text-emerald-600">
            <?php echo e($claims->where('status', 'resolved')->count()); ?>

        </p>
    </div>

    <div class="rounded-2xl border bg-white p-4 shadow-sm">
        <p class="text-xs font-bold uppercase text-slate-500">Urgents</p>
        <p class="text-2xl font-black text-red-600">
            <?php echo e($claims->where('priority', 'urgent')->count()); ?>

        </p>
    </div>
    </div>



    title="⚠️ Centre de litiges"
    subtitle="Signalez un problème, ajoutez des preuves et suivez le traitement de votre demande.">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 rounded-2xl border bg-white p-6 shadow-sm space-y-4">
            <div>
                <h3 class="text-lg font-black text-slate-900">Créer une réclamation</h3>
                <p class="text-sm text-slate-500">
                    Ajoutez un maximum de détails pour que le support puisse traiter rapidement votre demande.
                </p>
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Rendez-vous concerné</label>
                <select wire:model="rendez_vous_id" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    <option value="">— Aucun / général —</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $rendezVous; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rdv): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($rdv->id); ?>">
                        <?php echo e($rdv->date?->format('d/m/Y')); ?> — <?php echo e($rdv->service_display_name); ?>

                    </option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </select>
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Catégorie</label>
                <select wire:model="category" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    <option value="quality">Qualité du nettoyage</option>
                    <option value="delay">Retard</option>
                    <option value="damage">Dégât / dommage</option>
                    <option value="billing">Facturation</option>
                    <option value="employee_behavior">Comportement employé</option>
                    <option value="missing_service">Service non réalisé</option>
                </select>
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Priorité</label>
                <select wire:model="priority" class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                    <option value="low">Basse</option>
                    <option value="normal">Normale</option>
                    <option value="high">Haute</option>
                    <option value="urgent">Urgente</option>
                </select>
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Titre</label>
                <input
                    type="text"
                    wire:model="title"
                    class="mt-1 w-full rounded-xl border-gray-300 text-sm"
                    placeholder="Ex : Nettoyage incomplet">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['title'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-red-600 mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Description</label>
                <textarea
                    wire:model="description"
                    rows="5"
                    class="mt-1 w-full rounded-xl border-gray-300 text-sm"
                    placeholder="Expliquez le problème avec le plus de détails possible..."></textarea>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['description'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-red-600 mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Preuves photo</label>
                <input
                    type="file"
                    wire:model="photos"
                    multiple
                    accept="image/*"
                    class="mt-1 w-full text-sm">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['photos.*'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-red-600 mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <button
                wire:click="createClaim"
                wire:loading.attr="disabled"
                class="w-full rounded-xl bg-red-600 px-4 py-3 text-sm font-bold text-white hover:bg-red-700 disabled:opacity-60">
                <span wire:loading.remove>Envoyer la réclamation</span>
                <span wire:loading>Envoi en cours...</span>
            </button>
        </div>

        <div class="lg:col-span-2 space-y-4">
            <div class="rounded-2xl border bg-white p-4 shadow-sm flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-slate-900">Mes litiges</h3>
                    <p class="text-sm text-slate-500">Suivi du statut et du délai de réponse.</p>
                </div>

                <select wire:model.live="filterStatus" class="rounded-xl border-gray-300 text-sm">
                    <option value="">Tous les statuts</option>
                    <option value="open">Ouvert</option>
                    <option value="in_review">En traitement</option>
                    <option value="waiting_client">En attente client</option>
                    <option value="resolved">Résolu</option>
                    <option value="closed">Clôturé</option>
                </select>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $claims; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $claim): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="rounded-2xl border bg-white p-5 shadow-sm space-y-4">
                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                    <div>
                        <h4 class="font-semibold text-slate-900">
                            <?php echo e($claim->title); ?>

                        </h4>

                        <p class="text-sm text-slate-500">
                            <?php echo e($claim->category_label); ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($claim->rendezVous): ?>
                            — RDV du <?php echo e($claim->rendezVous->date?->format('d/m/Y')); ?>

                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <span class="rounded-full px-3 py-1 text-xs font-medium
                                <?php echo e($claim->status === 'resolved'
                                    ? 'bg-emerald-100 text-emerald-700'
                                    : 'bg-amber-100 text-amber-700'); ?>">
                            <?php echo e($claim->status_label); ?>

                        </span>

                        <span class="rounded-full px-3 py-1 text-xs font-medium
                                <?php echo e(in_array($claim->priority, ['high', 'urgent'])
                                    ? 'bg-red-100 text-red-700'
                                    : 'bg-slate-100 text-slate-700'); ?>">
                            <?php echo e(ucfirst($claim->priority)); ?>

                        </span>
                    </div>
                </div>

                <p class="text-sm text-slate-700">
                    <?php echo e($claim->description); ?>

                </p>

                <div class="rounded-2xl border bg-slate-50 p-4">
                    <p class="text-sm font-bold text-slate-900 mb-3">Suivi du dossier</p>

                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="rounded-full px-3 py-1 font-semibold <?php echo e(in_array($claim->status, ['open','in_review','waiting_client','resolved','closed']) ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-500'); ?>">
                            Ouvert
                        </span>

                        <span class="rounded-full px-3 py-1 font-semibold <?php echo e(in_array($claim->status, ['in_review','waiting_client','resolved','closed']) ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-500'); ?>">
                            Analyse support
                        </span>

                        <span class="rounded-full px-3 py-1 font-semibold <?php echo e(in_array($claim->status, ['waiting_client']) ? 'bg-purple-100 text-purple-700' : 'bg-slate-100 text-slate-500'); ?>">
                            Attente client
                        </span>

                        <span class="rounded-full px-3 py-1 font-semibold <?php echo e(in_array($claim->status, ['resolved','closed']) ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'); ?>">
                            Résolu
                        </span>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                    <div class="rounded-xl bg-slate-50 border p-3">
                        <p class="text-slate-500">Créé le</p>
                        <p class="font-medium text-slate-900">
                            <?php echo e($claim->created_at?->format('d/m/Y H:i')); ?>

                        </p>
                    </div>

                    <div class="rounded-xl bg-slate-50 border p-3">
                        <p class="text-slate-500">Réponse attendue</p>

                        <p class="font-medium <?php echo e($claim->sla_due_at && $claim->sla_due_at->isPast() && !in_array($claim->status, ['resolved', 'closed']) ? 'text-red-700' : 'text-slate-900'); ?>">
                            <?php echo e($claim->sla_due_at?->format('d/m/Y H:i') ?? '—'); ?>

                        </p>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($claim->sla_due_at && $claim->sla_due_at->isPast() && !in_array($claim->status, ['resolved', 'closed'])): ?>
                        <p class="text-xs text-red-600 font-semibold mt-1">
                            Délai dépassé
                        </p>
                        <?php else: ?>
                        <p class="text-xs text-slate-500 mt-1">
                            Délai de traitement prévu
                        </p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div class="rounded-xl bg-slate-50 border p-3">
                        <p class="text-slate-500">Résolu le</p>
                        <p class="font-medium text-slate-900">
                            <?php echo e($claim->resolved_at?->format('d/m/Y H:i') ?? '—'); ?>

                        </p>
                    </div>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(is_array($claim->attachments) && count($claim->attachments)): ?>
                <div class="space-y-2">
                    <p class="text-sm font-medium text-slate-800">Preuves ajoutées</p>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $claim->attachments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attachment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <a
                            href="<?php echo e(asset('storage/'.$attachment['path'])); ?>"
                            target="_blank"
                            class="block rounded-xl overflow-hidden border bg-slate-50">
                            <img
                                src="<?php echo e(asset('storage/'.$attachment['path'])); ?>"
                                class="h-24 w-full object-cover"
                                alt="Preuve litige">
                        </a>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['title' => 'Aucun litige','message' => 'Vous n’avez pas encore signalé de problème.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Aucun litige','message' => 'Vous n’avez pas encore signalé de problème.']); ?>
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

            <div>
                <?php echo e($claims->links()); ?>

            </div>
        </div>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal)): ?>
<?php $attributes = $__attributesOriginal; ?>
<?php unset($__attributesOriginal); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal)): ?>
<?php $component = $__componentOriginal; ?>
<?php unset($__componentOriginal); ?>
<?php endif; ?><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/client/litiges-client.blade.php ENDPATH**/ ?>