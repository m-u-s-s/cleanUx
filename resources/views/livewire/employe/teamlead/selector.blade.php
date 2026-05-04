<div class="bg-white rounded-2xl border shadow-sm p-4 space-y-4">
    <div>
        <label class="text-sm font-semibold text-slate-700">Lot / chantier</label>
        <select wire:model.live="selectedBatchId" class="mt-1 w-full rounded-xl border-slate-300">
            <option value="">Choisir un lot</option>
            @foreach($batches as $batch)
                <option value="{{ $batch->id }}">#{{ $batch->id }} — {{ $batch->name ?? $batch->batch_type }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="text-sm font-semibold text-slate-700">Segment</label>
        <select wire:model.live="selectedSegmentId" class="mt-1 w-full rounded-xl border-slate-300">
            <option value="">Choisir un segment</option>
            @foreach($segments as $segment)
                <option value="{{ $segment->id }}">{{ $segment->segment_label ?? ('Segment #'.$segment->id) }}</option>
            @endforeach
        </select>
    </div>

    @if($selectedSegment)
        <div class="rounded-xl border bg-slate-50 p-3 text-sm">
            <p class="font-semibold text-slate-800">{{ $selectedSegment->segment_label ?? ('Segment #'.$selectedSegment->id) }}</p>
            <p class="text-slate-500">{{ $selectedSegment->segment_date }} · {{ $selectedSegment->estimated_minutes }} min</p>
            <p class="text-slate-500">Mission #{{ $selectedSegment->mission_id ?? '—' }}</p>
        </div>
    @endif
</div>
