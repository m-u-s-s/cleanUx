<?php
$statsJour = $statsJour ?? [
'total' => 0,
'a_faire' => 0,
'en_cours' => 0,
'terminees' => 0,
'refusees' => 0,
];

$missionsDuJour = $missionsDuJour ?? collect();
$historiqueRecent = $historiqueRecent ?? collect();
$prochaineMission = $prochaineMission ?? null;
?>

<div class="space-y-6">
    <?php if (isset($component)) { $__componentOriginalc8dcec789990941345d9d6080d414298 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc8dcec789990941345d9d6080d414298 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.active-sessions','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('active-sessions'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc8dcec789990941345d9d6080d414298)): ?>
<?php $attributes = $__attributesOriginalc8dcec789990941345d9d6080d414298; ?>
<?php unset($__attributesOriginalc8dcec789990941345d9d6080d414298); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc8dcec789990941345d9d6080d414298)): ?>
<?php $component = $__componentOriginalc8dcec789990941345d9d6080d414298; ?>
<?php unset($__componentOriginalc8dcec789990941345d9d6080d414298); ?>
<?php endif; ?>

    <?php if (isset($component)) { $__componentOriginal12a5063a2f74bc3084facd42cc51c606 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal12a5063a2f74bc3084facd42cc51c606 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.page-shell','data' => ['eyebrow' => 'Portail employé','title' => 'Ma journée','subtitle' => 'Vue rapide de vos missions, actions prioritaires et historique récent.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('page-shell'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['eyebrow' => 'Portail employé','title' => 'Ma journée','subtitle' => 'Vue rapide de vos missions, actions prioritaires et historique récent.']); ?>
         <?php $__env->slot('actions', null, []); ?> 
            <?php if (isset($component)) { $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.badge','data' => ['label' => $statsJour['total'] . ' mission(s) aujourd’hui','tone' => 'blue','icon' => '📅']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('ui.badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($statsJour['total'] . ' mission(s) aujourd’hui'),'tone' => 'blue','icon' => '📅']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $attributes = $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $component = $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.badge','data' => ['label' => $statsJour['terminees'] . ' terminée(s)','tone' => 'green','icon' => '✅']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('ui.badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($statsJour['terminees'] . ' terminée(s)'),'tone' => 'green','icon' => '✅']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $attributes = $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $component = $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginal897b42a47ecd20a68e8cc0d392b7acfd = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal897b42a47ecd20a68e8cc0d392b7acfd = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.action-button','data' => ['href' => route('employe.missions'),'icon' => '📋']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('ui.action-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('employe.missions')),'icon' => '📋']); ?>Toutes mes missions <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal897b42a47ecd20a68e8cc0d392b7acfd)): ?>
<?php $attributes = $__attributesOriginal897b42a47ecd20a68e8cc0d392b7acfd; ?>
<?php unset($__attributesOriginal897b42a47ecd20a68e8cc0d392b7acfd); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal897b42a47ecd20a68e8cc0d392b7acfd)): ?>
<?php $component = $__componentOriginal897b42a47ecd20a68e8cc0d392b7acfd; ?>
<?php unset($__componentOriginal897b42a47ecd20a68e8cc0d392b7acfd); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginal897b42a47ecd20a68e8cc0d392b7acfd = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal897b42a47ecd20a68e8cc0d392b7acfd = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.action-button','data' => ['href' => route('employe.historique'),'icon' => '🕘']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('ui.action-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('employe.historique')),'icon' => '🕘']); ?>Mon historique <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal897b42a47ecd20a68e8cc0d392b7acfd)): ?>
<?php $attributes = $__attributesOriginal897b42a47ecd20a68e8cc0d392b7acfd; ?>
<?php unset($__attributesOriginal897b42a47ecd20a68e8cc0d392b7acfd); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal897b42a47ecd20a68e8cc0d392b7acfd)): ?>
<?php $component = $__componentOriginal897b42a47ecd20a68e8cc0d392b7acfd; ?>
<?php unset($__componentOriginal897b42a47ecd20a68e8cc0d392b7acfd); ?>
<?php endif; ?>
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

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()->canReceiveStripeConnectPayments()): ?>
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">
        Votre compte de paiement est actif. Vous pouvez recevoir vos reversements.
    </div>
    <?php else: ?>
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-800">
        <p class="font-semibold">Paiement prestataire non configuré</p>
        <p class="text-sm mt-1">
            Configurez Stripe Connect pour recevoir automatiquement vos paiements.
        </p>

        <a
            href="<?php echo e(route('employe.stripe-connect.start')); ?>"
            class="inline-flex mt-3 rounded-xl bg-slate-900 px-4 py-2 text-white">
            Configurer mes paiements
        </a>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="grid grid-cols-2 gap-4 xl:grid-cols-5">
        <?php if (isset($component)) { $__componentOriginal01e87901d1d4257641ca1465090a996e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal01e87901d1d4257641ca1465090a996e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.stat','data' => ['title' => 'Total','value' => $statsJour['total'],'tone' => 'slate','icon' => '📦','hint' => 'Toutes les missions du jour']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('ui.stat'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Total','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($statsJour['total']),'tone' => 'slate','icon' => '📦','hint' => 'Toutes les missions du jour']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal01e87901d1d4257641ca1465090a996e)): ?>
<?php $attributes = $__attributesOriginal01e87901d1d4257641ca1465090a996e; ?>
<?php unset($__attributesOriginal01e87901d1d4257641ca1465090a996e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal01e87901d1d4257641ca1465090a996e)): ?>
<?php $component = $__componentOriginal01e87901d1d4257641ca1465090a996e; ?>
<?php unset($__componentOriginal01e87901d1d4257641ca1465090a996e); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginal01e87901d1d4257641ca1465090a996e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal01e87901d1d4257641ca1465090a996e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.stat','data' => ['title' => 'À faire','value' => $statsJour['a_faire'],'tone' => 'amber','icon' => '⏳','hint' => 'Missions encore à démarrer']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('ui.stat'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'À faire','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($statsJour['a_faire']),'tone' => 'amber','icon' => '⏳','hint' => 'Missions encore à démarrer']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal01e87901d1d4257641ca1465090a996e)): ?>
<?php $attributes = $__attributesOriginal01e87901d1d4257641ca1465090a996e; ?>
<?php unset($__attributesOriginal01e87901d1d4257641ca1465090a996e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal01e87901d1d4257641ca1465090a996e)): ?>
<?php $component = $__componentOriginal01e87901d1d4257641ca1465090a996e; ?>
<?php unset($__componentOriginal01e87901d1d4257641ca1465090a996e); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginal01e87901d1d4257641ca1465090a996e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal01e87901d1d4257641ca1465090a996e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.stat','data' => ['title' => 'En cours','value' => $statsJour['en_cours'],'tone' => 'blue','icon' => '🚚','hint' => 'Missions déjà lancées']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('ui.stat'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'En cours','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($statsJour['en_cours']),'tone' => 'blue','icon' => '🚚','hint' => 'Missions déjà lancées']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal01e87901d1d4257641ca1465090a996e)): ?>
<?php $attributes = $__attributesOriginal01e87901d1d4257641ca1465090a996e; ?>
<?php unset($__attributesOriginal01e87901d1d4257641ca1465090a996e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal01e87901d1d4257641ca1465090a996e)): ?>
<?php $component = $__componentOriginal01e87901d1d4257641ca1465090a996e; ?>
<?php unset($__componentOriginal01e87901d1d4257641ca1465090a996e); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginal01e87901d1d4257641ca1465090a996e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal01e87901d1d4257641ca1465090a996e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.stat','data' => ['title' => 'Terminées','value' => $statsJour['terminees'],'tone' => 'green','icon' => '✅','hint' => 'Missions clôturées']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('ui.stat'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Terminées','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($statsJour['terminees']),'tone' => 'green','icon' => '✅','hint' => 'Missions clôturées']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal01e87901d1d4257641ca1465090a996e)): ?>
<?php $attributes = $__attributesOriginal01e87901d1d4257641ca1465090a996e; ?>
<?php unset($__attributesOriginal01e87901d1d4257641ca1465090a996e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal01e87901d1d4257641ca1465090a996e)): ?>
<?php $component = $__componentOriginal01e87901d1d4257641ca1465090a996e; ?>
<?php unset($__componentOriginal01e87901d1d4257641ca1465090a996e); ?>
<?php endif; ?>
        <?php if (isset($component)) { $__componentOriginal01e87901d1d4257641ca1465090a996e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal01e87901d1d4257641ca1465090a996e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.stat','data' => ['title' => 'Refusées','value' => $statsJour['refusees'],'tone' => 'red','icon' => '⛔','hint' => 'Missions non exécutées']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('ui.stat'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Refusées','value' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($statsJour['refusees']),'tone' => 'red','icon' => '⛔','hint' => 'Missions non exécutées']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal01e87901d1d4257641ca1465090a996e)): ?>
<?php $attributes = $__attributesOriginal01e87901d1d4257641ca1465090a996e; ?>
<?php unset($__attributesOriginal01e87901d1d4257641ca1465090a996e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal01e87901d1d4257641ca1465090a996e)): ?>
<?php $component = $__componentOriginal01e87901d1d4257641ca1465090a996e; ?>
<?php unset($__componentOriginal01e87901d1d4257641ca1465090a996e); ?>
<?php endif; ?>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($prochaineMission): ?>
    <div class="overflow-hidden rounded-[28px] bg-gradient-to-r from-blue-700 via-sky-700 to-indigo-700 p-6 text-white shadow-[0_18px_50px_rgba(37,99,235,0.22)]">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm text-blue-100">Prochaine mission</p>
                <h3 class="mt-1 text-xl font-bold">
                    <?php echo e($prochaineMission->service_display_name ?: 'Service non précisé'); ?>

                </h3>
                <p class="mt-1 text-sm text-blue-100"><?php echo e($prochaineMission->date); ?> à <?php echo e($prochaineMission->heure); ?></p>
                <p class="text-sm text-blue-100"><?php echo e($prochaineMission->client->name ?? 'Client'); ?> • <?php echo e($prochaineMission->adresse ?? 'Adresse non précisée'); ?>, <?php echo e($prochaineMission->ville ?? '—'); ?></p>
            </div>

            <div class="flex flex-wrap gap-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($prochaineMission->telephone_client): ?>
                <a href="tel:<?php echo e($prochaineMission->telephone_client); ?>" class="inline-flex items-center rounded-xl bg-white px-4 py-2 text-sm font-semibold text-blue-700 transition hover:bg-blue-50">📞 Appeler</a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($prochaineMission->adresse || $prochaineMission->ville): ?>
                <a href="https://www.google.com/maps/search/?api=1&query=<?php echo e(urlencode(($prochaineMission->adresse ?? '') . ' ' . ($prochaineMission->ville ?? ''))); ?>" target="_blank" class="inline-flex items-center rounded-xl bg-white px-4 py-2 text-sm font-semibold text-blue-700 transition hover:bg-blue-50">📍 GPS</a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <?php if (isset($component)) { $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => ['padding' => 'p-5','title' => 'Missions du jour','subtitle' => 'Triées par priorité d’exécution.','eyebrow' => 'Aujourd’hui']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['padding' => 'p-5','title' => 'Missions du jour','subtitle' => 'Triées par priorité d’exécution.','eyebrow' => 'Aujourd’hui']); ?>
                <div class="space-y-4">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $missionsDuJour; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rdv): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="cu-list-item <?php echo e($rdv->status === 'sur_place' ? 'ring-2 ring-indigo-200 border-indigo-300' : ''); ?>">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div>
                                <h4 class="text-lg font-semibold text-slate-900">
                                    <?php echo e($rdv->service_display_name ?: 'Service non précisé'); ?>

                                </h4>
                                <p class="text-sm text-slate-600">👤 <?php echo e($rdv->client->name ?? 'Client'); ?></p>
                                <p class="text-sm text-slate-600">🕒 <?php echo e($rdv->heure); ?> • 📍 <?php echo e($rdv->adresse ?? '—'); ?>, <?php echo e($rdv->ville ?? '—'); ?></p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <?php if (isset($component)) { $__componentOriginal2ddbc40e602c342e508ac696e52f8719 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal2ddbc40e602c342e508ac696e52f8719 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.badge','data' => ['status' => $rdv->status]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($rdv->status)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $attributes = $__attributesOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__attributesOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal2ddbc40e602c342e508ac696e52f8719)): ?>
<?php $component = $__componentOriginal2ddbc40e602c342e508ac696e52f8719; ?>
<?php unset($__componentOriginal2ddbc40e602c342e508ac696e52f8719); ?>
<?php endif; ?>
                                <?php if (isset($component)) { $__componentOriginal203f5a9932b19b3fc18de88ddfaf9f46 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal203f5a9932b19b3fc18de88ddfaf9f46 = $attributes; } ?>
<?php $component = App\View\Components\PriorityBadge::resolve(['priority' => $rdv->priorite] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('priority-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(App\View\Components\PriorityBadge::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal203f5a9932b19b3fc18de88ddfaf9f46)): ?>
<?php $attributes = $__attributesOriginal203f5a9932b19b3fc18de88ddfaf9f46; ?>
<?php unset($__attributesOriginal203f5a9932b19b3fc18de88ddfaf9f46); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal203f5a9932b19b3fc18de88ddfaf9f46)): ?>
<?php $component = $__componentOriginal203f5a9932b19b3fc18de88ddfaf9f46; ?>
<?php unset($__componentOriginal203f5a9932b19b3fc18de88ddfaf9f46); ?>
<?php endif; ?>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-3 text-sm text-slate-700 md:grid-cols-2">
                            <div class="space-y-1">
                                <p><span class="font-medium">Téléphone :</span> <?php echo e($rdv->telephone_client ?? '—'); ?></p>
                                <p><span class="font-medium">Durée estimée :</span> <?php echo e($rdv->duree_estimee ? $rdv->duree_estimee . ' min' : '—'); ?></p>
                                <p><span class="font-medium">Type de lieu :</span> <?php echo e(ucfirst($rdv->type_lieu ?? '—')); ?></p>
                            </div>

                            <div class="space-y-1">
                                <p><span class="font-medium">Surface :</span> <?php echo e($rdv->surface ?? '—'); ?></p>
                                <p><span class="font-medium">Parking :</span> <?php echo e($rdv->acces_parking ? 'Oui' : 'Non'); ?></p>
                                <p><span class="font-medium">Animaux :</span> <?php echo e($rdv->presence_animaux ? 'Oui' : 'Non'); ?></p>
                            </div>
                        </div>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rdv->commentaire_client): ?>
                        <div class="mt-4 rounded-xl border border-slate-200 bg-white p-3 text-sm text-slate-700">
                            <span class="font-medium">Remarque client :</span>
                            <?php echo e($rdv->commentaire_client); ?>

                        </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rdv->telephone_client): ?>
                            <a href="tel:<?php echo e($rdv->telephone_client); ?>" class="inline-flex items-center rounded-xl bg-green-100 px-3 py-2 text-sm font-medium text-green-700 transition hover:bg-green-200">📞 Appeler</a>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rdv->adresse || $rdv->ville): ?>
                            <a href="https://www.google.com/maps/search/?api=1&query=<?php echo e(urlencode(($rdv->adresse ?? '') . ' ' . ($rdv->ville ?? ''))); ?>" target="_blank" class="inline-flex items-center rounded-xl bg-blue-100 px-3 py-2 text-sm font-medium text-blue-700 transition hover:bg-blue-200">📍 GPS</a>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <?php if (isset($component)) { $__componentOriginal3607a477fdef7402bc742abad5df9c51 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3607a477fdef7402bc742abad5df9c51 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.empty-state','data' => ['title' => 'Aucune mission aujourd’hui','message' => 'Les nouvelles missions assignées apparaîtront ici automatiquement.','icon' => '🗓️']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('ui.empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Aucune mission aujourd’hui','message' => 'Les nouvelles missions assignées apparaîtront ici automatiquement.','icon' => '🗓️']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal3607a477fdef7402bc742abad5df9c51)): ?>
<?php $attributes = $__attributesOriginal3607a477fdef7402bc742abad5df9c51; ?>
<?php unset($__attributesOriginal3607a477fdef7402bc742abad5df9c51); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal3607a477fdef7402bc742abad5df9c51)): ?>
<?php $component = $__componentOriginal3607a477fdef7402bc742abad5df9c51; ?>
<?php unset($__componentOriginal3607a477fdef7402bc742abad5df9c51); ?>
<?php endif; ?>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $attributes = $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $component = $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>

            <?php if (isset($component)) { $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => ['padding' => 'p-5','title' => 'Gestion complète des missions','subtitle' => 'Suivi opérationnel, changement de statut et actions terrain.','eyebrow' => 'Terrain']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['padding' => 'p-5','title' => 'Gestion complète des missions','subtitle' => 'Suivi opérationnel, changement de statut et actions terrain.','eyebrow' => 'Terrain']); ?>
                <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('employe.mes-rendez-vous', []);

$__key = null;

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-3487531416-0', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key);

echo $__html;

unset($__html);
unset($__key);
unset($__name);
unset($__params);
unset($__split);
if (isset($__slots)) unset($__slots);
?>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $attributes = $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $component = $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>
        </div>

        <div class="space-y-6">
            <?php if (isset($component)) { $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => ['padding' => 'p-5','title' => 'Historique récent','subtitle' => 'Vos dernières missions terminées et leur rythme d’exécution.','eyebrow' => 'Suivi']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['padding' => 'p-5','title' => 'Historique récent','subtitle' => 'Vos dernières missions terminées et leur rythme d’exécution.','eyebrow' => 'Suivi']); ?>
                <div class="space-y-3">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $historiqueRecent; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rdv): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="cu-list-item">
                        <p class="font-medium text-slate-900"><?php echo e($rdv->service_display_name ?: 'Service non précisé'); ?></p>
                        <p class="text-sm text-slate-600"><?php echo e($rdv->date); ?> à <?php echo e($rdv->heure); ?></p>
                        <p class="text-sm text-slate-600"><?php echo e($rdv->client->name ?? 'Client'); ?></p>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rdv->duree_reelle): ?>
                        <p class="mt-1 text-xs text-slate-500">Durée réelle : <?php echo e($rdv->duree_reelle); ?> min</p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <?php if (isset($component)) { $__componentOriginal3607a477fdef7402bc742abad5df9c51 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3607a477fdef7402bc742abad5df9c51 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.empty-state','data' => ['title' => 'Aucun historique récent','message' => 'Votre historique de missions terminées apparaîtra ici.','icon' => '🧾']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('ui.empty-state'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => 'Aucun historique récent','message' => 'Votre historique de missions terminées apparaîtra ici.','icon' => '🧾']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal3607a477fdef7402bc742abad5df9c51)): ?>
<?php $attributes = $__attributesOriginal3607a477fdef7402bc742abad5df9c51; ?>
<?php unset($__attributesOriginal3607a477fdef7402bc742abad5df9c51); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal3607a477fdef7402bc742abad5df9c51)): ?>
<?php $component = $__componentOriginal3607a477fdef7402bc742abad5df9c51; ?>
<?php unset($__componentOriginal3607a477fdef7402bc742abad5df9c51); ?>
<?php endif; ?>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $attributes = $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $component = $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>


            <?php if (isset($component)) { $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => ['padding' => 'p-5','title' => 'Zones assignées','subtitle' => 'Vos zones de couverture actives et les éventuels écarts du jour.','eyebrow' => 'Couverture']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['padding' => 'p-5','title' => 'Zones assignées','subtitle' => 'Vos zones de couverture actives et les éventuels écarts du jour.','eyebrow' => 'Couverture']); ?>
                <div class="space-y-4">
                    <div>
                        <p class="text-sm font-medium text-slate-700 mb-2">Zones actives</p>
                        <div class="flex flex-wrap gap-2">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $assignedZones; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $zone): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <span class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700"><?php echo e($zone->name); ?></span>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <span class="text-sm text-slate-500">Aucune zone assignée.</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>

                    <div class="rounded-2xl border <?php echo e($missionsHorsZone->isNotEmpty() ? 'border-red-200 bg-red-50' : 'border-emerald-200 bg-emerald-50'); ?> p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h4 class="text-base font-semibold <?php echo e($missionsHorsZone->isNotEmpty() ? 'text-red-700' : 'text-emerald-700'); ?>">Mission(s) hors zone</h4>
                                <p class="mt-1 text-sm <?php echo e($missionsHorsZone->isNotEmpty() ? 'text-red-600' : 'text-emerald-600'); ?>">
                                    <?php echo e($missionsHorsZone->count()); ?> mission(s) détectée(s) aujourd’hui en dehors de vos zones assignées.
                                </p>
                            </div>
                            <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold <?php echo e($missionsHorsZone->isNotEmpty() ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'); ?>"><?php echo e($missionsHorsZone->count()); ?></span>
                        </div>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($missionsHorsZone->isNotEmpty()): ?>
                        <div class="mt-4 space-y-3">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $missionsHorsZone; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rdv): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="rounded-xl border border-red-200 bg-white p-3">
                                <p class="font-medium text-slate-900"><?php echo e($rdv->service_display_name ?: 'Service non précisé'); ?></p>
                                <p class="text-sm text-slate-600"><?php echo e($rdv->client->name ?? 'Client'); ?> • <?php echo e(substr((string) $rdv->heure, 0, 5)); ?></p>
                                <p class="text-sm text-red-700"><?php echo e($rdv->serviceZone?->name ?? 'Zone non définie'); ?></p>
                            </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $attributes = $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $component = $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>

            <?php if (isset($component)) { $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => ['padding' => 'p-5','title' => 'Mes limites de RDV par jour','subtitle' => 'Ajustez rapidement votre capacité hebdomadaire.','eyebrow' => 'Capacité']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['padding' => 'p-5','title' => 'Mes limites de RDV par jour','subtitle' => 'Ajustez rapidement votre capacité hebdomadaire.','eyebrow' => 'Capacité']); ?>
                <div class="space-y-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = \Carbon\Carbon::now()->startOfWeek()->daysUntil(\Carbon\Carbon::now()->endOfWeek()); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $jour): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="cu-list-item flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div class="text-sm font-medium text-slate-700 md:w-1/3">
                            <?php echo e($jour->translatedFormat('l d F')); ?>

                        </div>
                        <div class="md:w-2/3">
                            <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('modifier-limite-jour', [
                            'date' => $jour->format('Y-m-d'),
                            'user_id' => auth()->id(),
                            'fromAdmin' => false
                            ]);

$__key = $jour->format('Ymd') . '-' . auth()->id();

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-3487531416-1', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key);

echo $__html;

unset($__html);
unset($__key);
unset($__name);
unset($__params);
unset($__split);
if (isset($__slots)) unset($__slots);
?>
                        </div>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $attributes = $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $component = $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>

            <?php if (isset($component)) { $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => ['padding' => 'p-5','title' => 'Accès rapides employé','subtitle' => 'Les raccourcis les plus utiles pour piloter votre journée.','eyebrow' => 'Raccourcis']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['padding' => 'p-5','title' => 'Accès rapides employé','subtitle' => 'Les raccourcis les plus utiles pour piloter votre journée.','eyebrow' => 'Raccourcis']); ?>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <?php if (isset($component)) { $__componentOriginal897b42a47ecd20a68e8cc0d392b7acfd = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal897b42a47ecd20a68e8cc0d392b7acfd = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.action-button','data' => ['href' => route('employe.feedbacks'),'icon' => '💬']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('ui.action-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('employe.feedbacks')),'icon' => '💬']); ?>Voir tous mes feedbacks <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal897b42a47ecd20a68e8cc0d392b7acfd)): ?>
<?php $attributes = $__attributesOriginal897b42a47ecd20a68e8cc0d392b7acfd; ?>
<?php unset($__attributesOriginal897b42a47ecd20a68e8cc0d392b7acfd); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal897b42a47ecd20a68e8cc0d392b7acfd)): ?>
<?php $component = $__componentOriginal897b42a47ecd20a68e8cc0d392b7acfd; ?>
<?php unset($__componentOriginal897b42a47ecd20a68e8cc0d392b7acfd); ?>
<?php endif; ?>
                    <?php if (isset($component)) { $__componentOriginal897b42a47ecd20a68e8cc0d392b7acfd = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal897b42a47ecd20a68e8cc0d392b7acfd = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.action-button','data' => ['href' => route('employe.validation.multiple'),'variant' => 'amber','icon' => '✅']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('ui.action-button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(Illuminate\View\AnonymousComponent::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes(['href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('employe.validation.multiple')),'variant' => 'amber','icon' => '✅']); ?>Validation groupée des RDV <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal897b42a47ecd20a68e8cc0d392b7acfd)): ?>
<?php $attributes = $__attributesOriginal897b42a47ecd20a68e8cc0d392b7acfd; ?>
<?php unset($__attributesOriginal897b42a47ecd20a68e8cc0d392b7acfd); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal897b42a47ecd20a68e8cc0d392b7acfd)): ?>
<?php $component = $__componentOriginal897b42a47ecd20a68e8cc0d392b7acfd; ?>
<?php unset($__componentOriginal897b42a47ecd20a68e8cc0d392b7acfd); ?>
<?php endif; ?>
                </div>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $attributes = $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $component = $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>

            <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('feedbacks-employe', []);

$__key = null;

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-3487531416-2', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key);

echo $__html;

unset($__html);
unset($__key);
unset($__name);
unset($__params);
unset($__split);
if (isset($__slots)) unset($__slots);
?>
            <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('employe.feedback-stats', []);

$__key = null;

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-3487531416-3', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key);

echo $__html;

unset($__html);
unset($__key);
unset($__name);
unset($__params);
unset($__split);
if (isset($__slots)) unset($__slots);
?>
            <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('employe.validation-multiple-rdv', []);

$__key = null;

$__key ??= \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::generateKey('lw-3487531416-4', $__key);

$__html = app('livewire')->mount($__name, $__params, $__key);

echo $__html;

unset($__html);
unset($__key);
unset($__name);
unset($__params);
unset($__split);
if (isset($__slots)) unset($__slots);
?>
        </div>
    </div>
</div>


<script>
    const OFFLINE_QUEUE_KEY = 'cleanux_offline_actions';

    function getOfflineQueue() {
        return JSON.parse(localStorage.getItem(OFFLINE_QUEUE_KEY) || '[]');
    }

    function saveOfflineQueue(queue) {
        localStorage.setItem(OFFLINE_QUEUE_KEY, JSON.stringify(queue));
    }

    function queueOfflineAction(type, missionId, payload = {}) {
        const queue = getOfflineQueue();

        queue.push({
            type: type,
            mission_id: missionId,
            payload: payload,
            created_at: new Date().toISOString(),
        });

        saveOfflineQueue(queue);
    }

    async function syncOfflineActions() {
        const queue = getOfflineQueue();

        if (!queue.length || !navigator.onLine) {
            return;
        }

        const response = await fetch('/missions/offline-sync', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                actions: queue,
            }),
        });

        const result = await response.json();

        if (result.ok) {
            saveOfflineQueue([]);
            console.log(`${result.synced} action(s) synchronisée(s).`);
        }
    }

    window.addEventListener('online', syncOfflineActions);

    setInterval(syncOfflineActions, 30000);
</script><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/livewire/employe-dashboard.blade.php ENDPATH**/ ?>