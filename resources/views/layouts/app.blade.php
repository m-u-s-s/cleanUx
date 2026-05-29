<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      x-data
      x-init="
        const pref = localStorage.getItem('theme') || '{{ auth()->user()?->theme_preference ?? 'system' }}';
        if (pref === 'dark' || (pref === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
      ">

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

    {{-- Typo via bunny.net (GDPR-friendly, pas de tracking Google) --}}
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|figtree:400,500,600,700|space-grotesk:600,700&display=swap" rel="stylesheet">

    @auth
    <meta name="user-id" content="{{ auth()->id() }}">
    <meta name="org-id" content="{{ auth()->user()->organization_account_id }}">
    @endauth

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('styles')

    {{-- PostHog analytics — only loaded when POSTHOG_API_KEY is set (GDPR: loaded on user consent via cookie banner) --}}
    @if(config('analytics.posthog.api_key'))
    <script>
        !function(t,e){var o,n,p,r;e.__SV||(window.posthog=e,e._i=[],e.init=function(i,s,a){function g(t,e){var o=e.split(".");2==o.length&&(t=t[o[0]],e=o[1]),t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}(p=t.createElement("script")).type="text/javascript",p.async=!0,p.src=s.api_host+"/static/array.js",(r=t.getElementsByTagName("script")[0]).parentNode.insertBefore(p,r);var u=e;for(void 0!==a?u=e[a]=[]:a="posthog",u.people=u.people||[],u.toString=function(t){var e="posthog";return"posthog"!==a&&(e+="."+a),t||(e+=" (stub)"),e},u.people.toString=function(){return u.toString(1)+".people (stub)"},o="capture identify alias people.set people.set_once set_config register register_once unregister opt_out_capturing has_opted_out_capturing opt_in_capturing reset isFeatureEnabled onFeatureFlags getFeatureFlag getFeatureFlagPayload reloadFeatureFlags group updateEarlyAccessFeatureEnrollment getEarlyAccessFeatures getActiveMatchingSurveys getSurveys onSessionId".split(" "),n=0;n<o.length;n++)g(u,o[n]);e._i.push([i,s,a])},e.__SV=1)}(document,window.posthog||[]);
        posthog.init('{{ config("analytics.posthog.api_key") }}', {
            api_host: '{{ config("analytics.posthog.host", "https://eu.posthog.com") }}',
            person_profiles: 'identified_only',
            @auth
            loaded: function(ph) { ph.identify('{{ auth()->id() }}'); },
            @endauth
        });
    </script>
    @endif
</head>

<body class="font-sans antialiased text-slate-800 bg-slate-50/30 selection:bg-brand-100 selection:text-brand-900">
    <a href="#main-content" class="skip-to-content">Aller au contenu principal</a>
    <x-banner />

    <div class="min-h-screen {{ ($embedded ?? false) ? '' : 'pb-20 sm:pb-0' }}">
        @unless($embedded ?? false)
        <div data-chrome="primary-nav">
            @livewire('navigation-menu')
        </div>
        @endunless

        @if (isset($header))
        <header class="border-b border-white/70 bg-white/70 shadow-sm backdrop-blur">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                {{ $header }}
            </div>
        </header>
        @endif

        <main id="main-content" class="px-3 py-5 sm:px-5 lg:px-8 lg:py-6">
            <div class="cu-page animate-fade-in">
                {{ $slot }}
            </div>
        </main>
    </div>

    <x-toast />

    <audio id="success-sound" src="{{ asset('sounds/success.mp3') }}" preload="auto"></audio>
    <audio id="error-sound" src="{{ asset('sounds/error.mp3') }}" preload="auto"></audio>

    @unless($embedded ?? false)
    @auth
    @if (class_exists(\App\Livewire\Notifications::class))
    @livewire('notifications')
    @endif
    @endauth
    @endunless

    @unless($embedded ?? false)
    @auth
    @if(auth()->user()->isClient())
    <x-ui.mobile-bottom-nav role="client" />
    @elseif(auth()->user()->isEmploye() && request()->routeIs('employe.*'))
    <x-ui.mobile-bottom-nav role="employe" />
    @endif
    @endauth
    @endunless

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

    @unless($embedded ?? false)
    <x-mobile-bottom-nav />
    <x-pwa-install-prompt />
    <x-cookie-banner />

    @auth
        @livewire('chatbot.assistant-widget')
    @endauth
    @endunless
</body>

</html>