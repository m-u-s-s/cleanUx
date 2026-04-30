<x-page-shell
    title="🔁 Mes abonnements"
    subtitle="Gérez vos prestations récurrentes.">

    <div class="rounded-2xl border bg-white p-6 shadow-sm">
        <p class="text-slate-600">
            Aucun abonnement actif pour le moment.
        </p>

        <a href="{{ route('booking.create') }}"
           class="mt-4 inline-flex rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700">
            Créer une demande
        </a>
    </div>
</x-page-shell>