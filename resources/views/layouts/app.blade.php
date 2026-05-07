<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    {{-- Phase 8 — PWA --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="CleanUx">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'CleanUx') }}</title>


    @auth
    <meta name="user-id" content="{{ auth()->id() }}">
    <meta name="org-id" content="{{ auth()->user()->organization_account_id }}">
    @endauth

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('styles')
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

    @auth
    @if(auth()->user()->isClient())
    <x-ui.mobile-bottom-nav role="client" />
    @elseif(auth()->user()->isEmploye() && request()->routeIs('employe.*'))
    <x-ui.mobile-bottom-nav role="employe" />
    @endif
    @endauth

    @stack('modals')
    @livewireScripts

    @auth
    @if(auth()->user()->isEmploye() && request()->routeIs('employe.*'))
    <script src="{{ asset('js/offline-mission.js') }}"></script>

    <script>
        if (window.OfflineMission) {
            setInterval(() => {
                window.OfflineMission.sync();
            }, 10000);

            window.addEventListener('online', () => {
                window.OfflineMission.sync();
            });
        }
    </script>
    @endif
    @endauth

    @stack('scripts')
</body>

</html>