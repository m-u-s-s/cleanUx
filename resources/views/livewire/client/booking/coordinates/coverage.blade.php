@if($coverageMessage)
    <div class="md:col-span-2">
        <div class="rounded-2xl border px-4 py-3 text-sm {{ $coverageStatus === 'covered' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700' }}">
            {{ $coverageMessage }}
        </div>
    </div>
@endif
