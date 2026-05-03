<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
    <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">
                Liste détaillée
            </p>

            <h2 class="text-2xl font-black text-slate-900">
                Retours clients
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Répondez directement depuis la carte du feedback. La réponse est sauvegardée automatiquement.
            </p>
        </div>

        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black text-slate-600">
            {{ $feedbacks->total() }} résultat(s)
        </span>
    </div>

    <div class="space-y-5">
        @forelse($feedbacks as $feedback)
            @include('livewire.admin.feedbacks.feedback-card', ['feedback' => $feedback])
        @empty
            <x-empty-state
                title="Aucun feedback trouvé"
                message="Aucun retour ne correspond aux filtres actuels."
                icon="💬" />
        @endforelse
    </div>

    @include('livewire.admin.feedbacks.pagination')
</section>
