<div class="space-y-6">
    <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
        <x-kpi-card title="Utilisateurs" :value="$stats['utilisateurs']" tone="slate" icon="👥" />
        <x-kpi-card title="Clients" :value="$stats['clients']" tone="blue" icon="👤" />
        <x-kpi-card title="Employés" :value="$stats['employes']" tone="green" icon="👨‍🔧" />
        <x-kpi-card title="RDV" :value="$stats['rendez_vous']" tone="amber" icon="📅" />
        <x-kpi-card title="Feedbacks" :value="$stats['feedbacks']" tone="orange" icon="⭐" />
        <x-kpi-card title="Logs" :value="$stats['logs']" tone="red" icon="🪵" />
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <x-app-card title="Commandes de seed utiles" subtitle="Référentiel, démo et vérification rapide.">
            <div class="space-y-3">
                @foreach($seedCommands as $command)
                    <code class="cu-code-block">{{ $command }}</code>
                @endforeach
            </div>
        </x-app-card>

        <x-app-card title="Commandes de vérification" subtitle="Checks utiles pour garder la plateforme saine.">
            <div class="space-y-3">
                @foreach($usefulCommands as $command)
                    <code class="cu-code-block">{{ $command }}</code>
                @endforeach
            </div>
        </x-app-card>
    </div>

    <div class="cu-note">
        <h4 class="text-sm font-bold uppercase tracking-wide">Note</h4>
        <p class="mt-2">
            Cette section est volontairement informative. Elle ne lance pas directement de commandes système depuis l’interface,
            ce qui évite les actions destructrices involontaires en production.
        </p>
    </div>
</div>
