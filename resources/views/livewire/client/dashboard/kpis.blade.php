<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi-card :title="__('Total prestations')" :value="$statsClient['total'] ?? 0" tone="slate" icon="📦" />
        <x-kpi-card :title="__('À venir')" :value="$statsClient['avenir'] ?? 0" tone="blue" icon="📅" />
        <x-kpi-card :title="__('Terminées')" :value="$statsClient['termine'] ?? 0" tone="green" icon="✅" />
        <x-kpi-card :title="__('Feedbacks')" :value="$statsClient['feedbacks'] ?? 0" tone="amber" icon="⭐" />
    </div>
