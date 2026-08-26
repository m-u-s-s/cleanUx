{{-- LA PAGE COMMENÇAIT PAR UNE CARTE. Le titre d une carte est un h3 : c est juste pour une
     carte, et insuffisant pour une page, qui doit annoncer ce qu elle est. --}}
<div class="space-y-5">
    <x-page-shell eyebrow="Qualite" title="Vos retours clients"
                  subtitle="Les notes et commentaires laisses apres vos interventions." />

    <x-app-card padding="p-5 md:p-6" :title="__('💬 Feedbacks reçus de vos clients')" :subtitle="__('Retrouvez vos derniers retours clients, notes et commentaires.')">
    <div class="space-y-3">
        @forelse($feedbacks as $feedback)
            <div class="brio-list-item !bg-white">
                <x-feedback-card :feedback="$feedback" />
            </div>
        @empty
            <x-empty-state :title="__('Aucun feedback reçu pour le moment.')" :message="__('Les retours de vos clients apparaîtront ici après les premières missions terminées.')" icon="💬" />
        @endforelse
    </div>

    <div class="mt-4">{{ $feedbacks->links() }}</div>
</x-app-card>
</div>
