<div class="space-y-6">
    <?php if (isset($component)) { $__componentOriginal12a5063a2f74bc3084facd42cc51c606 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal12a5063a2f74bc3084facd42cc51c606 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-shell','data' => ['eyebrow' => __('Espace client'),'title' => __('Documents & finance'),'subtitle' => __('Consultez vos devis, vos factures, vos paiements récents et votre statut d’abonnement.')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('page-shell'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['eyebrow' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Espace client')),'title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Documents & finance')),'subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Consultez vos devis, vos factures, vos paiements récents et votre statut d’abonnement.'))]); ?>
         <?php $__env->slot('actions', null, []); ?> 
            <a href="<?php echo e(route('client.dashboard')); ?>" class="cu-btn-secondary"><?php echo e(__('← Retour au dashboard')); ?></a>
            <a href="<?php echo e(route('client.rendezvous.index')); ?>" class="cu-btn-secondary"><?php echo e(__('📅 Mes rendez-vous')); ?></a>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $subscriptionSummary['is_premium']): ?>
                <a href="<?php echo e(route('premium.offer')); ?>" class="cu-btn-primary !bg-amber-500 hover:!bg-amber-600"><?php echo e(__('Découvrir Premium')); ?></a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
         <?php $__env->endSlot(); ?>
     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal12a5063a2f74bc3084facd42cc51c606)): ?>
<?php $attributes = $__attributesOriginal12a5063a2f74bc3084facd42cc51c606; ?>
<?php unset($__attributesOriginal12a5063a2f74bc3084facd42cc51c606); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal12a5063a2f74bc3084facd42cc51c606)): ?>
<?php $component = $__componentOriginal12a5063a2f74bc3084facd42cc51c606; ?>
<?php unset($__componentOriginal12a5063a2f74bc3084facd42cc51c606); ?>
<?php endif; ?>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <?php if (isset($component)) { $__componentOriginala4ae059936bc185e758290466e2179c1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala4ae059936bc185e758290466e2179c1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.kpi-card','data' => ['title' => __('Devis'),'value' => $financeSummary['quotes_count'],'tone' => 'sky','icon' => '🧾']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('kpi-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Devis')),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($financeSummary['quotes_count']),'tone' => 'sky','icon' => '🧾']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala4ae059936bc185e758290466e2179c1)): ?>
<?php $attributes = $__attributesOriginala4ae059936bc185e758290466e2179c1; ?>
<?php unset($__attributesOriginala4ae059936bc185e758290466e2179c1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala4ae059936bc185e758290466e2179c1)): ?>
<?php $component = $__componentOriginala4ae059936bc185e758290466e2179c1; ?>
<?php unset($__componentOriginala4ae059936bc185e758290466e2179c1); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginala4ae059936bc185e758290466e2179c1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala4ae059936bc185e758290466e2179c1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.kpi-card','data' => ['title' => __('Devis à valider'),'value' => $financeSummary['quotes_pending'],'tone' => 'amber','icon' => '⏳']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('kpi-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Devis à valider')),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($financeSummary['quotes_pending']),'tone' => 'amber','icon' => '⏳']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala4ae059936bc185e758290466e2179c1)): ?>
<?php $attributes = $__attributesOriginala4ae059936bc185e758290466e2179c1; ?>
<?php unset($__attributesOriginala4ae059936bc185e758290466e2179c1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala4ae059936bc185e758290466e2179c1)): ?>
<?php $component = $__componentOriginala4ae059936bc185e758290466e2179c1; ?>
<?php unset($__componentOriginala4ae059936bc185e758290466e2179c1); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginala4ae059936bc185e758290466e2179c1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala4ae059936bc185e758290466e2179c1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.kpi-card','data' => ['title' => __('Factures'),'value' => $financeSummary['invoices_count'],'tone' => 'slate','icon' => '📄']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('kpi-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Factures')),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($financeSummary['invoices_count']),'tone' => 'slate','icon' => '📄']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala4ae059936bc185e758290466e2179c1)): ?>
<?php $attributes = $__attributesOriginala4ae059936bc185e758290466e2179c1; ?>
<?php unset($__attributesOriginala4ae059936bc185e758290466e2179c1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala4ae059936bc185e758290466e2179c1)): ?>
<?php $component = $__componentOriginala4ae059936bc185e758290466e2179c1; ?>
<?php unset($__componentOriginala4ae059936bc185e758290466e2179c1); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginala4ae059936bc185e758290466e2179c1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala4ae059936bc185e758290466e2179c1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.kpi-card','data' => ['title' => __('En retard'),'value' => $financeSummary['overdue_count'],'tone' => 'rose','icon' => '⚠️']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('kpi-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('En retard')),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($financeSummary['overdue_count']),'tone' => 'rose','icon' => '⚠️']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala4ae059936bc185e758290466e2179c1)): ?>
<?php $attributes = $__attributesOriginala4ae059936bc185e758290466e2179c1; ?>
<?php unset($__attributesOriginala4ae059936bc185e758290466e2179c1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala4ae059936bc185e758290466e2179c1)): ?>
<?php $component = $__componentOriginala4ae059936bc185e758290466e2179c1; ?>
<?php unset($__componentOriginala4ae059936bc185e758290466e2179c1); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginala4ae059936bc185e758290466e2179c1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala4ae059936bc185e758290466e2179c1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.kpi-card','data' => ['title' => __('Reste à payer'),'value' => number_format((float) $financeSummary['outstanding_total'], 2, ',', ' ') . ' ' . ($financeSummary['currency_symbol'] ?? '€'),'tone' => 'emerald','icon' => '💳']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('kpi-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Reste à payer')),'value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(number_format((float) $financeSummary['outstanding_total'], 2, ',', ' ') . ' ' . ($financeSummary['currency_symbol'] ?? '€')),'tone' => 'emerald','icon' => '💳']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala4ae059936bc185e758290466e2179c1)): ?>
<?php $attributes = $__attributesOriginala4ae059936bc185e758290466e2179c1; ?>
<?php unset($__attributesOriginala4ae059936bc185e758290466e2179c1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala4ae059936bc185e758290466e2179c1)): ?>
<?php $component = $__componentOriginala4ae059936bc185e758290466e2179c1; ?>
<?php unset($__componentOriginala4ae059936bc185e758290466e2179c1); ?>
<?php endif; ?>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <?php if (isset($component)) { $__componentOriginal593082f5d9bbe85e64b8909ad2db569b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal593082f5d9bbe85e64b8909ad2db569b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.app-card','data' => ['padding' => 'p-6','title' => __('Filtres'),'subtitle' => __('Affinez les documents visibles.')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('app-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['padding' => 'p-6','title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Filtres')),'subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Affinez les documents visibles.'))]); ?>
            <div class="space-y-4">
                <div>
                    <label class="text-sm font-medium text-slate-600"><?php echo e(__('Type de document')); ?></label>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <button wire:click="setDocumentType('all')" class="cu-chip <?php echo e($documentType === 'all' ? '!bg-slate-900 !text-white !border-slate-900' : ''); ?>"><?php echo e(__('Tous')); ?></button>
                        <button wire:click="setDocumentType('quotes')" class="cu-chip <?php echo e($documentType === 'quotes' ? '!bg-slate-900 !text-white !border-slate-900' : ''); ?>"><?php echo e(__('Devis')); ?></button>
                        <button wire:click="setDocumentType('invoices')" class="cu-chip <?php echo e($documentType === 'invoices' ? '!bg-slate-900 !text-white !border-slate-900' : ''); ?>"><?php echo e(__('Factures')); ?></button>
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-600"><?php echo e(__('Statut')); ?></label>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['all' => __('Tous'), 'draft' => __('Brouillon'), 'sent' => __('Envoyé'), 'accepted' => __('Accepté'), 'issued' => __('Émise'), 'partial' => __('Partiel'), 'paid' => __('Payée'), 'overdue' => __('En retard')]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <button wire:click="setStatus('<?php echo e($value); ?>')" class="cu-chip <?php echo e($status === $value ? '!bg-slate-900 !text-white !border-slate-900' : ''); ?>"><?php echo e($label); ?></button>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            </div>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal593082f5d9bbe85e64b8909ad2db569b)): ?>
<?php $attributes = $__attributesOriginal593082f5d9bbe85e64b8909ad2db569b; ?>
<?php unset($__attributesOriginal593082f5d9bbe85e64b8909ad2db569b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal593082f5d9bbe85e64b8909ad2db569b)): ?>
<?php $component = $__componentOriginal593082f5d9bbe85e64b8909ad2db569b; ?>
<?php unset($__componentOriginal593082f5d9bbe85e64b8909ad2db569b); ?>
<?php endif; ?>

        <?php if (isset($component)) { $__componentOriginal593082f5d9bbe85e64b8909ad2db569b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal593082f5d9bbe85e64b8909ad2db569b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.app-card','data' => ['padding' => 'p-6','title' => __('Abonnement'),'subtitle' => __('Suivi de votre plan et de ses avantages.')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('app-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['padding' => 'p-6','title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Abonnement')),'subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Suivi de votre plan et de ses avantages.'))]); ?>
            <div class="space-y-3 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-slate-500"><?php echo e(__('Plan')); ?></span>
                    <span class="font-semibold text-slate-800"><?php echo e(ucfirst((string) $subscriptionSummary['plan_type'])); ?></span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-500"><?php echo e(__('Statut')); ?></span>
                    <span class="font-semibold <?php echo e($subscriptionSummary['is_past_due'] ? 'text-rose-700' : ($subscriptionSummary['is_premium'] ? 'text-emerald-700' : 'text-slate-700')); ?>">
                        <?php echo e($subscriptionSummary['is_premium'] ? __('Actif') : ucfirst((string) $subscriptionSummary['plan_status'])); ?>

                    </span>
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($subscriptionSummary['renewal_at']): ?>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500"><?php echo e(__('Renouvellement')); ?></span>
                        <span class="font-semibold text-slate-800"><?php echo e(optional($subscriptionSummary['renewal_at'])->format('d/m/Y')); ?></span>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div class="mt-5 cu-card-muted p-4 <?php echo e($subscriptionSummary['is_premium'] ? '!border-amber-100 !bg-amber-50' : ''); ?>">
                <p class="text-sm font-semibold <?php echo e($subscriptionSummary['is_premium'] ? 'text-amber-800' : 'text-slate-800'); ?>"><?php echo e(__('Avantages')); ?></p>
                <ul class="mt-2 space-y-2 text-sm <?php echo e($subscriptionSummary['is_premium'] ? 'text-amber-700' : 'text-slate-600'); ?>">
                    <li><?php echo e(__('• Choix des employés favoris')); ?></li>
                    <li><?php echo e(__('• Meilleure visibilité sur les disponibilités')); ?></li>
                    <li><?php echo e(__('• Gestion plus simple de vos documents')); ?></li>
                </ul>
            </div>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal593082f5d9bbe85e64b8909ad2db569b)): ?>
<?php $attributes = $__attributesOriginal593082f5d9bbe85e64b8909ad2db569b; ?>
<?php unset($__attributesOriginal593082f5d9bbe85e64b8909ad2db569b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal593082f5d9bbe85e64b8909ad2db569b)): ?>
<?php $component = $__componentOriginal593082f5d9bbe85e64b8909ad2db569b; ?>
<?php unset($__componentOriginal593082f5d9bbe85e64b8909ad2db569b); ?>
<?php endif; ?>

        <?php if (isset($component)) { $__componentOriginal593082f5d9bbe85e64b8909ad2db569b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal593082f5d9bbe85e64b8909ad2db569b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.app-card','data' => ['padding' => 'p-6','title' => __('Paiements récents'),'subtitle' => __('Derniers paiements enregistrés sur vos factures.')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('app-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['padding' => 'p-6','title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Paiements récents')),'subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Derniers paiements enregistrés sur vos factures.'))]); ?>
            <div class="space-y-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $latestPaymentEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="cu-list-item flex items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold text-slate-800"><?php echo e($payment->payment_reference ?: __('Paiement')); ?></p>
                            <p class="text-sm text-slate-500"><?php echo e(optional($payment->paid_at)->format('d/m/Y H:i') ?: optional($payment->created_at)->format('d/m/Y H:i')); ?></p>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold text-slate-900"><?php echo e(number_format((float) $payment->amount, 2, ',', ' ')); ?> <?php echo e($financeSummary['currency_symbol'] ?? '€'); ?></p>
                            <p class="text-xs uppercase tracking-wide text-slate-500"><?php echo e($payment->status); ?></p>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['title' => __('Aucun paiement récent'),'message' => __('Vos paiements enregistrés apparaîtront ici.'),'icon' => '💳']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Aucun paiement récent')),'message' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Vos paiements enregistrés apparaîtront ici.')),'icon' => '💳']); ?>
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
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal593082f5d9bbe85e64b8909ad2db569b)): ?>
<?php $attributes = $__attributesOriginal593082f5d9bbe85e64b8909ad2db569b; ?>
<?php unset($__attributesOriginal593082f5d9bbe85e64b8909ad2db569b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal593082f5d9bbe85e64b8909ad2db569b)): ?>
<?php $component = $__componentOriginal593082f5d9bbe85e64b8909ad2db569b; ?>
<?php unset($__componentOriginal593082f5d9bbe85e64b8909ad2db569b); ?>
<?php endif; ?>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($documentType !== 'invoices'): ?>
        <?php if (isset($component)) { $__componentOriginal593082f5d9bbe85e64b8909ad2db569b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal593082f5d9bbe85e64b8909ad2db569b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.app-card','data' => ['padding' => 'p-6','title' => __('Mes devis'),'subtitle' => __('Consultez et téléchargez vos derniers devis.')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('app-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['padding' => 'p-6','title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Mes devis')),'subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Consultez et téléchargez vos derniers devis.'))]); ?>
            <div class="space-y-4">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $quotes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $quote): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="cu-list-item flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-lg font-semibold text-slate-900"><?php echo e($quote->quote_number); ?></p>
                                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold <?php echo e($this->quoteStatusBadge((string) $quote->status)); ?>">
                                    <?php echo e(ucfirst((string) $quote->status)); ?>

                                </span>
                            </div>
                            <p class="mt-1 text-sm text-slate-600"><?php echo e($quote->rendezVous?->service_display_name ?? 'Service non précisé'); ?></p>
                            <p class="text-sm text-slate-500"><?php echo e($quote->rendezVous?->serviceZone?->name ?? __('Zone non précisée')); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($quote->rendezVous?->organizationSite): ?> · <?php echo e($quote->rendezVous->organizationSite->name); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
                            <p class="text-sm text-slate-500"><?php echo e(__('Émis le')); ?> <?php echo e(optional($quote->issued_at)->format('d/m/Y') ?: '—'); ?> · <?php echo e(__('Valable jusqu’au')); ?> <?php echo e(optional($quote->valid_until)->format('d/m/Y') ?: '—'); ?></p>
                        </div>

                        <div class="flex flex-col items-start gap-3 lg:items-end">
                            <p class="text-xl font-bold text-slate-900"><?php echo e($quote->formatDocumentMoney($quote->total_amount)); ?></p>
                            <a href="<?php echo e(route('client.finance.quote.download', $quote)); ?>" class="cu-btn-secondary"><?php echo e(__('📥 Télécharger le devis')); ?></a>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['title' => __('Aucun devis'),'message' => __('Vos devis apparaîtront ici dès qu’un rendez-vous sera chiffré.'),'icon' => '🧾']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Aucun devis')),'message' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Vos devis apparaîtront ici dès qu’un rendez-vous sera chiffré.')),'icon' => '🧾']); ?>
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
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal593082f5d9bbe85e64b8909ad2db569b)): ?>
<?php $attributes = $__attributesOriginal593082f5d9bbe85e64b8909ad2db569b; ?>
<?php unset($__attributesOriginal593082f5d9bbe85e64b8909ad2db569b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal593082f5d9bbe85e64b8909ad2db569b)): ?>
<?php $component = $__componentOriginal593082f5d9bbe85e64b8909ad2db569b; ?>
<?php unset($__componentOriginal593082f5d9bbe85e64b8909ad2db569b); ?>
<?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($documentType !== 'quotes'): ?>
        <?php if (isset($component)) { $__componentOriginal593082f5d9bbe85e64b8909ad2db569b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal593082f5d9bbe85e64b8909ad2db569b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.app-card','data' => ['padding' => 'p-6','title' => __('Mes factures'),'subtitle' => __('Suivez votre reste à payer et téléchargez vos factures.')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('app-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['padding' => 'p-6','title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Mes factures')),'subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Suivez votre reste à payer et téléchargez vos factures.'))]); ?>
            <div class="space-y-4">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $invoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="cu-list-item flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-lg font-semibold text-slate-900"><?php echo e($invoice->invoice_number); ?></p>
                                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold <?php echo e($this->invoiceStatusBadge((string) $invoice->status)); ?>">
                                    <?php echo e(ucfirst((string) $invoice->status)); ?>

                                </span>
                            </div>
                            <p class="mt-1 text-sm text-slate-600"><?php echo e($invoice->rendezVous?->service_display_name ?? 'Service non précisé'); ?></p>
                            <p class="text-sm text-slate-500"><?php echo e(__('Émise le')); ?> <?php echo e(optional($invoice->issued_at)->format('d/m/Y') ?: '—'); ?> · <?php echo e(__('Échéance')); ?> <?php echo e(optional($invoice->due_at)->format('d/m/Y') ?: '—'); ?></p>
                            <p class="text-sm text-slate-500"><?php echo e(__('Reste à payer :')); ?> <?php echo e($invoice->formatDocumentMoney($invoice->balance_due)); ?></p>
                        </div>

                        <div class="flex flex-col items-start gap-3 lg:items-end">
                            <p class="text-xl font-bold text-slate-900"><?php echo e($invoice->formatDocumentMoney($invoice->total_amount)); ?></p>
                            <a href="<?php echo e(route('client.finance.invoice.download', $invoice)); ?>" class="cu-btn-secondary"><?php echo e(__('📥 Télécharger la facture')); ?></a>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <?php if (isset($component)) { $__componentOriginal074a021b9d42f490272b5eefda63257c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal074a021b9d42f490272b5eefda63257c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.empty-state','data' => ['title' => __('Aucune facture'),'message' => __('Vos factures apparaîtront ici dès qu’une prestation sera confirmée ou terminée.'),'icon' => '📄']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Aucune facture')),'message' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(__('Vos factures apparaîtront ici dès qu’une prestation sera confirmée ou terminée.')),'icon' => '📄']); ?>
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
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal593082f5d9bbe85e64b8909ad2db569b)): ?>
<?php $attributes = $__attributesOriginal593082f5d9bbe85e64b8909ad2db569b; ?>
<?php unset($__attributesOriginal593082f5d9bbe85e64b8909ad2db569b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal593082f5d9bbe85e64b8909ad2db569b)): ?>
<?php $component = $__componentOriginal593082f5d9bbe85e64b8909ad2db569b; ?>
<?php unset($__componentOriginal593082f5d9bbe85e64b8909ad2db569b); ?>
<?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/client/finance-documents-client.blade.php ENDPATH**/ ?>