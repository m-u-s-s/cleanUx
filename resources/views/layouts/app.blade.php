<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'CleanUx') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="font-sans antialiased text-gray-800 selection:bg-sky-100 selection:text-sky-900">
    <x-banner />

    <div class="min-h-screen pb-20 sm:pb-0">
        @livewire('navigation-menu')

        @if (isset($header))
        <header class="border-b border-white/70 bg-white/70 shadow-sm backdrop-blur">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                {{ $header }}
            </div>
        </header>
        @endif

        <main class="px-3 py-5 sm:px-5 lg:px-8 lg:py-6">
            <div class="cu-page animate-fade-in">
                {{ $slot }}
            </div>
        </main>
    </div>

    <x-toast />

    <audio id="success-sound" src="{{ asset('sounds/success.mp3') }}" preload="auto"></audio>
    <audio id="error-sound" src="{{ asset('sounds/error.mp3') }}" preload="auto"></audio>

    @auth
    @if (class_exists(\App\Livewire\Notifications::class))
    @livewire('notifications')
    @endif
    @endauth

    @stack('modals')
    @livewireScripts
    @stack('scripts')

    @auth
    @if(auth()->user()->role === 'client')
    <div class="sm:hidden fixed bottom-0 inset-x-0 z-50 border-t border-slate-200 bg-white/95 backdrop-blur shadow-[0_-10px_30px_rgba(15,23,42,0.08)]">
        <div class="grid grid-cols-4 h-16">
            <a href="{{ route('client.dashboard') }}"
                class="flex flex-col items-center justify-center text-xs {{ request()->routeIs('client.dashboard') ? 'text-blue-600 font-semibold' : 'text-gray-500' }}">
                <span>🏠</span>
                <span>Accueil</span>
            </a>

            <a href="{{ route('client.rendezvous.create') }}"
                class="flex flex-col items-center justify-center text-xs {{ request()->routeIs('client.rendezvous.*') ? 'text-blue-600 font-semibold' : 'text-gray-500' }}">
                <span>➕</span>
                <span>Demande</span>
            </a>

            <a href="{{ route('client.rendezvous.index') }}"
                class="flex flex-col items-center justify-center text-xs {{ request()->routeIs('client.rendezvous.index') ? 'text-blue-600 font-semibold' : 'text-gray-500' }}">
                <span>📅</span>
                <span>Rendez-vous</span>
            </a>

            <a href="{{ route('client.historique') }}"
                class="flex flex-col items-center justify-center text-xs {{ request()->routeIs('client.historique') ? 'text-blue-600 font-semibold' : 'text-gray-500' }}">
                <span>🕘</span>
                <span>Historique</span>
            </a>
        </div>
    </div>
    @endif
    @endauth




    <!-- script offline -->
    <script src="/js/offline-mission.js"></script>

    <script>
        setInterval(() => {
            window.OfflineMission.sync();
        }, 10000); // toutes les 10 sec

        window.addEventListener('online', () => {
            window.OfflineMission.sync();
        });
    </script>
</body>

</html>