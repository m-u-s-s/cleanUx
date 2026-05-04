    <div class="rounded-2xl border bg-white p-5 shadow-sm">
        <h3 class="font-semibold text-slate-900">📍 Sites</h3>

        @foreach($sites as $site)
            <div class="border p-3 rounded-xl mt-2">
                <p class="font-medium">{{ $site->name }}</p>
                <p class="text-sm text-slate-500">{{ $site->address }}</p>
                <p class="text-xs">Centre coût : {{ $site->cost_center }}</p>
            </div>
        @endforeach
    </div>
