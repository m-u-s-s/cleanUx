<div class="space-y-8">
    <?php if (isset($component)) { $__componentOriginal12a5063a2f74bc3084facd42cc51c606 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal12a5063a2f74bc3084facd42cc51c606 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-shell','data' => ['eyebrow' => 'Entreprise','title' => 'Comptes entreprise','subtitle' => 'Gère les comptes corporate, les sites, les contacts et les règles contractuelles de base.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('page-shell'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['eyebrow' => 'Entreprise','title' => 'Comptes entreprise','subtitle' => 'Gère les comptes corporate, les sites, les contacts et les règles contractuelles de base.']); ?>
         <?php $__env->slot('actions', null, []); ?> 
            <button wire:click="resetAccountForm" class="cu-btn-primary">Nouveau compte</button>
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
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            <?php echo e(session('success')); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
        <div class="xl:col-span-4 space-y-6">
            <?php if (isset($component)) { $__componentOriginalf3f7946f558699cf27352737986448eb = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf3f7946f558699cf27352737986448eb = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.filter-panel','data' => ['title' => 'Filtres entreprise','subtitle' => 'Recherche, statut, type et zone.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('filter-panel'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Filtres entreprise','subtitle' => 'Recherche, statut, type et zone.']); ?>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-1 gap-3">
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Recherche entreprise, TVA, email..." class="rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">

                    <select wire:model.live="status" class="rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Tous statuts</option>
                        <option value="active">Actif</option>
                        <option value="inactive">Inactif</option>
                        <option value="prospect">Prospect</option>
                        <option value="suspended">Suspendu</option>
                    </select>

                    <select wire:model.live="type" class="rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Tous types</option>
                        <option value="entreprise">Entreprise</option>
                        <option value="business">Business</option>
                        <option value="partner">Partner</option>
                        <option value="individual">Individual</option>
                    </select>

                    <select wire:model.live="zoneFilter" class="rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Toutes zones</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $zones; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $zone): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($zone->id); ?>"><?php echo e($zone->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.app-card','data' => ['title' => 'Liste entreprises','subtitle' => $accounts->total().' résultats','padding' => 'p-0 overflow-hidden']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('app-card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Liste entreprises','subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($accounts->total().' résultats'),'padding' => 'p-0 overflow-hidden']); ?>
                <div class="divide-y divide-slate-100">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $accounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <button wire:click="selectAccount(<?php echo e($account->id); ?>)" class="w-full text-left px-5 py-4 hover:bg-slate-50 transition <?php echo e($selectedAccount?->id === $account->id ? 'bg-blue-50' : ''); ?>">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-semibold text-slate-900"><?php echo e($account->name); ?></p>
                                    <p class="text-xs text-slate-500 mt-1">
                                        <?php echo e($account->legal_name ?: $account->slug); ?>

                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($account->tva_number): ?>
                                            · TVA <?php echo e($account->tva_number); ?>

                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </p>
                                </div>
                                <span class="inline-flex px-2 py-1 rounded-full text-xs <?php echo e($account->status === 'active' ? 'bg-green-100 text-green-700' : ($account->status === 'prospect' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-700')); ?>">
                                    <?php echo e(ucfirst($account->status)); ?>

                                </span>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-600">
                                <span class="px-2 py-1 rounded-full bg-slate-100"><?php echo e(ucfirst($account->type)); ?></span>
                                <span class="px-2 py-1 rounded-full bg-slate-100"><?php echo e($account->sites_count); ?> sites</span>
                                <span class="px-2 py-1 rounded-full bg-slate-100"><?php echo e($account->users_count); ?> utilisateurs</span>
                                <span class="px-2 py-1 rounded-full bg-slate-100"><?php echo e($account->rendez_vous_count); ?> RDV</span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($account->is_key_account): ?>
                                    <span class="px-2 py-1 rounded-full bg-purple-100 text-purple-700">Key account</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </button>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <div class="px-5 py-8 text-sm text-slate-500">Aucun compte entreprise trouvé.</div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div class="px-5 py-4 border-t border-slate-100">
                    <?php echo e($accounts->links()); ?>

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

        <div class="xl:col-span-4 space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-900">Fiche entreprise</h2>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedAccount): ?>
                        <span class="text-xs text-slate-500">#<?php echo e($selectedAccount->id); ?></span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Nom commercial</label>
                        <input wire:model.defer="name" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-red-600 mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Raison sociale</label>
                        <input wire:model.defer="legal_name" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Slug</label>
                        <input wire:model.defer="slug" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['slug'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-red-600 mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">TVA</label>
                        <input wire:model.defer="tva_number" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['tva_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-red-600 mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
                        <select wire:model.defer="account_type" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                            <option value="entreprise">Entreprise</option>
                            <option value="business">Business</option>
                            <option value="partner">Partner</option>
                            <option value="individual">Individual</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Statut</label>
                        <select wire:model.defer="account_status" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                            <option value="active">Actif</option>
                            <option value="inactive">Inactif</option>
                            <option value="prospect">Prospect</option>
                            <option value="suspended">Suspendu</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                        <input wire:model.defer="email" type="email" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Téléphone</label>
                        <input wire:model.defer="phone" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Email facturation</label>
                        <input wire:model.defer="billing_email" type="email" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Code postal</label>
                        <input wire:model.defer="postal_code" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Adresse</label>
                        <input wire:model.defer="address_line_1" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Complément</label>
                        <input wire:model.defer="address_line_2" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Ville</label>
                        <input wire:model.defer="city" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-4">
                    <h3 class="font-semibold text-slate-900 mb-3">Règles contractuelles</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Référence contrat</label>
                            <input wire:model.defer="contract_reference" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Profil tarifaire</label>
                            <input wire:model.defer="pricing_profile" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500" placeholder="standard, négocié, key-account...">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">SLA (heures)</label>
                            <input wire:model.defer="sla_hours" type="number" min="0" step="0.5" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Zone prioritaire</label>
                            <select wire:model.defer="priority_zone_id" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Aucune</option>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $zones; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $zone): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($zone->id); ?>"><?php echo e($zone->name); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Mode d’approbation</label>
                            <select wire:model.defer="approval_mode" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                <option value="auto">Automatique</option>
                                <option value="manual">Approbation corporate</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Centre de coût par défaut</label>
                            <input wire:model.defer="default_cost_center" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Remise négociée (%)</label>
                            <input wire:model.defer="negotiated_discount_percent" type="number" min="0" max="100" step="0.01" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                    <label class="inline-flex items-center gap-2"><input wire:model.defer="is_multisite" type="checkbox" class="rounded border-slate-300 text-blue-600"> Multi-sites</label>
                    <label class="inline-flex items-center gap-2"><input wire:model.defer="is_key_account" type="checkbox" class="rounded border-slate-300 text-blue-600"> Key account</label>
                    <label class="inline-flex items-center gap-2"><input wire:model.defer="purchase_order_required" type="checkbox" class="rounded border-slate-300 text-blue-600"> PO obligatoire</label>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                    <textarea wire:model.defer="notes" rows="4" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500"></textarea>
                </div>

                <button wire:click="saveAccount" class="w-full inline-flex justify-center items-center px-4 py-3 rounded-xl bg-blue-600 text-white font-semibold hover:bg-blue-700 transition">
                    Enregistrer le compte
                </button>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-900">Utilisateurs liés</h2>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedAccount): ?>
                        <span class="text-xs text-slate-500"><?php echo e($selectedAccount->users->count()); ?> liés</span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedAccount): ?>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <select wire:model="user_to_attach" class="md:col-span-2 rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Sélectionner un utilisateur</option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $availableUsers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($user->id); ?>"><?php echo e($user->name); ?> · <?php echo e($user->email); ?> · <?php echo e($user->role_label); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </select>
                        <select wire:model="user_role_mode" class="rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                            <option value="keep">Garder rôle</option>
                            <option value="entreprise">Passer en entreprise</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Scope sites</label>
                            <select wire:model="user_site_scope_mode" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                <option value="all_sites">Tous les sites</option>
                                <option value="selected_sites">Sites sélectionnés</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Sites autorisés</label>
                            <select wire:model="user_allowed_site_ids" multiple class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500 min-h-[110px]">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ($selectedAccount?->sites ?? collect()); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $site): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($site->id); ?>"><?php echo e($site->name); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <button wire:click="attachUser" class="w-full inline-flex justify-center items-center px-4 py-2.5 rounded-xl bg-slate-900 text-white font-semibold hover:bg-slate-800 transition">
                        Rattacher l’utilisateur
                    </button>

                    <div class="space-y-3">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $selectedAccount->users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <div class="rounded-2xl border border-slate-200 p-4 flex items-center justify-between gap-4">
                                <div>
                                    <p class="font-semibold text-slate-900"><?php echo e($user->name); ?></p>
                                    <p class="text-xs text-slate-500"><?php echo e($user->email); ?> · <?php echo e($user->role_label); ?></p>
                                    <p class="text-xs text-slate-400">Scope sites : <?php echo e(data_get($user->metadata, "entreprise_site_scope.mode", "all_sites") === "selected_sites" ? "Sites sélectionnés" : "Tous les sites"); ?></p>
                                </div>
                                <button wire:click="detachUser(<?php echo e($user->id); ?>)" class="px-3 py-2 rounded-xl bg-red-50 text-red-700 text-sm font-medium hover:bg-red-100">
                                    Détacher
                                </button>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <div class="rounded-2xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">Aucun utilisateur rattaché.</div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="rounded-2xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">Sélectionne une entreprise pour gérer ses utilisateurs.</div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <div class="xl:col-span-4 space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-slate-900">Sites entreprise</h2>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedAccount): ?>
                        <button wire:click="resetSiteForm" class="text-sm text-blue-600 hover:text-blue-800">Nouveau site</button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedAccount): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Nom du site</label>
                            <input wire:model.defer="site_name" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['site_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-red-600 mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Code site</label>
                            <input wire:model.defer="site_code" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['site_code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-red-600 mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Contact site</label>
                            <input wire:model.defer="site_contact_name" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Email site</label>
                            <input wire:model.defer="site_email" type="email" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Téléphone site</label>
                            <input wire:model.defer="site_phone" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Code postal</label>
                            <input wire:model.defer="site_postal_code" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Adresse</label>
                            <input wire:model.defer="site_address_line_1" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Complément</label>
                            <input wire:model.defer="site_address_line_2" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Ville</label>
                            <input wire:model.defer="site_city" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Zone</label>
                            <select wire:model.defer="site_zone_id" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Auto depuis code postal</option>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $zones; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $zone): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($zone->id); ?>"><?php echo e($zone->name); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Approbation site</label>
                            <select wire:model.defer="site_approval_mode" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                <option value="inherit">Hériter du compte</option>
                                <option value="auto">Auto</option>
                                <option value="manual">Approbation corporate</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Centre de coût site</label>
                            <input wire:model.defer="site_default_cost_center" type="text" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Consignes d’accès</label>
                        <textarea wire:model.defer="site_access_instructions" rows="3" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                        <label class="inline-flex items-center gap-2"><input wire:model.defer="site_is_primary" type="checkbox" class="rounded border-slate-300 text-blue-600"> Site principal</label>
                        <label class="inline-flex items-center gap-2"><input wire:model.defer="site_is_active" type="checkbox" class="rounded border-slate-300 text-blue-600"> Site actif</label>
                        <label class="inline-flex items-center gap-2"><input wire:model.defer="site_purchase_order_required" type="checkbox" class="rounded border-slate-300 text-blue-600"> PO obligatoire</label>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <button wire:click="saveSite" class="inline-flex justify-center items-center px-4 py-3 rounded-xl bg-blue-600 text-white font-semibold hover:bg-blue-700 transition">
                            Enregistrer le site
                        </button>
                        <button wire:click="resetSiteForm" class="inline-flex justify-center items-center px-4 py-3 rounded-xl bg-slate-100 text-slate-700 font-semibold hover:bg-slate-200 transition">
                            Réinitialiser
                        </button>
                    </div>

                    <div class="space-y-3 pt-2 border-t border-slate-100">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $selectedAccount->sites->sortByDesc('is_primary'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $site): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <div class="rounded-2xl border border-slate-200 p-4 space-y-3">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="font-semibold text-slate-900"><?php echo e($site->name); ?></p>
                                        <p class="text-xs text-slate-500"><?php echo e($site->site_code ?: 'Sans code'); ?> · <?php echo e($site->postal_code ?: '-'); ?> <?php echo e($site->city ?: ''); ?></p>
                                    </div>
                                    <div class="flex gap-2">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($site->is_primary): ?>
                                            <span class="inline-flex px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-700">Principal</span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($site->is_active): ?>
                                            <span class="inline-flex px-2 py-1 rounded-full text-xs bg-green-100 text-green-700">Actif</span>
                                        <?php else: ?>
                                            <span class="inline-flex px-2 py-1 rounded-full text-xs bg-red-100 text-red-700">Inactif</span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                </div>

                                <div class="text-xs text-slate-600 space-y-1">
                                    <p><span class="font-medium">Zone :</span> <?php echo e($site->serviceZone?->name ?: 'Non résolue'); ?></p>
                                    <p><span class="font-medium">Contact :</span> <?php echo e($site->contact_name ?: '-'); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($site->email): ?>· <?php echo e($site->email); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
                                    <p><span class="font-medium">Approbation :</span> <?php echo e(data_get($site->metadata, "approval_mode", "inherit")); ?></p>
                                    <p><span class="font-medium">Centre de coût :</span> <?php echo e(data_get($site->metadata, "default_cost_center", "—")); ?></p>
                                </div>

                                <div class="flex gap-2">
                                    <button wire:click="editSite(<?php echo e($site->id); ?>)" class="px-3 py-2 rounded-xl bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">Modifier</button>
                                    <button wire:click="deleteSite(<?php echo e($site->id); ?>)" wire:confirm="Supprimer ce site ?" class="px-3 py-2 rounded-xl bg-red-50 text-red-700 text-sm font-medium hover:bg-red-100">Supprimer</button>
                                </div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <div class="rounded-2xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">Aucun site enregistré.</div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="rounded-2xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">Sélectionne une entreprise pour gérer ses sites.</div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-4">
                <h2 class="text-lg font-semibold text-slate-900">Historique</h2>
                <div class="space-y-3">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $logs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-medium text-slate-900"><?php echo e(str_replace(['.', '_'], ' ', $log->action)); ?></p>
                                <span class="text-xs text-slate-500"><?php echo e($log->created_at?->format('d/m/Y H:i')); ?></span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">Par <?php echo e($log->user?->name ?: 'Système'); ?></p>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <div class="rounded-2xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">Aucun historique pour cette entreprise.</div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/admin/gestion-entreprises.blade.php ENDPATH**/ ?>