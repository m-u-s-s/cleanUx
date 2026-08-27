@props([
    'fichiers' => [],
    'titre' => 'Preuves ajoutées',
])

@php
    /*
        UN SEUL AFFICHAGE POUR LES DEUX MODULES DE LITIGE.

        Chaque lien passe par `PrivateMedia::url()` : une route signée de trente minutes, elle-même
        gardée par l'authentification. JAMAIS `/storage/` — une preuve montre un logement, un dégât,
        parfois une personne. C'est la propriété que `PrivateMediaTest` tient depuis le début.
    */
    $preuves = collect($fichiers)
        ->filter(fn ($p) => is_array($p) && ! empty($p['path']))
        ->values();
@endphp

@if ($preuves->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'space-y-2']) }}>
        <p class="text-sm font-bold text-slate-800">{{ $titre }}</p>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            @foreach ($preuves as $preuve)
                <a href="{{ \App\Support\Media\PrivateMedia::url($preuve['path']) }}"
                   target="_blank"
                   rel="noopener"
                   class="block overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                    <img src="{{ \App\Support\Media\PrivateMedia::url($preuve['path']) }}"
                         class="h-24 w-full object-cover"
                         alt="{{ $preuve['original_name'] ?? 'Preuve du litige' }}">
                </a>
            @endforeach
        </div>
    </div>
@endif
