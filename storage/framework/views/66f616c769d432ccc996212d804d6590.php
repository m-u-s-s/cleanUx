<div class="space-y-6">
    <?php if (isset($component)) { $__componentOriginal12a5063a2f74bc3084facd42cc51c606 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal12a5063a2f74bc3084facd42cc51c606 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-shell','data' => ['eyebrow' => 'International','title' => 'International exploitable','subtitle' => 'Active les marchés, configure les règles locales et pilote la readiness pays par pays.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('page-shell'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['eyebrow' => 'International','title' => 'International exploitable','subtitle' => 'Active les marchés, configure les règles locales et pilote la readiness pays par pays.']); ?>
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

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <?php echo e(session('success')); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-1 space-y-4">
            <?php if (isset($component)) { $__componentOriginalf3f7946f558699cf27352737986448eb = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf3f7946f558699cf27352737986448eb = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.filter-panel','data' => ['title' => 'Marchés','subtitle' => 'Recherche, stage et sélection du pays actif.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('filter-panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Marchés','subtitle' => 'Recherche, stage et sélection du pays actif.']); ?>
                <div class="space-y-3">
                    <input type="text" wire:model.live="search" placeholder="Nom, ISO, devise..." class="w-full rounded-lg border-gray-300 shadow-sm">
                    <select wire:model.live="stageFilter" class="w-full rounded-lg border-gray-300 shadow-sm">
                        <option value="">— Tous les stages —</option>
                        <option value="draft">Brouillon</option>
                        <option value="catalog_only">Catalogue uniquement</option>
                        <option value="booking_enabled">Réservation active</option>
                        <option value="mission_enabled">Mission active</option>
                        <option value="billing_enabled">Facturation active</option>
                        <option value="ready_for_launch">Prêt au lancement</option>
                    </select>
                </div>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf3f7946f558699cf27352737986448eb)): ?>
<?php $attributes = $__attributesOriginalf3f7946f558699cf27352737986448eb; ?>
<?php unset($__attributesOriginalf3f7946f558699cf27352737986448eb); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf3f7946f558699cf27352737986448eb)): ?>
<?php $component = $__componentOriginalf3f7946f558699cf27352737986448eb; ?>
<?php unset($__componentOriginalf3f7946f558699cf27352737986448eb); ?>
<?php endif; ?>

            <?php if (isset($component)) { $__componentOriginal593082f5d9bbe85e64b8909ad2db569b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal593082f5d9bbe85e64b8909ad2db569b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.app-card','data' => ['title' => 'Pays','subtitle' => 'Choisis un marché pour configurer son exploitation.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('app-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Pays','subtitle' => 'Choisis un marché pour configurer son exploitation.']); ?>
                <div class="space-y-3">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $countries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $country): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <?php ($stage = $country->operationalSetting?->launch_stage_label ?? 'Brouillon'); ?>
                        <?php ($score = $country->launchReadiness?->readiness_score ?? 0); ?>
                        <button type="button" wire:click="selectCountry(<?php echo e($country->id); ?>)" class="w-full rounded-2xl border p-4 text-left transition <?php echo e($selectedCountry && $selectedCountry->id === $country->id ? 'border-blue-500 bg-blue-50 shadow' : 'border-slate-200 bg-white hover:border-slate-300'); ?>">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-semibold text-slate-900"><?php echo e($country->name); ?></div>
                                    <div class="text-xs text-slate-500"><?php echo e($country->iso_code); ?> • <?php echo e($country->currency_code); ?> • <?php echo e($country->default_locale); ?></div>
                                </div>
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?php echo e($country->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'); ?>"><?php echo e($country->is_active ? 'Actif' : 'Inactif'); ?></span>
                            </div>
                            <div class="mt-3 flex items-center justify-between gap-3 text-xs text-slate-600">
                                <span><?php echo e($stage); ?></span>
                                <span>Readiness <?php echo e($score); ?>%</span>
                            </div>
                        </button>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-center text-slate-500">Aucun pays disponible.</div>
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

        <div class="xl:col-span-2 space-y-6">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedCountry): ?>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <?php if (isset($component)) { $__componentOriginala4ae059936bc185e758290466e2179c1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala4ae059936bc185e758290466e2179c1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.kpi-card','data' => ['title' => 'Pays','value' => $selectedCountry->name,'tone' => 'slate','icon' => '🌍']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('kpi-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Pays','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($selectedCountry->name),'tone' => 'slate','icon' => '🌍']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.kpi-card','data' => ['title' => 'Readiness','value' => $selectedCountryReadinessScore.'%','tone' => 'blue','icon' => '🚀']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('kpi-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Readiness','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($selectedCountryReadinessScore.'%'),'tone' => 'blue','icon' => '🚀']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.kpi-card','data' => ['title' => 'Devise','value' => $selectedCountry->currency_code,'tone' => 'green','icon' => '💶']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('kpi-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Devise','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($selectedCountry->currency_code),'tone' => 'green','icon' => '💶']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.kpi-card','data' => ['title' => 'Locale','value' => $selectedCountry->default_locale,'tone' => 'amber','icon' => '🈯']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('kpi-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Locale','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($selectedCountry->default_locale),'tone' => 'amber','icon' => '🈯']); ?>
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

                <?php if (isset($component)) { $__componentOriginal593082f5d9bbe85e64b8909ad2db569b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal593082f5d9bbe85e64b8909ad2db569b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.app-card','data' => ['title' => 'Réglages opérationnels','subtitle' => 'Active les briques produit par marché.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('app-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Réglages opérationnels','subtitle' => 'Active les briques produit par marché.']); ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="booking_enabled" class="rounded border-gray-300 text-blue-600 shadow-sm"> Réservation active</label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="mission_enabled" class="rounded border-gray-300 text-blue-600 shadow-sm"> Mission active</label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="billing_enabled" class="rounded border-gray-300 text-blue-600 shadow-sm"> Facturation active</label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="partner_network_enabled" class="rounded border-gray-300 text-blue-600 shadow-sm"> Réseau partenaires actif</label>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Stage marché</label>
                            <select wire:model.defer="readiness_stage" class="w-full rounded-lg border-gray-300 shadow-sm">
                                <option value="draft">Brouillon</option>
                                <option value="catalog_only">Catalogue uniquement</option>
                                <option value="booking_enabled">Réservation active</option>
                                <option value="mission_enabled">Mission active</option>
                                <option value="billing_enabled">Facturation active</option>
                                <option value="ready_for_launch">Prêt au lancement</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Symbole devise</label>
                            <input type="text" wire:model.defer="currency_symbol" class="w-full rounded-lg border-gray-300 shadow-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Format date</label>
                            <input type="text" wire:model.defer="date_format" class="w-full rounded-lg border-gray-300 shadow-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Format heure</label>
                            <input type="text" wire:model.defer="time_format" class="w-full rounded-lg border-gray-300 shadow-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Format adresse</label>
                            <input type="text" wire:model.defer="address_format" class="w-full rounded-lg border-gray-300 shadow-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Format téléphone</label>
                            <input type="text" wire:model.defer="phone_format" class="w-full rounded-lg border-gray-300 shadow-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Unité distance</label>
                            <input type="text" wire:model.defer="default_distance_unit" class="w-full rounded-lg border-gray-300 shadow-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Unité surface</label>
                            <input type="text" wire:model.defer="default_surface_unit" class="w-full rounded-lg border-gray-300 shadow-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Taxe par défaut (%)</label>
                            <input type="number" step="0.01" wire:model.defer="default_tax_rate" class="w-full rounded-lg border-gray-300 shadow-sm">
                        </div>

                        <label class="inline-flex items-center gap-2 text-sm text-slate-700 md:col-span-2"><input type="checkbox" wire:model.defer="requires_vat_number_for_companies" class="rounded border-gray-300 text-blue-600 shadow-sm"> Numéro TVA obligatoire pour les entreprises</label>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <button type="button" wire:click="saveOperationalSetting" class="cu-btn-primary">Enregistrer les réglages opérationnels</button>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.app-card','data' => ['title' => 'Facturation pays','subtitle' => 'Préfixes, taxes, arrondis et conditions de paiement.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('app-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Facturation pays','subtitle' => 'Préfixes, taxes, arrondis et conditions de paiement.']); ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Préfixe devis</label><input type="text" wire:model.defer="quote_prefix" class="w-full rounded-lg border-gray-300 shadow-sm"></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Préfixe facture</label><input type="text" wire:model.defer="invoice_prefix" class="w-full rounded-lg border-gray-300 shadow-sm"></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Libellé taxe</label><input type="text" wire:model.defer="tax_label" class="w-full rounded-lg border-gray-300 shadow-sm"></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Paiement (jours)</label><input type="number" wire:model.defer="payment_terms_days" class="w-full rounded-lg border-gray-300 shadow-sm"></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Mode arrondi</label><input type="text" wire:model.defer="rounding_mode" class="w-full rounded-lg border-gray-300 shadow-sm"></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Séparateur décimal</label><input type="text" wire:model.defer="decimal_separator" class="w-full rounded-lg border-gray-300 shadow-sm"></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Séparateur milliers</label><input type="text" wire:model.defer="thousands_separator" class="w-full rounded-lg border-gray-300 shadow-sm"></div>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700 md:col-span-2"><input type="checkbox" wire:model.defer="prices_include_tax" class="rounded border-gray-300 text-blue-600 shadow-sm"> Prix saisis TTC</label>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button type="button" wire:click="saveBillingProfile" class="cu-btn-primary">Enregistrer le profil de facturation</button>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.app-card','data' => ['title' => 'Readiness marché','subtitle' => 'Checklist de lancement réel par pays.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('app-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Readiness marché','subtitle' => 'Checklist de lancement réel par pays.']); ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm text-slate-700">
                        <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model.defer="catalog_ready" class="rounded border-gray-300 text-blue-600 shadow-sm"> Catalogue prêt</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model.defer="booking_ready" class="rounded border-gray-300 text-blue-600 shadow-sm"> Booking prêt</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model.defer="mission_ready" class="rounded border-gray-300 text-blue-600 shadow-sm"> Mission prête</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model.defer="billing_ready" class="rounded border-gray-300 text-blue-600 shadow-sm"> Billing prêt</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model.defer="partner_network_ready" class="rounded border-gray-300 text-blue-600 shadow-sm"> Réseau partenaires prêt</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model.defer="compliance_ready" class="rounded border-gray-300 text-blue-600 shadow-sm"> Compliance prête</label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" wire:model.defer="support_ready" class="rounded border-gray-300 text-blue-600 shadow-sm"> Support prêt</label>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Notes readiness</label>
                        <textarea wire:model.defer="readiness_notes" rows="4" class="w-full rounded-lg border-gray-300 shadow-sm"></textarea>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button type="button" wire:click="saveReadiness" class="cu-btn-primary">Enregistrer la readiness</button>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.app-card','data' => ['title' => 'Règles service par pays','subtitle' => 'Ce qui est vendable et opérable sur ce marché.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('app-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Règles service par pays','subtitle' => 'Ce qui est vendable et opérable sur ce marché.']); ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Service</label>
                            <select wire:model.live="service_catalog_id" class="w-full rounded-lg border-gray-300 shadow-sm">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $serviceCatalogs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($service->id); ?>"><?php echo e($service->display_name); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Multiplicateur prix</label>
                            <input type="number" step="0.01" wire:model.defer="service_pricing_multiplier" class="w-full rounded-lg border-gray-300 shadow-sm">
                        </div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Préavis min. (h)</label><input type="number" wire:model.defer="service_minimum_notice_hours" class="w-full rounded-lg border-gray-300 shadow-sm"></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">SLA réponse (h)</label><input type="number" wire:model.defer="service_sla_response_hours" class="w-full rounded-lg border-gray-300 shadow-sm"></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">SLA résolution (h)</label><input type="number" wire:model.defer="service_sla_resolution_hours" class="w-full rounded-lg border-gray-300 shadow-sm"></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Équipe par défaut (optionnel)</label><input type="number" wire:model.defer="service_default_team_id" class="w-full rounded-lg border-gray-300 shadow-sm"></div>
                        <div><label class="block text-sm font-medium text-slate-700 mb-1">Partenaire par défaut (optionnel)</label><input type="number" wire:model.defer="service_default_partner_id" class="w-full rounded-lg border-gray-300 shadow-sm"></div>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="service_is_enabled" class="rounded border-gray-300 text-blue-600 shadow-sm"> Service activé</label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" wire:model.defer="service_requires_quote" class="rounded border-gray-300 text-blue-600 shadow-sm"> Devis obligatoire</label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700 md:col-span-2"><input type="checkbox" wire:model.defer="service_requires_manual_validation" class="rounded border-gray-300 text-blue-600 shadow-sm"> Validation manuelle obligatoire</label>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button type="button" wire:click="saveServiceRule" class="cu-btn-primary">Enregistrer la règle service</button>
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
            <?php else: ?>
                <div class="cu-card p-8 text-center text-slate-500">Sélectionne un pays pour configurer son exploitation internationale.</div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
</div>
<?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/admin/international-operations-center.blade.php ENDPATH**/ ?>