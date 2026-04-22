<x-active-sessions />

<x-page-shell eyebrow="Pilotage" title="Tableau de bord administrateur" subtitle="Vue consolidée des opérations, des urgences, des clients premium et de la charge globale de la plateforme.">
    <x-slot name="actions">
        <a href="{{ route('admin.planning') }}" class="cu-btn-primary">🗓️ Planning</a>
        <a href="{{ route('admin.missions') }}" class="cu-btn-secondary">📋 Missions</a>
        <a href="{{ route('admin.premium.clients') }}" class="cu-btn-secondary !border-amber-200 !bg-amber-50 !text-amber-700">★ Clients Premium</a>
        <a href="{{ route('admin.outils') }}" class="cu-btn-secondary">🛠️ Outils</a>
    </x-slot>
</x-page-shell>
