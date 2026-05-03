@if(($visibleDashboardSections['premium'] ?? true) && ! $compactMode)
    <section class="space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-amber-600">
                Clients & premium
            </p>
            <h2 class="text-2xl font-black text-slate-900">
                Suivi client et abonnements
            </h2>
            <p class="text-sm text-slate-500">
                Clients premium, rendez-vous non assignés et accompagnement personnalisé.
            </p>
        </div>

        @include('livewire.admin.dashboard.premium-overview')
    </section>
@endif
