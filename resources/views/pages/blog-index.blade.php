<x-guest-layout
    :seoTitle="$seoTitle ?? 'Blog — Brio | Conseils, actualités, guides'"
    :seoDescription="$seoDescription ?? 'Découvrez nos articles sur les services à domicile, conseils nettoyage, guides travaux, et actualités Brio.'"
>

    <div class="cx-shell">
        <div class="max-w-4xl mx-auto px-4 py-20">
            <h1 class="text-4xl font-display font-bold text-white mb-4">Blog Brio</h1>
            <p class="text-lg mb-12" style="color:var(--cx-muted)">Conseils, actualités et guides pour vos services à domicile.</p>

            <div class="brio-card p-8 text-center">
                <x-empty-state
                    title="Articles à venir"
                    message="Notre blog sera bientôt disponible avec des guides pratiques, des conseils d'entretien et les dernières actualités Brio."
                />
            </div>
        </div>
    </div>

</x-guest-layout>
