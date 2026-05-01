<div class="min-h-screen bg-slate-50" <?php if($realtimeEnabled): ?> wire:poll.10s="realtimeRefresh" <?php endif; ?>>
    <?php echo $__env->make('livewire.admin.dashboard.loading-overlay', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <?php echo $__env->make('livewire.admin.dashboard.calendar-modal', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <?php echo $__env->make('livewire.admin.dashboard.quick-actions', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <?php echo $__env->make('livewire.admin.dashboard.mobile-actions', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

    <?php
    $filtreZone = $filtreZone
    ?? data_get(auth()->user(), 'managed_service_zone_id')
    ?? data_get(auth()->user(), 'primary_service_zone_id');

    $adminScopeId = ($zoneScopeLocked ?? false) && filled($filtreZone)
    ? (int) $filtreZone
    : null;
    ?>

    <div class="mx-auto max-w-7xl space-y-8 px-4 pb-28 pt-6 sm:px-6 lg:px-8 lg:pb-8">

        
        <?php echo $__env->make('livewire.admin.dashboard.shell', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        <?php echo $__env->make('livewire.admin.dashboard.filters', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        <?php echo $__env->make('livewire.admin.dashboard.realtime-indicator', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        <?php echo $__env->make('livewire.admin.dashboard.section-controls', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

        
        <section class="space-y-4">
            <?php echo $__env->make('livewire.admin.dashboard.kpis', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            <?php echo $__env->make('livewire.admin.dashboard.today-priority', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </section>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($executiveMode): ?>
        <section class="space-y-6">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">
                    Vue exécutive
                </p>
                <h2 class="text-2xl font-black text-slate-900">
                    Synthèse rapide de la plateforme
                </h2>
                <p class="text-sm text-slate-500">
                    Vue simplifiée pour suivre les priorités sans détails opérationnels.
                </p>
            </div>

            <?php echo $__env->make('livewire.admin.dashboard.executive-summary', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            <?php echo $__env->make('livewire.admin.dashboard.executive-actions', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            <?php echo $__env->make('livewire.admin.dashboard.alert-panels', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            <?php echo $__env->make('livewire.admin.dashboard.charts-and-calendar', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <?php echo $__env->make('livewire.admin.dashboard.activity-log-panel', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                <?php echo $__env->make('livewire.admin.dashboard.export-feedbacks', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            </div>
        </section>
        <?php else: ?>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($visibleDashboardSections['operations'] ?? true): ?>
        <section class="space-y-6">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-blue-600">
                    Priorité opérationnelle
                </p>
                <h2 class="text-2xl font-black text-slate-900">
                    Ce qui demande ton attention maintenant
                </h2>
                <p class="text-sm text-slate-500">
                    Urgences, interventions du jour, charge employés et missions à suivre.
                </p>
            </div>

            <?php echo $__env->make('livewire.admin.dashboard.operations-overview', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            <?php echo $__env->make('livewire.admin.dashboard.alert-panels', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($visibleDashboardSections['analytics'] ?? true): ?>
        <section class="space-y-6">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-600">
                    Analyse & qualité
                </p>
                <h2 class="text-2xl font-black text-slate-900">
                    Performance, qualité et tendances
                </h2>
                <p class="text-sm text-slate-500">
                    Indicateurs métier, feedbacks, durées, services et qualité terrain.
                </p>
            </div>

            <?php echo $__env->make('livewire.admin.dashboard.analytics-panel', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $compactMode): ?>
            <?php echo $__env->make('livewire.admin.dashboard.quality-panel', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($visibleDashboardSections['premium'] ?? true) && ! $compactMode): ?>
        <section class="space-y-6">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-amber-600">
                    Clients & premium
                </p>
                <h2 class="text-2xl font-black text-slate-900">
                    Suivi client et abonnements
                </h2>
                <p class="text-sm text-slate-500">
                    Clients premium, rendez-vous non assignés et accompagnement personnalisé.
                </p>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $compactMode): ?>
            <?php echo $__env->make('livewire.admin.dashboard.premium-overview', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($visibleDashboardSections['charts'] ?? true): ?>
        <section class="space-y-6">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">
                    Planning visuel
                </p>
                <h2 class="text-2xl font-black text-slate-900">
                    Graphiques et calendrier global
                </h2>
                <p class="text-sm text-slate-500">
                    Vue visuelle de la charge, des rendez-vous et de l’évolution mensuelle.
                </p>
            </div>

            <?php echo $__env->make('livewire.admin.dashboard.charts-and-calendar', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
        </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($visibleDashboardSections['tools'] ?? true): ?>
        <section class="space-y-6">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">
                    Outils rapides
                </p>
                <h2 class="text-2xl font-black text-slate-900">
                    Exports, limites et activité
                </h2>
                <p class="text-sm text-slate-500">
                    Actions administratives fréquentes et suivi des logs récents.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <?php echo $__env->make('livewire.admin.dashboard.export-feedbacks', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                <?php echo $__env->make('livewire.admin.dashboard.activity-log-panel', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $compactMode): ?>
            <?php echo $__env->make('livewire.admin.dashboard.employee-limits', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($visibleDashboardSections['modules'] ?? false) && ! $compactMode): ?>
        <section class="space-y-6">
            <details class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <summary class="cursor-pointer list-none">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">
                                Modules avancés
                            </p>
                            <h2 class="text-2xl font-black text-slate-900">
                                Ouvrir les modules intégrés
                            </h2>
                            <p class="text-sm text-slate-500">
                                Feedbacks détaillés, qualité RH, utilisateurs et agenda hebdomadaire.
                            </p>
                        </div>

                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">
                            Cliquer pour ouvrir
                        </span>
                    </div>
                </summary>

                <div class="mt-6 space-y-6">
                    <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('admin.feedback-stats', ['scopeId' => $adminScopeId]);

$__key = null;

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-978099882-0', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key);

echo $__html;

unset($__html);
unset($__key);
unset($__name);
unset($__params);
unset($__split);
if (isset($__slots)) unset($__slots);
?>

                    <?php echo $__env->make('livewire.admin.dashboard.embedded-modules', [
                    'adminScopeId' => $adminScopeId
                    ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                </div>
            </details>
        </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php echo $__env->make('livewire.admin.dashboard.scripts', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

        <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('admin.admin-alerts-center', []);

$__key = null;

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-978099882-1', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key);

echo $__html;

unset($__html);
unset($__key);
unset($__name);
unset($__params);
unset($__split);
if (isset($__slots)) unset($__slots);
?>
        <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('admin.admin-analytics-dashboard', []);

$__key = null;

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-978099882-2', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key);

echo $__html;

unset($__html);
unset($__key);
unset($__name);
unset($__params);
unset($__split);
if (isset($__slots)) unset($__slots);
?>
        <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('admin.employee-performance', []);

$__key = null;

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-978099882-3', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key);

echo $__html;

unset($__html);
unset($__key);
unset($__name);
unset($__params);
unset($__split);
if (isset($__slots)) unset($__slots);
?>
    </div>
</div><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/admin-dashboard.blade.php ENDPATH**/ ?>