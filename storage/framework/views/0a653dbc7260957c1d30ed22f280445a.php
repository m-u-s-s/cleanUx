<div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="mb-5 flex items-center justify-between gap-3">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">Audit</p>
            <h3 class="text-xl font-black text-slate-900">Journal d’activité admin</h3>
            <p class="text-sm text-slate-500">
                Dernières actions sensibles ou automatiques.
            </p>
        </div>

        <a href="<?php echo e(route('admin.audit.logs')); ?>"
           class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white hover:bg-slate-700">
            Voir tout
        </a>
    </div>

    <div class="space-y-3">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $recentActivityLogs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php
                $actionLabel = match($log->action) {
                    'mission_replanifiee' => 'Mission replanifiée',
                    'mission_statut_modifie' => 'Statut de mission modifié',
                    'mission_terminee_avec_rapport' => 'Mission terminée avec rapport',
                    'rdv_modifie_par_client' => 'Rendez-vous modifié par le client',
                    'rdv_annule_par_client' => 'Rendez-vous annulé par le client',
                    'feedback_repondu_par_admin' => 'Réponse admin à un feedback',
                    'export_rendez_vous' => 'Export des rendez-vous',
                    'export_feedbacks' => 'Export des feedbacks',
                    'import_csv_execute' => 'Import CSV exécuté',
                    'import_csv_avec_erreurs' => 'Import CSV avec erreurs',
                    'rappel_24h_envoye' => 'Rappel 24h envoyé',
                    'rappel_2h_envoye' => 'Rappel 2h envoyé',
                    'demande_feedback_envoyee' => 'Demande de feedback envoyée',
                    'alerte_urgence_envoyee' => 'Alerte urgence envoyée',
                    'alerte_depassement_durees' => 'Alerte sur dépassements de durée',
                    'alerte_taux_feedback_faible' => 'Alerte taux de feedback faible',
                    'suggestion_reaffectation_auto' => 'Suggestion automatique de réaffectation',
                    default => ucfirst(str_replace('_', ' ', $log->action)),
                };

                $isCritical = str_contains($log->action, 'export')
                    || str_contains($log->action, 'delete')
                    || str_contains($log->action, 'supprime')
                    || str_contains($log->action, 'security')
                    || str_contains($log->action, 'incident');
            ?>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="font-black text-slate-900">
                            <?php echo e($isCritical ? '🔐' : '🕓'); ?> <?php echo e($actionLabel); ?>

                        </p>

                        <p class="mt-1 text-sm text-slate-500">
                            Par <?php echo e($log->user->name ?? 'Système automatique'); ?>

                            · <?php echo e($log->created_at?->diffForHumans()); ?>

                        </p>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($log->target_id): ?>
                        <span class="rounded-full bg-white px-3 py-1 text-xs font-black text-slate-600 ring-1 ring-slate-200">
                            #<?php echo e($log->target_id); ?>

                        </span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($log->meta)): ?>
                    <details class="mt-3">
                        <summary class="cursor-pointer text-xs font-bold text-blue-700">
                            Voir les détails
                        </summary>

                        <div class="mt-3 grid grid-cols-1 gap-2 text-xs">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $log->meta; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <div class="rounded-xl border bg-white p-3">
                                    <span class="font-black text-slate-700">
                                        <?php echo e(ucfirst(str_replace('_', ' ', $key))); ?> :
                                    </span>

                                    <span class="text-slate-600">
                                        <?php echo e(is_array($value) ? json_encode($value) : $value); ?>

                                    </span>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </details>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['title' => 'Aucune activité','message' => 'Les actions récentes apparaîtront ici.','icon' => '🕓']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Aucune activité','message' => 'Les actions récentes apparaîtront ici.','icon' => '🕓']); ?>
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
</div><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/admin/dashboard/activity-log-panel.blade.php ENDPATH**/ ?>