<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <title><?php echo e(config('app.name', 'CleanUx')); ?> | Nettoyage simple, suivi et professionnel</title>

    <meta name="description" content="Réservez un service de nettoyage professionnel, suivez l’employé en route, validez la mission par code et laissez votre feedback.">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />

    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::styles(); ?>

</head>

<body class="font-sans antialiased bg-slate-50 text-slate-900">
    <div class="min-h-screen">
        <header class="sticky top-0 z-50 border-b border-white/60 bg-white/85 backdrop-blur-xl">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <a href="<?php echo e(route('home')); ?>" class="flex items-center gap-2">
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-600 text-white font-black">
                        C
                    </div>
                    <div>
                        <p class="text-lg font-black tracking-tight text-slate-900">
                            <?php echo e(config('app.name', 'CleanUx')); ?>

                        </p>
                        <p class="hidden text-xs text-slate-500 sm:block">Nettoyage connecté & professionnel</p>
                    </div>
                </a>

                <nav class="hidden items-center gap-6 text-sm font-medium text-slate-600 md:flex">
                    <a href="<?php echo e(route('home')); ?>#services" class="hover:text-blue-700">Services</a>
                    <a href="<?php echo e(route('home')); ?>#fonctionnement" class="hover:text-blue-700">Fonctionnement</a>
                    <a href="<?php echo e(route('home')); ?>#b2b" class="hover:text-blue-700">Entreprises</a>
                    <a href="<?php echo e(route('home')); ?>#premium" class="hover:text-blue-700">Premium</a>
                </nav>

                <div class="flex items-center gap-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                        <a href="<?php echo e(route('dashboard')); ?>"
                           class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            Dashboard
                        </a>
                    <?php else: ?>
                        <a href="<?php echo e(route('login')); ?>"
                           class="hidden rounded-xl px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 sm:inline-flex">
                            Connexion
                        </a>

                        <a href="<?php echo e(route('booking.create')); ?>"
                           class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">
                            Réserver
                        </a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </header>

        <?php echo e($slot); ?>

    </div>

    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scripts(); ?>

    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html><?php /**PATH C:\Users\mmdar\Desktop\code\work\CleanUx\resources\views/layouts/guest.blade.php ENDPATH**/ ?>