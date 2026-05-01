<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
            <div>
                <h2 class="text-2xl font-bold text-blue-900">📋 Mes missions</h2>
                <p class="text-sm text-gray-500">Suivi opérationnel de vos rendez-vous et interventions.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="<?php echo e(route('employe.dashboard')); ?>"
                    class="inline-flex items-center px-4 py-2 rounded-xl border bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                    ← Dashboard
                </a>
                <a href="<?php echo e(route('employe.historique')); ?>"
                    class="inline-flex items-center px-4 py-2 rounded-xl border bg-white text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                    🕘 Historique
                </a>
            </div>
        </div>

        <div class="grid grid-cols-2 xl:grid-cols-5 gap-4">
            <div class="bg-white rounded-xl shadow border p-4">
                <p class="text-sm text-gray-500">Total</p>
                <p class="text-2xl font-bold text-slate-800"><?php echo e($stats['total'] ?? 0); ?></p>
            </div>
            <div class="bg-white rounded-xl shadow border p-4">
                <p class="text-sm text-gray-500">À confirmer</p>
                <p class="text-2xl font-bold text-amber-600"><?php echo e($stats['a_confirmer'] ?? 0); ?></p>
            </div>
            <div class="bg-white rounded-xl shadow border p-4">
                <p class="text-sm text-gray-500">À faire</p>
                <p class="text-2xl font-bold text-blue-700"><?php echo e($stats['a_faire'] ?? 0); ?></p>
            </div>
            <div class="bg-white rounded-xl shadow border p-4">
                <p class="text-sm text-gray-500">Terminées</p>
                <p class="text-2xl font-bold text-emerald-700"><?php echo e($stats['terminees'] ?? 0); ?></p>
            </div>
            <div class="bg-white rounded-xl shadow border p-4">
                <p class="text-sm text-gray-500">Zones</p>
                <p class="text-2xl font-bold text-indigo-700"><?php echo e($stats['zone_count'] ?? 0); ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1.2fr)_minmax(360px,0.8fr)] gap-6 items-start">
            <div class="space-y-6">
                <?php echo $__env->make('livewire.employe.mes-rendez-vous', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
            </div>

            <div class="space-y-4">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedRendezVous && $selectedMission): ?>
                <div class="bg-white rounded-2xl border border-indigo-200 shadow-sm p-5 space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Mission sélectionnée</p>
                            <h3 class="text-lg font-bold text-slate-900">
                                <?php echo e($selectedRendezVous->service_display_name); ?>

                            </h3>
                            <p class="text-sm text-slate-500">
                                RDV #<?php echo e($selectedRendezVous->id); ?> · Mission #<?php echo e($selectedMission->id); ?>

                            </p>
                        </div>

                        <button
                            wire:click="clearSelectedRdv"
                            class="inline-flex items-center rounded-lg border px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">
                            Fermer
                        </button>

                        <button
                            type="button"
                            onclick="startMissionTracking(<?php echo e($selectedMission->id); ?>)"
                            class="rounded-xl bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">
                            Je suis en route
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                        <div class="rounded-xl bg-slate-50 border p-3">
                            <p class="text-slate-500">Client</p>
                            <p class="font-semibold text-slate-900"><?php echo e($selectedRendezVous->client?->name ?? '—'); ?></p>
                        </div>
                        <div class="rounded-xl bg-slate-50 border p-3">
                            <p class="text-slate-500">Adresse</p>
                            <p class="font-semibold text-slate-900"><?php echo e($selectedRendezVous->adresse ?? '—'); ?>, <?php echo e($selectedRendezVous->ville ?? '—'); ?></p>
                        </div>
                        <div class="rounded-xl bg-slate-50 border p-3">
                            <p class="text-slate-500">Statut RDV</p>
                            <p class="font-semibold text-slate-900"><?php echo e($selectedRendezVous->status); ?></p>
                        </div>
                        <div class="rounded-xl bg-slate-50 border p-3">
                            <p class="text-slate-500">Statut mission</p>
                            <p class="font-semibold text-slate-900"><?php echo e($selectedMission->status); ?></p>
                        </div>
                    </div>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $selectedMission->checklists; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $checklist): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="rounded-2xl border bg-white p-4 space-y-3">
                    <h3 class="font-bold"><?php echo e($checklist->template_name); ?></h3>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $checklist->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <label class="flex items-center gap-3">
                        <input
                            type="checkbox"
                            onchange="toggleChecklistItem(<?php echo e($item->id); ?>, this.checked)"
                            <?php echo e($item->status === 'done' ? 'checked' : ''); ?>>

                        <span>
                            <?php echo e($item->label); ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($item->is_required): ?>
                            <span class="text-red-500">*</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </span>
                    </label>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('employe.mission-actions', ['mission' => $selectedMission]);

$__key = 'mission-actions-'.$selectedMission->id;

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-951643331-0', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key);

echo $__html;

unset($__html);
unset($__key);
unset($__name);
unset($__params);
unset($__split);
if (isset($__slots)) unset($__slots);
?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($selectedMission->status, ['en_route', 'arrived', 'started', 'paused'])): ?>
                <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('employe.mission-route-tracking', ['mission' => $selectedMission]);

$__key = 'mission-route-'.$selectedMission->id;

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-951643331-1', $__key);

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

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($selectedMission->status, ['arrived', 'started', 'paused', 'completed'])): ?>
                <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('employe.mission-execution-board', ['mission' => $selectedMission]);

$__key = 'mission-execution-'.$selectedMission->id;

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-951643331-2', $__key);

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

                <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('employe.mission-incident-board', ['mission' => $selectedMission]);

$__key = 'incident-board-'.$selectedMission->id;

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-951643331-3', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key);

echo $__html;

unset($__html);
unset($__key);
unset($__name);
unset($__params);
unset($__split);
if (isset($__slots)) unset($__slots);
?>
                <?php else: ?>
                <div class="bg-white rounded-2xl border border-dashed border-slate-300 p-8 text-center text-slate-500">
                    <p class="text-base font-semibold text-slate-700">Aucune mission ouverte</p>
                    <p class="mt-2 text-sm">
                        Sélectionnez un rendez-vous avec une mission liée pour afficher le panneau terrain,
                        le tracking, les actions et les incidents.
                    </p>
                </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    async function toggleChecklistItem(itemId, checked) {
        await fetch(`/mission-checklist-items/${itemId}/toggle`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                status: checked ? 'done' : 'pending',
            }),
        });
    }
    let cleanUxTrackingInterval = null;

    function startSendingPosition(sessionId) {
    if (cleanUxTrackingInterval) {
        clearInterval(cleanUxTrackingInterval);
    }
    
    cleanUxTrackingInterval = setInterval(() => {
        if (!navigator.geolocation) {
            return;
        }
        
        navigator.geolocation.getCurrentPosition(async function(position) {
            await fetch(`/mission-tracking-sessions/${sessionId}/tracking/push`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                    accuracy_meters: position.coords.accuracy,
                    speed_kmh: position.coords.speed ? position.coords.speed * 3.6 : null,
                    heading: position.coords.heading,
                    source: 'browser',
                    app_state: document.hidden ? 'background' : 'foreground',
                }),
            });
        });
    }, 15000);
    }
    </script><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/employe/missions-employe.blade.php ENDPATH**/ ?>