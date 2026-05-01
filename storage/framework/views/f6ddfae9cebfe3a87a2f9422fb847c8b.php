<div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-slate-900">Actions mission</h3>
            <p class="text-sm text-slate-500">
                Statut actuel :
                <span class="font-medium text-slate-800"><?php echo e($mission->status); ?></span>
            </p>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($successMessage): ?>
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
        <?php echo e($successMessage); ?>

    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errorMessage): ?>
    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        <?php echo e($errorMessage); ?>

    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="grid gap-3 md:grid-cols-2">
        <button
            wire:click="setEnRoute"
            type="button"
            class="rounded-xl bg-slate-900 px-4 py-3 text-sm font-medium text-white disabled:opacity-50"
            <?php if(! in_array($mission->status, ['planned', 'assigned'])): echo 'disabled'; endif; ?>
            >
            En route
        </button>

        <button
            wire:click="setArrived"
            type="button"
            class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-medium text-white disabled:opacity-50"
            <?php if(! in_array($mission->status, ['en_route', 'assigned'])): echo 'disabled'; endif; ?>
            >
            Arrivé
        </button>
    </div>
    <form
        onsubmit="startMissionWithCode(event, <?php echo e($mission->id); ?>)"
        enctype="multipart/form-data"
        class="space-y-3">
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">
                Code donné par le client
            </label>
            <input
                type="text"
                name="code"
                class="w-full rounded-xl border-slate-300"
                placeholder="Ex: 482913"
                required>
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1">
                Photos avant mission
            </label>
            <input
                type="file"
                name="photos_avant[]"
                accept="image/*"
                multiple
                class="w-full rounded-xl border border-slate-300 p-2">
        </div>

        <button
            type="submit"
            class="rounded-xl bg-emerald-600 px-4 py-2 text-white hover:bg-emerald-700">
            Démarrer la mission
        </button>
    </form>
    <div class="rounded-2xl border border-slate-200 p-4 space-y-3">
        <div class="flex items-center justify-between">
            <h4 class="font-medium text-slate-900">Commencer la mission</h4>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($generatedStartCode): ?>
            <span class="rounded-lg bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-800">
                Code début : <?php echo e($generatedStartCode); ?>

            </span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <div class="flex gap-2">
            <input
                wire:model.defer="startCode"
                type="text"
                inputmode="numeric"
                maxlength="6"
                placeholder="Code début"
                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-500 focus:outline-none">
            <button
                wire:click="startMission"
                type="button"
                class="rounded-xl bg-emerald-600 px-4 py-3 text-sm font-medium text-white disabled:opacity-50"
                <?php if($mission->status !== 'arrived'): echo 'disabled'; endif; ?>
                >
                Commencer
            </button>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['startCode'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
        <p class="text-sm text-red-600"><?php echo e($message); ?></p>
        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <div class="rounded-2xl border border-slate-200 p-4 space-y-3">
        <div class="flex items-center justify-between">
            <h4 class="font-medium text-slate-900">Terminer la mission</h4>

            <button
                wire:click="prepareEndCode"
                type="button"
                class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                <?php if(! in_array($mission->status, ['started', 'paused'])): echo 'disabled'; endif; ?>
                >
                Générer code fin
            </button>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($generatedEndCode): ?>
        <div class="rounded-xl bg-slate-100 px-4 py-3 text-sm text-slate-700">
            <span class="font-semibold">Code fin :</span> <?php echo e($generatedEndCode); ?>

        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="flex gap-2">
            <input
                wire:model.defer="endCode"
                type="text"
                inputmode="numeric"
                maxlength="6"
                placeholder="Code fin"
                class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-500 focus:outline-none">
            <button
                wire:click="finishMission"
                type="button"
                class="rounded-xl bg-red-600 px-4 py-3 text-sm font-medium text-white disabled:opacity-50"
                <?php if(! in_array($mission->status, ['started', 'paused'])): echo 'disabled'; endif; ?>
                >
                Terminer
            </button>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['endCode'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
        <p class="text-sm text-red-600"><?php echo e($message); ?></p>
        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<script>
    async function startMissionWithCode(event, missionId) {
        event.preventDefault();

        const form = event.target;
        const formData = new FormData(form);

        if (navigator.geolocation) {
            await new Promise((resolve) => {
                navigator.geolocation.getCurrentPosition((position) => {
                    formData.append('lat', position.coords.latitude);
                    formData.append('lng', position.coords.longitude);
                    resolve();
                }, () => resolve(), {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0,
                });
            });
        }

        const response = await fetch(`/missions/${missionId}/start`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: formData,
        });

        const result = await response.json();

        if (!result.ok) {
            alert('Code invalide ou impossible de démarrer la mission.');
            return;
        }

        alert('Mission démarrée avec succès.');
        window.location.reload();
    }
</script><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/employe/mission-actions.blade.php ENDPATH**/ ?>