<x-app-layout>
    {{--
        LA PAGE DE REPLI DES MODULES NON ENCORE BRANCHÉS.

        Elle était écrite en HTML brut dans `routes/missing-route-fixes-advanced.php` : un document
        complet, avec son propre `<head>`, sa propre coquille, et Tailwind chargé depuis un CDN. Elle
        échappait donc à TOUT — la barre de navigation, le thème sombre, la marque, et la politique
        du dépôt qui veut que les assets soient servis par Vite et non par un tiers.

        Le balayage de marque l'a trouvée parce qu'il rend les pages au lieu de lire les gabarits :
        c'était la seule page d'un espace authentifié à ne porter aucune identité.
    --}}
    <div class="mx-auto max-w-4xl">
        <div class="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-700 dark:bg-slate-800">
            <p class="text-sm font-black uppercase tracking-[0.2em] text-blue-600 dark:text-blue-400">
                {{ config('app.name', 'Brio') }}
            </p>
            <h1 class="mt-3 text-3xl font-black text-slate-900 dark:text-white">{{ $titre }}</h1>
            <p class="mt-4 text-slate-600 dark:text-slate-300">
                {{ $message ?? 'Cette page est routée. Il reste à y brancher le composant ou la logique métier.' }}
            </p>
            <a href="{{ route('dashboard') }}"
                class="mt-6 inline-flex rounded-2xl bg-blue-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-blue-700">
                Retour au tableau de bord
            </a>
        </div>
    </div>
</x-app-layout>
