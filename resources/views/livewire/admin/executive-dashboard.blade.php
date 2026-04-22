<div class="grid gap-6 md:grid-cols-4">

    <div class="p-4 border rounded">
        <div class="text-sm">Qualité moyenne</div>
        <div class="text-xl font-bold">{{ number_format($global['avg_quality'],1) }}/100</div>
    </div>

    <div class="p-4 border rounded">
        <div class="text-sm">Missions</div>
        <div class="text-xl font-bold">{{ $global['missions'] }}</div>
    </div>

    <div class="p-4 border rounded">
        <div class="text-sm">Incidents</div>
        <div class="text-xl font-bold">{{ $global['incidents'] }}</div>
    </div>

    <div class="p-4 border rounded">
        <div class="text-sm">Revenue</div>
        <div class="text-xl font-bold">€{{ number_format($global['revenue'],0) }}</div>
    </div>

</div>