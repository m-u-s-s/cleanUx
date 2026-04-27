<x-page-shell title="📜 Contrats entreprise">

    @foreach($contracts as $contract)
        <div class="border p-4 rounded-xl mb-3">
            <p class="font-bold">{{ $contract->name }}</p>
            <p>SLA : {{ $contract->sla_hours }}h</p>
            <p>Remise : {{ $contract->discount_percent }}%</p>
            <p>Statut : {{ $contract->status }}</p>
        </div>
    @endforeach

</x-page-shell>