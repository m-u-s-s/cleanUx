<section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
    <x-kpi-card title="Feedbacks" :value="$qualityStats['total']" tone="blue" icon="💬" />
    <x-kpi-card title="Moyenne" :value="$qualityStats['average_note_label']" tone="amber" icon="⭐" />
    <x-kpi-card title="Sans réponse" :value="$qualityStats['unanswered']" tone="rose" icon="✍️" />
    <x-kpi-card title="Notes faibles" :value="$qualityStats['low_scores']" tone="red" icon="⚠️" />
    <x-kpi-card title="Répondus" :value="$qualityStats['answered']" tone="green" icon="✅" />
</section>
