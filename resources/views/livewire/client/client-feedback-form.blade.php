<div class="space-y-6">
    <x-page-shell
        eyebrow="Espace client"
        title="Laisser un feedback"
        subtitle="Partagez votre avis sur la prestation réalisée."
    >
        <x-slot name="actions">
            @if(Route::has('client.dashboard'))
                <a href="{{ route('client.dashboard') }}" class="cu-btn-secondary">
                    ← Retour dashboard
                </a>
            @endif
        </x-slot>
    </x-page-shell>

    <x-app-card padding="p-6" title="Votre avis" subtitle="Cette page est prête. Il reste à connecter le formulaire complet au modèle Feedback.">
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Le composant Livewire <strong>ClientFeedbackForm</strong> existe, mais sa logique métier est encore minimale.
            Cette vue évite l’erreur Blade manquante et peut ensuite être enrichie avec note, commentaire et pièces jointes.
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700">Note</label>
                <select class="w-full rounded-2xl border-slate-300">
                    <option>5 étoiles</option>
                    <option>4 étoiles</option>
                    <option>3 étoiles</option>
                    <option>2 étoiles</option>
                    <option>1 étoile</option>
                </select>
            </div>

            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700">Type de feedback</label>
                <select class="w-full rounded-2xl border-slate-300">
                    <option>Qualité du nettoyage</option>
                    <option>Ponctualité</option>
                    <option>Communication</option>
                    <option>Autre</option>
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="mb-2 block text-sm font-semibold text-slate-700">Commentaire</label>
                <textarea rows="5" class="w-full rounded-2xl border-slate-300" placeholder="Expliquez votre expérience..."></textarea>
            </div>
        </div>

        <div class="mt-6 flex justify-end">
            <button type="button" class="cu-btn-primary">
                Envoyer mon feedback
            </button>
        </div>
    </x-app-card>
</div>