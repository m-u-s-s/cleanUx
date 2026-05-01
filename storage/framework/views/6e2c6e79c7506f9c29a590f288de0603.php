<div class="space-y-6">
    <div class="bg-white rounded-xl shadow border p-4 space-y-4">
        <div>
            <h3 class="text-lg font-semibold text-slate-900">Emails produit</h3>
            <p class="text-sm text-slate-500">Prévisualise les principaux emails transactionnels et consulte une journalisation minimale.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="text-sm text-slate-600">Template</label>
                <select wire:model.live="templateKey" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $templates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($key); ?>"><?php echo e($label); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </select>
            </div>
            <div>
                <label class="text-sm text-slate-600">Nom destinataire</label>
                <input type="text" wire:model.live="recipientName" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-sm text-slate-600">Email destinataire</label>
                <input type="email" wire:model.live="recipientEmail" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button wire:click="generatePreview" class="bg-sky-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-sky-700">Générer un aperçu</button>
            <span class="text-sm text-slate-500">Sujet : <span class="font-semibold text-slate-800"><?php echo e($subject); ?></span></span>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 bg-white rounded-xl shadow border overflow-hidden">
            <div class="px-4 py-3 border-b bg-slate-50">
                <h4 class="font-semibold text-slate-900">Aperçu email</h4>
            </div>
            <div class="p-4 bg-slate-100 overflow-auto">
                <?php echo $previewHtml; ?>

            </div>
        </div>

        <div class="bg-white rounded-xl shadow border p-4 space-y-3">
            <div>
                <h4 class="font-semibold text-slate-900">Journal récent</h4>
                <p class="text-sm text-slate-500">Aperçus et envois mail les plus récents.</p>
            </div>

            <div class="space-y-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $recentLogs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="border rounded-lg p-3 bg-slate-50">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-semibold text-slate-800"><?php echo e($log->subject ?: $log->template_key); ?></span>
                            <span class="text-[11px] uppercase tracking-wide px-2 py-1 rounded-full border <?php echo e($log->status === 'failed' ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'); ?>"><?php echo e($log->status); ?></span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500"><?php echo e($log->recipient_email ?: '—'); ?> • <?php echo e(strtoupper($log->channel)); ?></p>
                        <p class="mt-1 text-xs text-slate-400"><?php echo e(optional($log->created_at)->format('d/m/Y H:i')); ?></p>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <div class="text-sm text-slate-500 italic">Aucun log email disponible pour le moment.</div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/admin/product-emails-center.blade.php ENDPATH**/ ?>