<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-5">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-slate-900">Suivi de mission</h3>
            <p class="text-sm text-slate-500">
                Statut :
                <span class="font-medium text-slate-800"><?php echo e($mission->status); ?></span>
            </p>
        </div>

        <div class="text-right text-sm text-slate-500">
            <p>Référence : <span class="font-medium text-slate-800"><?php echo e($mission->rendezVous?->booking_reference ?? '—'); ?></span></p>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-xl border border-slate-200 p-4">
            <p class="text-sm text-slate-500">Employé principal</p>
            <p class="mt-1 font-medium text-slate-900"><?php echo e($mission->leadEmployee?->name ?? 'Non assigné'); ?></p>
        </div>

        <div class="rounded-xl border border-slate-200 p-4">
            <p class="text-sm text-slate-500">Heure prévue</p>
            <p class="mt-1 font-medium text-slate-900">
                <?php echo e(optional($mission->planned_start_at)->format('d/m/Y H:i') ?? '—'); ?>

            </p>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mission->status === 'en_route'): ?>
    <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700">
        Votre employé est en route.
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($mission->status, ['en_route', 'arrived', 'started', 'paused'])): ?>
    <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('client.mission-live-tracking', ['mission' => $mission]);

$__key = 'mission-live-tracking-'.$mission->id;

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-2304194737-0', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key);

echo $__html;

unset($__html);
unset($__key);
unset($__name);
unset($__params);
unset($__split);
if (isset($__slots)) unset($__slots);
?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($startCodeRecord && in_array($mission->status, ['arrived'])): ?>
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 space-y-2">
        <h4 class="font-semibold text-emerald-800">Code de début disponible</h4>
        <p class="text-sm text-emerald-700">
            Donnez ce code à l’employé pour démarrer la mission.
        </p>
        <div class="inline-flex rounded-xl bg-white px-4 py-2 text-xl font-bold tracking-[0.3em] text-emerald-800">
            <?php echo e($clientStartCode ?? 'Code en attente'); ?>

        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($endCodeRecord && in_array($mission->status, ['started', 'paused'])): ?>
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 space-y-2">
        <h4 class="font-semibold text-amber-800">Code de fin disponible</h4>
        <p class="text-sm text-amber-700">
            Donnez ce code à l’employé pour clôturer la mission.
        </p>
        <div class="inline-flex rounded-xl bg-white px-4 py-2 text-xl font-bold tracking-[0.3em] text-amber-800">
            <?php echo e(session('mission_end_code_'.$mission->id) ?? 'Code généré côté employé'); ?>

        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($mission->status, ['arrived', 'started', 'paused', 'completed'])): ?>
    <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('client.mission-client-actions', ['mission' => $mission]);

$__key = 'client-actions-'.$mission->id;

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-2304194737-1', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key);

echo $__html;

unset($__html);
unset($__key);
unset($__name);
unset($__params);
unset($__split);
if (isset($__slots)) unset($__slots);
?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mission->status === 'completed'): ?>
    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
        Mission terminée avec succès.
    </div>
    <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('client.mission-qr-codes', ['mission' => $mission]);

$__key = 'qr-codes-'.$mission->id;

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-2304194737-2', $__key);

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
[$__name, $__params] = $__split('client.mission-aftercare-summary', ['mission' => $mission]);

$__key = 'aftercare-'.$mission->id;

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-2304194737-3', $__key);

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
[$__name, $__params] = $__split('client.mission-final-validation', ['mission' => $mission]);

$__key = 'final-validation-'.$mission->id;

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-2304194737-4', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key);

echo $__html;

unset($__html);
unset($__key);
unset($__name);
unset($__params);
unset($__split);
if (isset($__slots)) unset($__slots);
?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/client/mission-tracking.blade.php ENDPATH**/ ?>