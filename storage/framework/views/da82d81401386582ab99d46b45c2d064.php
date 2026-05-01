<div class="sticky top-6 space-y-6">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->hasPrefill): ?>
    <div class="bg-amber-50 rounded-3xl shadow-sm border border-amber-200 p-6">
        <p class="text-sm font-medium text-amber-700">Modèle actif</p>
        <h3 class="text-lg font-bold text-amber-900 mt-1">Préremplissage détecté</h3>
        <div class="mt-4 space-y-2 text-sm text-amber-800">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($prefilledFromSource): ?><p>🔁 Vous repartez d’une ancienne prestation.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($prefilledFromLast): ?><p>⏱️ Vous repartez de votre dernière réservation.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($prefilledFromAddress): ?><p>📍 Une adresse récente a été injectée dans le formulaire.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($step === 5 && $createdReference): ?>
    <div class="bg-emerald-50 rounded-3xl shadow-sm border border-emerald-200 p-6">
        <p class="text-sm font-medium text-emerald-700">Suivi</p>
        <h3 class="text-lg font-bold text-emerald-900 mt-1">Demande enregistrée</h3>
        <div class="mt-4 space-y-2 text-sm text-emerald-800">
            <p>Référence : <span class="font-bold"><?php echo e($createdReference); ?></span></p>
            <p>Statut initial : <span class="font-semibold">en attente</span></p>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->isPremiumClient() && $createdEmployeName): ?>
            <p>Employé demandé : <span class="font-semibold"><?php echo e($createdEmployeName); ?></span></p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6">
        <p class="text-sm font-medium text-slate-500">Résumé de votre demande</p>
        <h3 class="text-xl font-bold text-slate-900 mt-1">Estimation en direct</h3>
        <div class="mt-6 space-y-4">
            <div class="rounded-2xl bg-slate-50 p-4 border border-slate-100">
                <p class="text-sm text-slate-500">Durée estimée</p>
                <p class="text-2xl font-bold text-slate-900 mt-1"><?php echo e($duree_estimee > 0 ? $duree_estimee . ' min' : '--'); ?></p>
            </div>
            <div class="rounded-2xl bg-sky-50 p-4 border border-sky-100">
                <p class="text-sm text-sky-700">Devis estimatif</p>
                <p class="text-3xl font-extrabold text-sky-900 mt-1"><?php echo e(number_format((float) $devis_estime, 2, ',', ' ')); ?> €</p>
            </div>
            <div class="space-y-3 text-sm">
                <div class="flex items-center justify-between gap-3"><span class="text-slate-500">Service</span><span class="font-semibold text-slate-800 text-right"><?php echo e($selectedServiceLabel ?? ($services[$selected_service_identifier] ?? '—')); ?></span></div>
                <div class="flex items-center justify-between gap-3"><span class="text-slate-500">Lieu</span><span class="font-semibold text-slate-800 text-right"><?php echo e($typesLieu[$type_lieu] ?? '—'); ?></span></div>
                <div class="flex items-center justify-between gap-3"><span class="text-slate-500">Fréquence</span><span class="font-semibold text-slate-800 text-right"><?php echo e($frequences[$frequence] ?? '—'); ?></span></div>
                <div class="flex items-center justify-between gap-3"><span class="text-slate-500">Surface</span><span class="font-semibold text-slate-800 text-right"><?php echo e($surfaces[$surface] ?? '—'); ?></span></div>
                <div class="flex items-center justify-between gap-3"><span class="text-slate-500">Options</span><span class="font-semibold text-slate-800 text-right"><?php echo e(count($options_prestation)); ?></span></div>
                <div class="flex items-center justify-between gap-3"><span class="text-slate-500">Zones</span><span class="font-semibold text-slate-800 text-right"><?php echo e(count($zones_specifiques)); ?></span></div>
            </div>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$this->isPremiumClient()): ?>
    <div class="rounded-3xl border border-amber-200 bg-amber-50 p-6">
        <p class="text-sm font-semibold text-amber-800">Passez en Premium</p>
        <p class="text-sm text-amber-700 mt-2">Choisissez vos employés favoris et consultez leurs disponibilités.</p>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/client/booking/sidebar.blade.php ENDPATH**/ ?>