<x-app-card padding="p-5 md:p-6" :title="__('💬 Feedbacks reçus de vos clients')" :subtitle="__('Retrouvez vos derniers retours clients, notes et commentaires.')">
    <div class="space-y-3">
        @forelse($feedbacks as $feedback)
            <div class="cu-list-item !bg-white">
                <x-feedback-card :feedback="$feedback" />
            </div>
        @empty
            <x-empty-state :title="__('Aucun feedback reçu pour le moment.')" :message="__('Les retours de vos clients apparaîtront ici après les premières missions terminées.')" icon="💬" />
        @endforelse
    </div>

    <div class="mt-4">{{ $feedbacks->links() }}</div>
</x-app-card>
