<?php if (isset($component)) { $__componentOriginal69dc84650370d1d4dc1b42d016d7226b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal69dc84650370d1d4dc1b42d016d7226b = $attributes; } ?>
<?php $component = App\View\Components\GuestLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('guest-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(App\View\Components\GuestLayout::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
    <main class="overflow-hidden bg-slate-50">

        
        <section class="relative">
            <div class="absolute inset-0 bg-gradient-to-br from-slate-950 via-slate-900 to-blue-950"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(59,130,246,0.28),transparent_35%),radial-gradient(circle_at_bottom_left,rgba(16,185,129,0.18),transparent_35%)]"></div>

            <div class="relative mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
                <div class="grid items-center gap-12 lg:grid-cols-2">
                    <div class="text-white">
                        <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-4 py-2 text-xs font-bold uppercase tracking-[0.2em] text-blue-100">
                            ✨ Plateforme de nettoyage moderne
                        </div>

                        <h1 class="mt-6 text-4xl font-black leading-tight tracking-tight sm:text-5xl lg:text-6xl">
                            Réservez un nettoyage,
                            <span class="text-blue-300">suivez l’employé</span>
                            et validez la mission en toute confiance.
                        </h1>

                        <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-200">
                            CleanUx simplifie toute l’expérience : devis, rendez-vous, suivi en temps réel,
                            codes de début/fin, historique, feedback et espace client.
                        </p>

                        <div class="mt-8 flex flex-wrap gap-3">
                            <a href="<?php echo e(route('booking.create')); ?>"
                               class="inline-flex items-center justify-center rounded-2xl bg-blue-600 px-6 py-4 text-sm font-bold text-white shadow-lg shadow-blue-900/30 hover:bg-blue-700">
                                Réserver une prestation
                            </a>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Route::has('premium.offer')): ?>
                                <a href="<?php echo e(route('premium.offer')); ?>"
                                   class="inline-flex items-center justify-center rounded-2xl border border-white/20 bg-white/10 px-6 py-4 text-sm font-bold text-white hover:bg-white/15">
                                    Découvrir Premium
                                </a>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                        <div class="mt-10 grid grid-cols-3 gap-3 text-center">
                            <div class="rounded-2xl border border-white/10 bg-white/10 p-4">
                                <p class="text-2xl font-black">24/7</p>
                                <p class="mt-1 text-xs text-slate-300">Demande en ligne</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/10 p-4">
                                <p class="text-2xl font-black">GPS</p>
                                <p class="mt-1 text-xs text-slate-300">Suivi employé</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/10 p-4">
                                <p class="text-2xl font-black">Code</p>
                                <p class="mt-1 text-xs text-slate-300">Début & fin</p>
                            </div>
                        </div>
                    </div>

                    <div class="relative">
                        <div class="rounded-[2rem] border border-white/10 bg-white p-5 shadow-2xl">
                            <div class="rounded-[1.5rem] bg-slate-50 p-5">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-500">Mission en cours</p>
                                        <h2 class="mt-1 text-2xl font-black text-slate-900">Nettoyage bureaux</h2>
                                    </div>
                                    <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-700">
                                        En route
                                    </span>
                                </div>

                                <div class="mt-5 rounded-2xl border bg-white p-4">
                                    <p class="text-sm text-slate-500">Arrivée estimée</p>
                                    <p class="mt-1 text-3xl font-black text-blue-700">12 min</p>
                                    <div class="mt-4 h-2 rounded-full bg-slate-100">
                                        <div class="h-2 w-2/3 rounded-full bg-blue-600"></div>
                                    </div>
                                </div>

                                <div class="mt-4 grid grid-cols-2 gap-3">
                                    <div class="rounded-2xl border bg-white p-4">
                                        <p class="text-xs text-slate-500">Employé</p>
                                        <p class="mt-1 font-bold text-slate-900">Assigné</p>
                                    </div>
                                    <div class="rounded-2xl border bg-white p-4">
                                        <p class="text-xs text-slate-500">Validation</p>
                                        <p class="mt-1 font-bold text-slate-900">Code client</p>
                                    </div>
                                </div>

                                <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                                    <p class="font-bold text-emerald-800">Mission sécurisée</p>
                                    <p class="mt-1 text-sm text-emerald-700">
                                        L’employé démarre et termine uniquement avec votre validation.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="absolute -bottom-6 -left-6 hidden rounded-2xl bg-white p-4 shadow-xl lg:block">
                            <p class="text-xs font-semibold text-slate-500">Feedback client</p>
                            <p class="mt-1 text-xl font-black text-slate-900">★★★★★</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        
        <section class="border-b border-slate-200 bg-white py-6">
            <div class="mx-auto grid max-w-7xl grid-cols-2 gap-4 px-4 text-center sm:px-6 md:grid-cols-4 lg:px-8">
                <div>
                    <p class="font-black text-slate-900">Devis clair</p>
                    <p class="text-sm text-slate-500">Avant confirmation</p>
                </div>
                <div>
                    <p class="font-black text-slate-900">Suivi live</p>
                    <p class="text-sm text-slate-500">Employé en route</p>
                </div>
                <div>
                    <p class="font-black text-slate-900">Photos & rapport</p>
                    <p class="text-sm text-slate-500">Après mission</p>
                </div>
                <div>
                    <p class="font-black text-slate-900">B2B ready</p>
                    <p class="text-sm text-slate-500">Sites & factures</p>
                </div>
            </div>
        </section>

        
        <section id="fonctionnement" class="py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="max-w-3xl">
                    <p class="text-sm font-black uppercase tracking-[0.2em] text-blue-600">Comment ça marche</p>
                    <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-900 sm:text-4xl">
                        Un parcours simple du devis jusqu’au feedback.
                    </h2>
                </div>

                <div class="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = [
                        ['1', 'Demande', 'Le client choisit le service, l’adresse, la date et les options.'],
                        ['2', 'Devis', 'Le prix estimé est visible avant la confirmation.'],
                        ['3', 'Mission', 'L’employé est assigné, suivi et validé par code.'],
                        ['4', 'Feedback', 'Le client reçoit une page de note et commentaire.'],
                    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $step): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="rounded-[2rem] border bg-white p-6 shadow-sm">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-600 text-lg font-black text-white">
                                <?php echo e($step[0]); ?>

                            </div>
                            <h3 class="mt-5 text-xl font-black text-slate-900"><?php echo e($step[1]); ?></h3>
                            <p class="mt-3 text-sm leading-6 text-slate-600"><?php echo e($step[2]); ?></p>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </section>

        
        <section id="services" class="bg-white py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="max-w-3xl">
                    <p class="text-sm font-black uppercase tracking-[0.2em] text-emerald-600">Services</p>
                    <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-900 sm:text-4xl">
                        Des prestations adaptées aux particuliers et aux entreprises.
                    </h2>
                </div>

                <div class="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = [
                        ['🏠', 'Nettoyage standard', 'Entretien régulier maison, appartement ou studio.'],
                        ['✨', 'Nettoyage profond', 'Intervention plus complète avec options ciblées.'],
                        ['🏗️', 'Fin de chantier', 'Nettoyage après travaux, rénovation ou déménagement.'],
                        ['🏢', 'Bureaux & B2B', 'Nettoyage professionnel avec suivi et facturation.'],
                    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="rounded-[2rem] border border-slate-200 bg-slate-50 p-6">
                            <div class="text-3xl"><?php echo e($service[0]); ?></div>
                            <h3 class="mt-4 text-lg font-black text-slate-900"><?php echo e($service[1]); ?></h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600"><?php echo e($service[2]); ?></p>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </section>

        
        <section id="premium" class="py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-8 lg:grid-cols-2">
                    <div class="rounded-[2rem] border bg-white p-8 shadow-sm">
                        <p class="text-sm font-black uppercase tracking-[0.2em] text-slate-500">Standard</p>
                        <h3 class="mt-3 text-3xl font-black text-slate-900">Pour réserver simplement.</h3>
                        <ul class="mt-6 space-y-3 text-sm text-slate-700">
                            <li>✅ Réservation rapide</li>
                            <li>✅ Devis estimé</li>
                            <li>✅ Suivi du rendez-vous</li>
                            <li>✅ Historique client</li>
                        </ul>
                        <a href="<?php echo e(route('booking.create')); ?>"
                           class="mt-8 inline-flex rounded-2xl bg-slate-900 px-5 py-3 text-sm font-bold text-white hover:bg-slate-800">
                            Commencer
                        </a>
                    </div>

                    <div class="relative overflow-hidden rounded-[2rem] border border-blue-200 bg-blue-600 p-8 text-white shadow-xl">
                        <div class="absolute right-0 top-0 h-40 w-40 rounded-full bg-white/10 blur-2xl"></div>
                        <p class="text-sm font-black uppercase tracking-[0.2em] text-blue-100">Premium</p>
                        <h3 class="mt-3 text-3xl font-black">Pour les clients réguliers.</h3>
                        <p class="mt-4 text-blue-50">
                            Plus de confort, plus de personnalisation et une expérience plus fluide.
                        </p>
                        <ul class="mt-6 space-y-3 text-sm text-blue-50">
                            <li>⭐ Employés favoris</li>
                            <li>⭐ Disponibilités visibles</li>
                            <li>⭐ Replanification plus simple</li>
                            <li>⭐ Expérience prioritaire</li>
                        </ul>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Route::has('premium.offer')): ?>
                            <a href="<?php echo e(route('premium.offer')); ?>"
                               class="mt-8 inline-flex rounded-2xl bg-white px-5 py-3 text-sm font-bold text-blue-700 hover:bg-blue-50">
                                Voir Premium
                            </a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        
        <section id="b2b" class="bg-slate-900 py-20 text-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-10 lg:grid-cols-2 lg:items-center">
                    <div>
                        <p class="text-sm font-black uppercase tracking-[0.2em] text-blue-300">Entreprises</p>
                        <h2 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                            Une solution pensée pour les bureaux, sites et grands comptes.
                        </h2>
                        <p class="mt-5 text-slate-300">
                            CleanUx peut gérer plusieurs sites, des validations internes, des factures mensuelles,
                            des centres de coûts et un suivi opérationnel clair.
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = [
                            ['Multi-sites', 'Plusieurs adresses et responsables.'],
                            ['Workflow', 'Validation manager et finance.'],
                            ['Facturation B2B', 'Factures groupées par période.'],
                            ['SLA', 'Suivi qualité et alertes opérationnelles.'],
                        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="rounded-2xl border border-white/10 bg-white/10 p-5">
                                <p class="font-black"><?php echo e($item[0]); ?></p>
                                <p class="mt-2 text-sm text-slate-300"><?php echo e($item[1]); ?></p>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        
        <section class="py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-[2rem] bg-gradient-to-r from-blue-600 to-emerald-500 p-8 text-white shadow-xl sm:p-12">
                    <div class="max-w-3xl">
                        <h2 class="text-3xl font-black sm:text-4xl">
                            Prêt à réserver votre prochain nettoyage ?
                        </h2>
                        <p class="mt-4 text-blue-50">
                            Une expérience moderne, claire et rassurante pour les particuliers comme pour les entreprises.
                        </p>

                        <div class="mt-8 flex flex-wrap gap-3">
                            <a href="<?php echo e(route('booking.create')); ?>"
                               class="rounded-2xl bg-white px-6 py-4 text-sm font-black text-blue-700 hover:bg-blue-50">
                                Réserver maintenant
                            </a>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->guest()): ?>
                                <a href="<?php echo e(route('register')); ?>"
                                   class="rounded-2xl border border-white/30 px-6 py-4 text-sm font-black text-white hover:bg-white/10">
                                    Créer un compte
                                </a>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </main>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal69dc84650370d1d4dc1b42d016d7226b)): ?>
<?php $attributes = $__attributesOriginal69dc84650370d1d4dc1b42d016d7226b; ?>
<?php unset($__attributesOriginal69dc84650370d1d4dc1b42d016d7226b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal69dc84650370d1d4dc1b42d016d7226b)): ?>
<?php $component = $__componentOriginal69dc84650370d1d4dc1b42d016d7226b; ?>
<?php unset($__componentOriginal69dc84650370d1d4dc1b42d016d7226b); ?>
<?php endif; ?><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/home.blade.php ENDPATH**/ ?>