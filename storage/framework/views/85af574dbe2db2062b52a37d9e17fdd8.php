<div class="space-y-6">
    <?php if (isset($component)) { $__componentOriginal12a5063a2f74bc3084facd42cc51c606 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal12a5063a2f74bc3084facd42cc51c606 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-shell','data' => ['eyebrow' => 'Finance','title' => 'Finance center','subtitle' => 'Devis, factures, suivi d’encaissement et marge estimée pilotés depuis les rendez-vous.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('page-shell'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['eyebrow' => 'Finance','title' => 'Finance center','subtitle' => 'Devis, factures, suivi d’encaissement et marge estimée pilotés depuis les rendez-vous.']); ?>
         <?php $__env->slot('actions', null, []); ?> 
            <button wire:click="syncFilteredDocuments" class="cu-btn-secondary">Sync filtres</button>
            <button wire:click="syncAllDocuments" class="cu-btn-primary">Sync globale</button>
            <button wire:click="exportFinanceCsv" class="cu-btn-secondary">Export CSV</button>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedRendezVous): ?>
                <button wire:click="downloadQuotePdf(<?php echo e($selectedRendezVous->id); ?>)" class="cu-btn-secondary">Devis PDF</button>
                <button wire:click="downloadInvoicePdf(<?php echo e($selectedRendezVous->id); ?>)" class="cu-btn-secondary">Facture PDF</button>
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

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('success')): ?>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?php echo e(session('success')); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('warning')): ?>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700"><?php echo e(session('warning')); ?></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
        <?php if (isset($component)) { $__componentOriginala4ae059936bc185e758290466e2179c1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala4ae059936bc185e758290466e2179c1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.kpi-card','data' => ['title' => 'CA estimé HTVA','value' => '€ '.number_format($kpis['total_htva'], 2, ',', ' '),'tone' => 'blue','icon' => '💼']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('kpi-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'CA estimé HTVA','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('€ '.number_format($kpis['total_htva'], 2, ',', ' ')),'tone' => 'blue','icon' => '💼']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.kpi-card','data' => ['title' => 'Entreprise HTVA','value' => '€ '.number_format($kpis['entreprise_htva'], 2, ',', ' '),'tone' => 'amber','icon' => '🏢']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('kpi-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Entreprise HTVA','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('€ '.number_format($kpis['entreprise_htva'], 2, ',', ' ')),'tone' => 'amber','icon' => '🏢']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.kpi-card','data' => ['title' => 'À facturer HTVA','value' => '€ '.number_format($kpis['to_invoice_htva'], 2, ',', ' '),'tone' => 'slate','icon' => '🧾']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('kpi-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'À facturer HTVA','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('€ '.number_format($kpis['to_invoice_htva'], 2, ',', ' ')),'tone' => 'slate','icon' => '🧾']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.kpi-card','data' => ['title' => 'Marge estimée','value' => '€ '.number_format($kpis['margin_estimate'], 2, ',', ' '),'tone' => 'green','icon' => '📈']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('kpi-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Marge estimée','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('€ '.number_format($kpis['margin_estimate'], 2, ',', ' ')),'tone' => 'green','icon' => '📈']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.kpi-card','data' => ['title' => 'Solde à encaisser','value' => '€ '.number_format($kpis['outstanding_balance'], 2, ',', ' '),'tone' => 'rose','icon' => '⏱️']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('kpi-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Solde à encaisser','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('€ '.number_format($kpis['outstanding_balance'], 2, ',', ' ')),'tone' => 'rose','icon' => '⏱️']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.kpi-card','data' => ['title' => 'Factures en retard','value' => $kpis['overdue_count'],'hint' => '€ '.number_format($kpis['overdue_balance'], 2, ',', ' '),'tone' => 'red','icon' => '🚨']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('kpi-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Factures en retard','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($kpis['overdue_count']),'hint' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('€ '.number_format($kpis['overdue_balance'], 2, ',', ' ')),'tone' => 'red','icon' => '🚨']); ?>
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

    <div class="grid gap-4 lg:grid-cols-4">
        <?php if (isset($component)) { $__componentOriginalf3f7946f558699cf27352737986448eb = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf3f7946f558699cf27352737986448eb = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.filter-panel','data' => ['title' => 'Filtres finance','subtitle' => 'Recherche, période, marché, zone, service et état de paiement.','class' => 'lg:col-span-3']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('filter-panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Filtres finance','subtitle' => 'Recherche, période, marché, zone, service et état de paiement.','class' => 'lg:col-span-3']); ?>
            <div class="grid gap-3 md:grid-cols-4 xl:grid-cols-9">
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Recherche" class="rounded-xl border-slate-300 text-sm shadow-sm xl:col-span-2">
                <input wire:model.live="dateFrom" type="date" class="rounded-xl border-slate-300 text-sm shadow-sm">
                <input wire:model.live="dateTo" type="date" class="rounded-xl border-slate-300 text-sm shadow-sm">
                <select wire:model.live="status" class="rounded-xl border-slate-300 text-sm shadow-sm">
                    <option value="">Tous statuts</option>
                    <option value="en_attente">En attente</option>
                    <option value="confirme">Confirmé</option>
                    <option value="en_route">En route</option>
                    <option value="sur_place">Sur place</option>
                    <option value="termine">Terminé</option>
                    <option value="annule">Annulé</option>
                    <option value="refuse">Refusé</option>
                </select>
                <select wire:model.live="market" class="rounded-xl border-slate-300 text-sm shadow-sm">
                    <option value="">Tous marchés</option>
                    <option value="particulier">Particulier</option>
                    <option value="entreprise">Entreprise</option>
                </select>
                <select wire:model.live="zoneId" class="rounded-xl border-slate-300 text-sm shadow-sm">
                    <option value="">Toutes zones</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $this->zones; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $zone): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($zone->id); ?>"><?php echo e($zone->name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </select>
                <select wire:model.live="serviceId" class="rounded-xl border-slate-300 text-sm shadow-sm">
                    <option value="">Tous services</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $this->services; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($service->id); ?>"><?php echo e($service->name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </select>
                <select wire:model.live="organizationId" class="rounded-xl border-slate-300 text-sm shadow-sm">
                    <option value="">Toutes entreprises</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $this->organizations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $organization): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($organization->id); ?>"><?php echo e($organization->name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </select>
                <select wire:model.live="viewMode" class="rounded-xl border-slate-300 text-sm shadow-sm">
                    <option value="all">Tout</option>
                    <option value="quotes">Devis</option>
                    <option value="invoices">Factures</option>
                    <option value="cancelled">Annulations</option>
                </select>
                <select wire:model.live="paymentFilter" class="rounded-xl border-slate-300 text-sm shadow-sm xl:col-span-2">
                    <option value="">Tous paiements</option>
                    <option value="quoted_only">Devis sans facture</option>
                    <option value="pending">À encaisser</option>
                    <option value="paid">Payé</option>
                    <option value="overdue">En retard</option>
                </select>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-slate-500">
                            <th class="py-3 pr-4">Réf.</th>
                            <th class="py-3 pr-4">Date</th>
                            <th class="py-3 pr-4">Client</th>
                            <th class="py-3 pr-4">Service</th>
                            <th class="py-3 pr-4">Zone</th>
                            <th class="py-3 pr-4">Finance</th>
                            <th class="py-3 pr-4 text-right">HTVA</th>
                            <th class="py-3 pr-4 text-right">Solde</th>
                            <th class="py-3 pr-4 text-right">Marge</th>
                            <th class="py-3 pr-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr class="<?php echo e($selectedRendezVous && $selectedRendezVous->id === $row->id ? 'bg-slate-50' : 'bg-white'); ?>">
                                <td class="py-3 pr-4 font-medium text-slate-800"><?php echo e($row->booking_reference ?: 'RDV-'.$row->id); ?></td>
                                <td class="py-3 pr-4 text-slate-600"><?php echo e(optional($row->date)->format('d/m/Y')); ?><br><span class="text-xs text-slate-400"><?php echo e(substr((string) $row->heure, 0, 5)); ?></span></td>
                                <td class="py-3 pr-4 text-slate-600">
                                    <div><?php echo e($row->organizationAccount?->name ?: $row->client?->name); ?></div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($row->organizationSite): ?>
                                        <div class="text-xs text-slate-400"><?php echo e($row->organizationSite->name); ?></div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                                <td class="py-3 pr-4 text-slate-600"><?php echo e($row->service_display_name); ?></td>
                                <td class="py-3 pr-4 text-slate-600"><?php echo e($row->serviceZone?->name ?: '—'); ?></td>
                                <td class="py-3 pr-4">
                                    <div class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 inline-block"><?php echo e($this->financeStage($row)); ?></div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($row->financeInvoice?->due_at): ?>
                                        <div class="mt-1 text-xs text-slate-400">Échéance <?php echo e($row->financeInvoice->due_at->format('d/m/Y')); ?></div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </td>
                                <td class="py-3 pr-4 text-right font-semibold text-slate-800">€ <?php echo e(number_format($this->amountHtva($row), 2, ',', ' ')); ?></td>
                                <td class="py-3 pr-4 text-right text-slate-600">€ <?php echo e(number_format((float) ($row->financeInvoice?->balance_due ?? 0), 2, ',', ' ')); ?></td>
                                <td class="py-3 pr-4 text-right text-slate-600">€ <?php echo e(number_format($this->marginEstimate($row), 2, ',', ' ')); ?></td>
                                <td class="py-3 pr-4 text-right">
                                    <button wire:click="selectRendezVous(<?php echo e($row->id); ?>)" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700">Ouvrir</button>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="10" class="py-8 text-center text-slate-400">Aucune donnée financière pour ces filtres.</td>
                            </tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <?php echo e($rows->links()); ?>

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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.app-card','data' => ['title' => 'Synthèse','subtitle' => 'Vue rapide du rendez-vous sélectionné et des actions disponibles.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('app-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Synthèse','subtitle' => 'Vue rapide du rendez-vous sélectionné et des actions disponibles.']); ?>
            <h2 class="text-lg font-semibold text-slate-800">Document sélectionné</h2>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedRendezVous): ?>
                <div class="mt-4 space-y-3 text-sm text-slate-600">
                    <div>
                        <div class="text-xs uppercase tracking-wide text-slate-400">Référence</div>
                        <div class="font-medium text-slate-800"><?php echo e($selectedRendezVous->booking_reference ?: 'RDV-'.$selectedRendezVous->id); ?></div>
                    </div>
                    <div class="grid gap-2">
                        <button wire:click="ensureQuoteDocument(<?php echo e($selectedRendezVous->id); ?>)" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Sync devis</button>
                        <button wire:click="ensureInvoiceDocument(<?php echo e($selectedRendezVous->id); ?>)" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Sync facture</button>
                        <button wire:click="issueInvoiceNow(<?php echo e($selectedRendezVous->id); ?>)" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Émettre facture</button>
                        <button wire:click="sendInvoiceReminderNow(<?php echo e($selectedRendezVous->id); ?>, 'gentle')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Relance douce</button>
                        <button wire:click="sendInvoiceReminderNow(<?php echo e($selectedRendezVous->id); ?>, 'overdue')" class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700">Relance retard</button>
                    </div>

                    <div class="rounded-2xl border border-slate-200 p-3">
                        <div class="text-xs uppercase tracking-wide text-slate-400">Montants</div>
                        <div class="mt-2 space-y-1">
                            <div>HTVA : <span class="font-semibold text-slate-800">€ <?php echo e(number_format($this->amountHtva($selectedRendezVous), 2, ',', ' ')); ?></span></div>
                            <div>TVA : <span class="font-semibold text-slate-800">€ <?php echo e(number_format($this->amountTva($selectedRendezVous), 2, ',', ' ')); ?></span></div>
                            <div>TVAC : <span class="font-semibold text-slate-800">€ <?php echo e(number_format($this->amountTvac($selectedRendezVous), 2, ',', ' ')); ?></span></div>
                            <div>Marge estimée : <span class="font-semibold text-slate-800">€ <?php echo e(number_format($this->marginEstimate($selectedRendezVous), 2, ',', ' ')); ?></span></div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 p-3">
                        <div class="text-xs uppercase tracking-wide text-slate-400">Documents</div>
                        <div class="mt-2 space-y-1">
                            <div>Devis : <span class="font-medium text-slate-800"><?php echo e($selectedRendezVous->financeQuote?->quote_number ?: '—'); ?></span></div>
                            <div>Facture : <span class="font-medium text-slate-800"><?php echo e($selectedRendezVous->financeInvoice?->invoice_number ?: '—'); ?></span></div>
                            <div>Statut facture : <span class="font-medium text-slate-800"><?php echo e($selectedRendezVous->financeInvoice?->status ?: '—'); ?></span></div>
                            <div>Solde : <span class="font-medium text-slate-800">€ <?php echo e(number_format((float) ($selectedRendezVous->financeInvoice?->balance_due ?? 0), 2, ',', ' ')); ?></span></div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 p-3">
                        <div class="text-xs uppercase tracking-wide text-slate-400">Paiement manuel</div>
                        <div class="mt-2 grid gap-2">
                            <input wire:model.live="manualPaymentAmount" type="number" step="0.01" min="0" class="rounded-xl border-slate-300 text-sm shadow-sm" placeholder="Montant">
                            <select wire:model.live="manualPaymentMethod" class="rounded-xl border-slate-300 text-sm shadow-sm">
                                <option value="manual">Manuel</option>
                                <option value="bank_transfer">Virement</option>
                                <option value="cash">Cash</option>
                                <option value="card">Carte</option>
                            </select>
                            <button wire:click="recordPartialPaymentNow(<?php echo e($selectedRendezVous->id); ?>)" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Enregistrer paiement partiel</button>
                            <button wire:click="markInvoicePaidNow(<?php echo e($selectedRendezVous->id); ?>)" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Solder la facture</button>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 p-3">
                        <div class="text-xs uppercase tracking-wide text-slate-400">Contexte corporate</div>
                        <div class="mt-2 space-y-1">
                            <div>PO : <?php echo e(data_get($selectedRendezVous->pricing_snapshot, 'corporate_context.purchase_order_reference', '—')); ?></div>
                            <div>Centre de coût : <?php echo e(data_get($selectedRendezVous->pricing_snapshot, 'corporate_context.cost_center', '—')); ?></div>
                            <div>Échéance : <?php echo e($selectedRendezVous->financeInvoice?->due_at?->format('d/m/Y') ?: '—'); ?></div>
                            <div>Dernière relance : <?php echo e(optional($selectedRendezVous->financeInvoice?->reminders?->sortByDesc('created_at')->first()?->created_at)->format('d/m/Y H:i') ?: '—'); ?></div>
                        </div>
                    </div>

                    <div class="grid gap-2 pt-2">
                        <button wire:click="downloadQuotePdf(<?php echo e($selectedRendezVous->id); ?>)" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Télécharger devis</button>
                        <button wire:click="downloadInvoicePdf(<?php echo e($selectedRendezVous->id); ?>)" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Télécharger facture</button>
                    </div>
                </div>
            <?php else: ?>
                <div class="mt-4 text-sm text-slate-400">Sélectionne un rendez-vous pour générer un devis, suivre l’encaissement et piloter la marge.</div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
</div>
<?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/admin/finance-center.blade.php ENDPATH**/ ?>