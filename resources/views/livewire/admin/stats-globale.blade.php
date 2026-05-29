@once
    @push('scripts')
        @vite(['resources/js/apexcharts.js'])
    @endpush
@endonce

<div class="space-y-6">

    {{-- 🎛️ Filtres --}}
    <div class="flex flex-wrap items-end gap-4">
        <div>
            <label class="text-sm">Année :</label>
            <select wire:model="year" class="border px-2 py-1 text-sm rounded">
                @for($y = now()->year; $y >= now()->year - 3; $y--)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endfor
            </select>
        </div>

        <div>
            <label class="text-sm">Employé (facultatif)</label>
            <select wire:model="employe_id" class="border px-2 py-1 text-sm rounded">
                <option value="">— Tous —</option>
                @foreach($employes as $e)
                    <option value="{{ $e->id }}">{{ $e->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- 📊 Graphique ApexCharts --}}
    <div wire:ignore id="chart-rdv" style="height: 300px;"></div>

    {{-- 🔢 Stats globales feedback --}}
    <div class="flex gap-8 text-sm text-gray-700">
        <div>💬 Feedbacks : <strong>{{ $feedbackCount }}</strong></div>
        <div>⭐ Moyenne des notes : <strong>{{ $noteAverage }}/5</strong></div>
    </div>

    {{-- Script ApexCharts --}}
    @push('scripts')
    <script>
        Livewire.hook('message.processed', (msg, comp) => {
            const chart = new ApexCharts(document.querySelector("#chart-rdv"), {
                chart: { type: 'bar', height: 300 },
                series: [{
                    name: 'RDV par mois',
                    data: @js($dataMonthly)
                }],
                xaxis: {
                    categories: ['Jan', 'Fev', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc']
                },
                colors: ['#2563eb']
            });
            chart.render();
        });
    </script>
    @endpush
</div>
