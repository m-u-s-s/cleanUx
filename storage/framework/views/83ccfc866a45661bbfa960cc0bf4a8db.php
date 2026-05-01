<div class="mx-auto max-w-5xl space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900"><?php echo e($title); ?></h1>
            <p class="text-sm text-slate-500"><?php echo e(__('Référence série :')); ?> <?php echo e($this->currentRendezVous->recurring_series_id); ?></p>
        </div>
        <a href="<?php echo e($backRoute); ?>" class="rounded-xl border px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
            <?php echo e(__('Retour')); ?>

        </a>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('success')): ?>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <?php echo e(session('success')); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="grid gap-6 lg:grid-cols-[1.2fr_.8fr]">
        <div class="rounded-2xl border bg-white p-6 shadow-sm space-y-5">
            <div>
                <h2 class="text-lg font-semibold text-slate-900"><?php echo e(__('Éditer la série')); ?></h2>
                <p class="text-sm text-slate-500"><?php echo e(__('Choisissez si la modification s’applique à une occurrence, aux occurrences futures ou à toute la série.')); ?></p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700"><?php echo e(__('Portée')); ?></label>
                    <select wire:model="scope" class="w-full rounded-xl border-slate-300">
                        <option value="occurrence"><?php echo e(__('Cette occurrence uniquement')); ?></option>
                        <option value="future"><?php echo e(__('Cette occurrence et les suivantes')); ?></option>
                        <option value="series"><?php echo e(__('Toute la série')); ?></option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700"><?php echo e(__('Employé')); ?></label>
                    <select wire:model="editEmployeId" class="w-full rounded-xl border-slate-300">
                        <option value=""><?php echo e(__('Conserver l’employé actuel')); ?></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $this->assignableEmployees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $employee): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($employee->id); ?>"><?php echo e($employee->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700"><?php echo e(__('Nouvelle date')); ?></label>
                    <input type="date" wire:model="editDate" class="w-full rounded-xl border-slate-300">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['editDate'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-sm text-red-600"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700"><?php echo e(__('Nouvelle heure')); ?></label>
                    <input type="time" wire:model="editHeure" class="w-full rounded-xl border-slate-300">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['editHeure'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-sm text-red-600"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['employe_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-sm text-red-600"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['heure'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-sm text-red-600"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['series'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-sm text-red-600"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div class="flex flex-wrap gap-3 pt-2">
                <button wire:click="saveChanges" class="rounded-xl bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">
                    <?php echo e(__('Sauvegarder')); ?>

                </button>
                <button wire:click="pauseSeries(scope)" class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100">
                    <?php echo e(__('Mettre en pause')); ?>

                </button>
                <button wire:click="resumeSeries(scope)" class="rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-100">
                    <?php echo e(__('Reprendre')); ?>

                </button>
                <button wire:click="cancelSeries(scope)" class="rounded-xl border border-red-300 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100">
                    <?php echo e(__('Annuler')); ?>

                </button>
            </div>
        </div>

        <div class="rounded-2xl border bg-white p-6 shadow-sm space-y-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-900"><?php echo e(__('Résumé')); ?></h2>
                <p class="text-sm text-slate-500"><?php echo e(__('Service :')); ?> <?php echo e($this->currentRendezVous->service_display_name); ?></p>
                <p class="text-sm text-slate-500"><?php echo e(__('Zone :')); ?> <?php echo e($this->currentRendezVous->serviceZone?->name ?? __('—')); ?></p>
                <p class="text-sm text-slate-500"><?php echo e(__('Client :')); ?> <?php echo e($this->currentRendezVous->client?->name ?? __('—')); ?></p>
            </div>
            <div class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-700">
                <p><span class="font-semibold"><?php echo e(__('Occurrences :')); ?></span> <?php echo e($this->seriesOccurrences->count()); ?></p>
                <p><span class="font-semibold"><?php echo e(__('Statut actuel :')); ?></span> <?php echo e(ucfirst($this->currentRendezVous->series_status ?? 'active')); ?></p>
                <p><span class="font-semibold"><?php echo e(__('Employé actuel :')); ?></span> <?php echo e($this->currentRendezVous->employe?->name ?? __('À affecter')); ?></p>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border bg-white p-6 shadow-sm">
        <div class="mb-4 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-900"><?php echo e(__('Occurrences de la série')); ?></h2>
                <p class="text-sm text-slate-500"><?php echo e(__('Visualisez les rendez-vous qui seront affectés.')); ?></p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                    <tr class="text-left text-slate-500">
                        <th class="py-2 pr-4">#</th>
                        <th class="py-2 pr-4"><?php echo e(__('Date')); ?></th>
                        <th class="py-2 pr-4"><?php echo e(__('Heure')); ?></th>
                        <th class="py-2 pr-4"><?php echo e(__('Employé')); ?></th>
                        <th class="py-2 pr-4"><?php echo e(__('Statut mission')); ?></th>
                        <th class="py-2 pr-4"><?php echo e(__('Statut série')); ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $this->seriesOccurrences; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $occurrence): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr class="<?php echo \Illuminate\Support\Arr::toCssClasses(['bg-sky-50' => $occurrence->id === $this->currentRendezVous->id]); ?>">
                            <td class="py-3 pr-4 font-semibold text-slate-700"><?php echo e($occurrence->series_position ?? '—'); ?></td>
                            <td class="py-3 pr-4 text-slate-700"><?php echo e(optional($occurrence->date)->format('d/m/Y')); ?></td>
                            <td class="py-3 pr-4 text-slate-700"><?php echo e(substr((string) $occurrence->heure, 0, 5)); ?></td>
                            <td class="py-3 pr-4 text-slate-700"><?php echo e($occurrence->employe?->name ?? __('À affecter')); ?></td>
                            <td class="py-3 pr-4 text-slate-700"><?php echo e(ucfirst(str_replace('_', ' ', $occurrence->status ?? '—'))); ?></td>
                            <td class="py-3 pr-4 text-slate-700"><?php echo e(ucfirst($occurrence->series_status ?? 'active')); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/recurring/edit-recurring-booking.blade.php ENDPATH**/ ?>