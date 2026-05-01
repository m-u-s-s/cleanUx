<div class="space-y-6">
    <?php if (isset($component)) { $__componentOriginal12a5063a2f74bc3084facd42cc51c606 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal12a5063a2f74bc3084facd42cc51c606 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-shell','data' => ['eyebrow' => 'Territoire','title' => 'Gestion des zones','subtitle' => 'Pilotage Belgique par zones, règles de service et affectations opérationnelles.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('page-shell'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['eyebrow' => 'Territoire','title' => 'Gestion des zones','subtitle' => 'Pilotage Belgique par zones, règles de service et affectations opérationnelles.']); ?>
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

    <?php
        $statusPill = fn ($status) => match ($status) {
            'active' => 'bg-emerald-100 text-emerald-700',
            'paused' => 'bg-amber-100 text-amber-700',
            'archived' => 'bg-rose-100 text-rose-700',
            default => 'bg-slate-100 text-slate-700',
        };
    ?>

    <div class="grid grid-cols-2 xl:grid-cols-5 gap-4">
        <?php if (isset($component)) { $__componentOriginala4ae059936bc185e758290466e2179c1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala4ae059936bc185e758290466e2179c1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.kpi-card','data' => ['title' => 'Total zones','value' => $zoneStats['total'],'tone' => 'slate','icon' => '🧭']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('kpi-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Total zones','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($zoneStats['total']),'tone' => 'slate','icon' => '🧭']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.kpi-card','data' => ['title' => 'Actives','value' => $zoneStats['active'],'tone' => 'green','icon' => '✅']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('kpi-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Actives','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($zoneStats['active']),'tone' => 'green','icon' => '✅']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.kpi-card','data' => ['title' => 'En pause','value' => $zoneStats['paused'],'tone' => 'amber','icon' => '⏸️']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('kpi-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'En pause','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($zoneStats['paused']),'tone' => 'amber','icon' => '⏸️']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.kpi-card','data' => ['title' => 'Réservables','value' => $zoneStats['bookable'],'tone' => 'blue','icon' => '📅']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('kpi-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Réservables','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($zoneStats['bookable']),'tone' => 'blue','icon' => '📅']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.kpi-card','data' => ['title' => 'Visibles','value' => $zoneStats['visible'],'tone' => 'slate','icon' => '👁️']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('kpi-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Visibles','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($zoneStats['visible']),'tone' => 'slate','icon' => '👁️']); ?>
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

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-1 space-y-4">
            <?php if (isset($component)) { $__componentOriginalf3f7946f558699cf27352737986448eb = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf3f7946f558699cf27352737986448eb = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.filter-panel','data' => ['title' => 'Filtres','subtitle' => 'Recherche, statut, couverture, visibilité et territoire.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('filter-panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Filtres','subtitle' => 'Recherche, statut, couverture, visibilité et territoire.']); ?>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-1 gap-3">
                    <input type="text" wire:model.live="search" placeholder="Nom, code, slug..."
                        class="w-full border-gray-300 rounded-lg shadow-sm">

                    <select wire:model.live="statusFilter" class="w-full border-gray-300 rounded-lg shadow-sm">
                        <option value="">— Tous les statuts —</option>
                        <option value="draft">Draft</option>
                        <option value="active">Active</option>
                        <option value="paused">Paused</option>
                        <option value="archived">Archivée</option>
                    </select>

                    <select wire:model.live="coverageFilter" class="w-full border-gray-300 rounded-lg shadow-sm">
                        <option value="">— Tous types —</option>
                        <option value="national">National</option>
                        <option value="region">Région</option>
                        <option value="province">Province</option>
                        <option value="commune">Commune</option>
                        <option value="postal_code">Code postal</option>
                        <option value="custom">Custom</option>
                    </select>

                    <select wire:model.live="bookableFilter" class="w-full border-gray-300 rounded-lg shadow-sm">
                        <option value="">— Réservable ou non —</option>
                        <option value="1">Réservable</option>
                        <option value="0">Non réservable</option>
                    </select>

                    <select wire:model.live="visibilityFilter" class="w-full border-gray-300 rounded-lg shadow-sm">
                        <option value="">— Visible ou non —</option>
                        <option value="1">Visible</option>
                        <option value="0">Masquée</option>
                    </select>

                    <select wire:model.live="regionFilter" class="w-full border-gray-300 rounded-lg shadow-sm">
                        <option value="">— Toutes les régions —</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($region->id); ?>"><?php echo e($region->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>

                    <select wire:model.live="provinceFilter" class="w-full border-gray-300 rounded-lg shadow-sm">
                        <option value="">— Toutes les provinces —</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $provinces; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $province): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($province->id); ?>"><?php echo e($province->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>
                </div>

                <div class="flex justify-end">
                    <button type="button" wire:click="resetFilters" class="cu-btn-secondary">
                        Réinitialiser les filtres
                    </button>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.app-card','data' => ['title' => 'Liste des zones','subtitle' => 'Sélection d’une zone pour gérer ses règles et affectations.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('app-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Liste des zones','subtitle' => 'Sélection d’une zone pour gérer ses règles et affectations.']); ?>
                <div class="space-y-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $zones; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $zone): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <button type="button" wire:click="selectZone(<?php echo e($zone->id); ?>)"
                        class="w-full text-left bg-white rounded-2xl border shadow-sm p-4 transition <?php echo e($selectedZone && $selectedZone->id === $zone->id ? 'border-blue-500 ring-2 ring-blue-100' : 'border-slate-200 hover:border-slate-300'); ?>">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-slate-900"><?php echo e($zone->name); ?></p>
                                <p class="text-xs text-slate-500"><?php echo e($zone->code); ?> · <?php echo e($zone->coverage_type); ?></p>
                                <p class="text-xs text-slate-500 mt-1">
                                    <?php echo e($zone->region?->name ?? '—'); ?>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($zone->province): ?>
                                        · <?php echo e($zone->province->name); ?>

                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </p>
                                <div class="mt-3 flex flex-wrap gap-2 text-[11px] text-slate-600">
                                    <span class="rounded-full bg-slate-100 px-2 py-1"><?php echo e($zone->postal_codes_count); ?> CP</span>
                                    <span class="rounded-full bg-slate-100 px-2 py-1"><?php echo e($zone->enabled_service_rules_count); ?> services actifs</span>
                                    <span class="rounded-full bg-slate-100 px-2 py-1"><?php echo e($zone->active_employee_assignments_count); ?> employés actifs</span>
                                </div>
                            </div>
                            <div class="text-right space-y-1">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-[11px] font-semibold <?php echo e($statusPill($zone->status)); ?>">
                                    <?php echo e(ucfirst($zone->status)); ?>

                                </span>
                                <div class="text-[11px] text-slate-500"><?php echo e($zone->is_bookable ? 'Réservable' : 'Non réservable'); ?></div>
                                <div class="text-[11px] text-slate-500"><?php echo e($zone->is_visible ? 'Visible' : 'Masquée'); ?></div>
                            </div>
                        </div>
                    </button>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <div class="bg-white border rounded-2xl p-6 text-center text-gray-500 italic">
                        Aucune zone trouvée.
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

                <div><?php echo e($zones->links()); ?></div>
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
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedZone): ?>
                <div class="bg-white rounded-2xl shadow border p-5 space-y-5">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                        <div>
                            <h3 class="text-xl font-semibold text-slate-900"><?php echo e($selectedZone->name); ?></h3>
                            <p class="text-sm text-slate-500"><?php echo e($selectedZone->code); ?> · <?php echo e($selectedZone->slug); ?></p>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                <span class="inline-flex items-center px-3 py-1 rounded-full bg-slate-100 text-slate-700"><?php echo e($selectedZone->coverage_type); ?></span>
                                <span class="inline-flex items-center px-3 py-1 rounded-full <?php echo e($selectedZone->is_visible ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-500'); ?>"><?php echo e($selectedZone->is_visible ? 'Visible' : 'Masquée'); ?></span>
                                <span class="inline-flex items-center px-3 py-1 rounded-full <?php echo e($selectedZone->is_bookable ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'); ?>"><?php echo e($selectedZone->is_bookable ? 'Bookable' : 'Non bookable'); ?></span>
                                <span class="inline-flex items-center px-3 py-1 rounded-full <?php echo e($statusPill($selectedZone->status)); ?>"><?php echo e(ucfirst($selectedZone->status)); ?></span>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button type="button" wire:click="toggleZoneBookability"
                                class="inline-flex items-center px-3 py-2 rounded-xl bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition">
                                <?php echo e($selectedZone->is_bookable ? 'Rendre non bookable' : 'Rendre bookable'); ?>

                            </button>
                            <button type="button" wire:click="toggleZoneVisibility"
                                class="inline-flex items-center px-3 py-2 rounded-xl bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200 transition">
                                <?php echo e($selectedZone->is_visible ? 'Masquer' : 'Rendre visible'); ?>

                            </button>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedZone->status !== 'active'): ?>
                                <button type="button" wire:click="setZoneStatus('active')"
                                    class="inline-flex items-center px-3 py-2 rounded-xl bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 transition">
                                    Activer
                                </button>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedZone->status !== 'paused'): ?>
                                <button type="button" wire:click="setZoneStatus('paused')"
                                    class="inline-flex items-center px-3 py-2 rounded-xl bg-amber-500 text-white text-sm font-medium hover:bg-amber-600 transition">
                                    Mettre en pause
                                </button>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                        <div class="rounded-2xl bg-slate-50 border p-4">
                            <p class="text-xs uppercase tracking-wide text-slate-500">Codes postaux</p>
                            <p class="mt-2 text-2xl font-bold text-slate-900"><?php echo e($selectedZone->postal_codes_count); ?></p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 border p-4">
                            <p class="text-xs uppercase tracking-wide text-slate-500">Services actifs</p>
                            <p class="mt-2 text-2xl font-bold text-slate-900"><?php echo e($selectedZone->enabled_service_rules_count); ?></p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 border p-4">
                            <p class="text-xs uppercase tracking-wide text-slate-500">Validation manuelle</p>
                            <p class="mt-2 text-2xl font-bold text-slate-900"><?php echo e($selectedZone->manual_validation_rules_count); ?></p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 border p-4">
                            <p class="text-xs uppercase tracking-wide text-slate-500">Employés actifs</p>
                            <p class="mt-2 text-2xl font-bold text-slate-900"><?php echo e($selectedZone->active_employee_assignments_count); ?></p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 border p-4">
                            <p class="text-xs uppercase tracking-wide text-slate-500">Sites entreprise</p>
                            <p class="mt-2 text-2xl font-bold text-slate-900"><?php echo e($selectedZone->organization_sites_count); ?></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nom</label>
                            <input type="text" wire:model="name" class="w-full border-gray-300 rounded-lg shadow-sm">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-rose-600 mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Code</label>
                            <input type="text" value="<?php echo e($code); ?>" disabled class="w-full border-gray-200 bg-slate-50 rounded-lg shadow-sm text-slate-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                            <select wire:model="status" class="w-full border-gray-300 rounded-lg shadow-sm">
                                <option value="draft">Draft</option>
                                <option value="active">Active</option>
                                <option value="paused">Paused</option>
                                <option value="archived">Archivée</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Type de couverture</label>
                            <select wire:model="coverage_type" class="w-full border-gray-300 rounded-lg shadow-sm">
                                <option value="national">National</option>
                                <option value="region">Région</option>
                                <option value="province">Province</option>
                                <option value="commune">Commune</option>
                                <option value="postal_code">Code postal</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Priorité</label>
                            <input type="number" min="1" wire:model="priority" class="w-full border-gray-300 rounded-lg shadow-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Délai minimum (h)</label>
                            <input type="number" min="0" wire:model="minimum_notice_hours" class="w-full border-gray-300 rounded-lg shadow-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Capacité max / jour</label>
                            <input type="number" min="1" wire:model="maximum_daily_jobs" class="w-full border-gray-300 rounded-lg shadow-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Buffer (minutes)</label>
                            <input type="number" min="0" wire:model="time_buffer_minutes" class="w-full border-gray-300 rounded-lg shadow-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Surcharge déplacement (€)</label>
                            <input type="number" min="0" step="0.01" wire:model="travel_surcharge" class="w-full border-gray-300 rounded-lg shadow-sm">
                        </div>

                        <div class="md:col-span-2 flex flex-wrap gap-6 pt-2">
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" wire:model="is_visible" class="rounded border-gray-300 text-blue-600 shadow-sm">
                                Visible
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" wire:model="is_bookable" class="rounded border-gray-300 text-blue-600 shadow-sm">
                                Réservable
                            </label>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes internes</label>
                            <textarea rows="3" wire:model="notes" class="w-full border-gray-300 rounded-lg shadow-sm"></textarea>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 pt-2">
                        <div class="rounded-2xl border bg-slate-50 p-4">
                            <h4 class="font-semibold text-slate-900">🌍 Couverture</h4>
                            <div class="mt-3 space-y-2 text-sm text-slate-600">
                                <p><span class="font-medium text-slate-800">Pays :</span> <?php echo e($selectedZone->country?->name ?? '—'); ?></p>
                                <p><span class="font-medium text-slate-800">Région :</span> <?php echo e($selectedZone->region?->name ?? '—'); ?></p>
                                <p><span class="font-medium text-slate-800">Province :</span> <?php echo e($selectedZone->province?->name ?? '—'); ?></p>
                                <p><span class="font-medium text-slate-800">Commune :</span> <?php echo e($selectedZone->commune?->name ?? '—'); ?></p>
                            </div>
                        </div>

                        <div class="rounded-2xl border bg-slate-50 p-4">
                            <h4 class="font-semibold text-slate-900">📮 Codes postaux liés</h4>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $selectedZone->postalCodes->take(12); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $postalCode): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <span class="inline-flex items-center rounded-full bg-white border px-3 py-1 text-xs text-slate-700">
                                        <?php echo e($postalCode->code); ?> <?php echo e($postalCode->city_name); ?>

                                    </span>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                    <span class="text-sm text-slate-500 italic">Aucun code postal lié.</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedZone->postalCodes->count() > 12): ?>
                                    <span class="inline-flex items-center rounded-full bg-white border px-3 py-1 text-xs text-slate-700">
                                        +<?php echo e($selectedZone->postalCodes->count() - 12); ?> autres
                                    </span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button type="button" wire:click="saveZone"
                            class="inline-flex items-center px-4 py-2 rounded-xl bg-blue-600 text-white font-medium hover:bg-blue-700 transition">
                            Enregistrer la zone
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                    <div class="bg-white rounded-2xl shadow border p-5 space-y-4">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">🧩 Services liés</h3>
                                <p class="text-sm text-slate-500">Active, ajuste le prix, la validation manuelle et la capacité par service.</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <select wire:model="copyRulesFromZoneId" class="border-gray-300 rounded-lg shadow-sm text-sm">
                                    <option value="">Copier depuis une zone...</option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $sourceZones; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $zoneOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($zoneOption->id); ?>"><?php echo e($zoneOption->name); ?> · <?php echo e($zoneOption->code); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </select>
                                <button type="button" wire:click="copyServiceRulesFromZone"
                                    class="inline-flex items-center px-3 py-2 rounded-xl bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200 transition">
                                    Copier règles
                                </button>
                                <button type="button" wire:click="saveAllServiceRules"
                                    class="inline-flex items-center px-3 py-2 rounded-xl bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition">
                                    Tout enregistrer
                                </button>
                            </div>
                        </div>

                        <div class="space-y-4 max-h-[760px] overflow-y-auto pr-1">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $serviceRules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $serviceId => $rule): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <div class="border rounded-2xl p-4 bg-slate-50">
                                    <div class="flex items-start justify-between gap-3 mb-3">
                                        <div>
                                            <p class="font-semibold text-slate-900"><?php echo e($rule['service_name']); ?></p>
                                            <p class="text-xs text-slate-500"><?php echo e($rule['service_type'] ?: 'Type non défini'); ?></p>
                                        </div>
                                        <div class="flex flex-wrap gap-2 justify-end">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rule['requires_manual_validation']): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-700">Validation manuelle</span>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                                <input type="checkbox" wire:model="serviceRules.<?php echo e($serviceId); ?>.is_enabled" class="rounded border-gray-300 text-blue-600 shadow-sm">
                                                Actif
                                            </label>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Prix spécifique (€)</label>
                                            <input type="number" step="0.01" min="0" wire:model="serviceRules.<?php echo e($serviceId); ?>.base_price_override" class="w-full border-gray-300 rounded-lg shadow-sm">
                                        </div>

                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Multiplicateur prix</label>
                                            <input type="number" step="0.01" min="0.1" wire:model="serviceRules.<?php echo e($serviceId); ?>.price_multiplier" class="w-full border-gray-300 rounded-lg shadow-sm">
                                        </div>

                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Délai minimum (h)</label>
                                            <input type="number" min="0" wire:model="serviceRules.<?php echo e($serviceId); ?>.minimum_notice_hours" class="w-full border-gray-300 rounded-lg shadow-sm">
                                        </div>

                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Capacité / jour</label>
                                            <input type="number" min="1" wire:model="serviceRules.<?php echo e($serviceId); ?>.maximum_daily_capacity" class="w-full border-gray-300 rounded-lg shadow-sm">
                                        </div>

                                        <div class="md:col-span-2">
                                            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                                <input type="checkbox" wire:model="serviceRules.<?php echo e($serviceId); ?>.requires_manual_validation" class="rounded border-gray-300 text-blue-600 shadow-sm">
                                                Validation manuelle requise
                                            </label>
                                        </div>
                                    </div>

                                    <div class="mt-3 flex justify-end">
                                        <button type="button" wire:click="saveServiceRule(<?php echo e($serviceId); ?>)"
                                            class="inline-flex items-center px-3 py-2 rounded-xl bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition">
                                            Enregistrer le service
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <div class="bg-white rounded-2xl shadow border p-5 space-y-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">👷 Employés liés</h3>
                                <p class="text-sm text-slate-500">Assigne, priorise et maintiens les rôles primaire, secondaire ou backup.</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <select wire:model="employeeToAssign" class="w-full border-gray-300 rounded-lg shadow-sm">
                                    <option value="">— Choisir un employé —</option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $availableEmployees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $employee): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($employee->id); ?>"><?php echo e($employee->name); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </select>

                                <select wire:model="assignmentType" class="w-full border-gray-300 rounded-lg shadow-sm">
                                    <option value="primary">Primary</option>
                                    <option value="secondary">Secondary</option>
                                    <option value="backup">Backup</option>
                                </select>

                                <input type="number" min="1" wire:model="assignmentPriority" placeholder="Priorité"
                                    class="w-full border-gray-300 rounded-lg shadow-sm">

                                <input type="text" wire:model="assignmentNotes" placeholder="Notes internes"
                                    class="w-full border-gray-300 rounded-lg shadow-sm">
                            </div>

                            <div class="flex justify-end">
                                <button type="button" wire:click="assignEmployee"
                                    class="inline-flex items-center px-4 py-2 rounded-xl bg-emerald-600 text-white font-medium hover:bg-emerald-700 transition">
                                    Affecter l’employé
                                </button>
                            </div>

                            <div class="space-y-3">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $selectedZone->employeeAssignments->sortBy('coverage_priority'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $assignment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <div class="border rounded-2xl p-4 bg-slate-50 space-y-3">
                                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                            <div>
                                                <p class="font-semibold text-slate-900"><?php echo e($assignment->user?->name ?? 'Employé supprimé'); ?></p>
                                                <p class="text-sm text-slate-500">
                                                    Affectation actuelle : <?php echo e(ucfirst($assignment->assignment_type)); ?>

                                                </p>
                                            </div>

                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold <?php echo e($assignment->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'); ?>">
                                                    <?php echo e($assignment->is_active ? 'Actif' : 'Inactif'); ?>

                                                </span>

                                                <button type="button" wire:click="toggleAssignment(<?php echo e($assignment->id); ?>)"
                                                    class="inline-flex items-center px-3 py-2 rounded-xl bg-white border text-slate-700 text-sm font-medium hover:bg-slate-100 transition">
                                                    <?php echo e($assignment->is_active ? 'Désactiver' : 'Réactiver'); ?>

                                                </button>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                            <select wire:model="assignmentEdits.<?php echo e($assignment->id); ?>.assignment_type" class="w-full border-gray-300 rounded-lg shadow-sm">
                                                <option value="primary">Primary</option>
                                                <option value="secondary">Secondary</option>
                                                <option value="backup">Backup</option>
                                            </select>

                                            <input type="number" min="1" wire:model="assignmentEdits.<?php echo e($assignment->id); ?>.coverage_priority" class="w-full border-gray-300 rounded-lg shadow-sm">

                                            <input type="text" wire:model="assignmentEdits.<?php echo e($assignment->id); ?>.notes" placeholder="Notes internes" class="w-full border-gray-300 rounded-lg shadow-sm">
                                        </div>

                                        <div class="flex flex-wrap justify-end gap-2">
                                            <button type="button" wire:click="saveAssignment(<?php echo e($assignment->id); ?>)"
                                                class="inline-flex items-center px-3 py-2 rounded-xl bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition">
                                                Enregistrer l’affectation
                                            </button>
                                            <button type="button" wire:click="removeAssignment(<?php echo e($assignment->id); ?>)"
                                                class="inline-flex items-center px-3 py-2 rounded-xl bg-rose-100 text-rose-700 text-sm font-medium hover:bg-rose-200 transition">
                                                Supprimer
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                    <div class="border rounded-xl p-4 text-sm text-slate-500 italic">
                                        Aucun employé affecté à cette zone.
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl shadow border p-5 space-y-4">
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900">🕓 Historique des modifications</h3>
                                <p class="text-sm text-slate-500">Dernières actions faites sur cette zone.</p>
                            </div>

                            <div class="space-y-3">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $zoneHistory; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                    <div class="border rounded-2xl p-4 bg-slate-50">
                                        <div class="flex items-center justify-between gap-3">
                                            <p class="font-medium text-slate-900"><?php echo e(str_replace('_', ' ', $log->action)); ?></p>
                                            <p class="text-xs text-slate-500"><?php echo e($log->created_at?->format('d/m/Y H:i')); ?></p>
                                        </div>
                                        <p class="text-sm text-slate-500 mt-1">Par <?php echo e($log->user?->name ?? 'Système'); ?></p>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(is_array($log->meta) && count($log->meta)): ?>
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = collect($log->meta)->take(4); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $metaKey => $metaValue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(is_scalar($metaValue) || $metaValue === null): ?>
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full bg-white border text-[11px] text-slate-600">
                                                            <?php echo e(str_replace('_', ' ', (string) $metaKey)); ?> : <?php echo e($metaValue === null ? '—' : $metaValue); ?>

                                                        </span>
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </div>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                    <div class="border rounded-xl p-4 text-sm text-slate-500 italic">
                                        Aucun historique pour cette zone.
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-2xl shadow border p-8 text-center text-slate-500">
                    Aucune zone sélectionnée.
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
</div>
<?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/admin/gestion-zones.blade.php ENDPATH**/ ?>