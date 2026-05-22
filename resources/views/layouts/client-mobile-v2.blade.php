<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0a0a0f">
    <title>{{ config('app.name', 'CleanUx') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body
    class="antialiased min-h-screen"
    style="
        background: var(--color-bg);
        color: var(--color-text);
        padding-top: env(safe-area-inset-top);
        padding-bottom: env(safe-area-inset-bottom);
        -webkit-tap-highlight-color: transparent;
    "
>
    {{ $slot }}
    @livewireScripts
</body>
</html>
