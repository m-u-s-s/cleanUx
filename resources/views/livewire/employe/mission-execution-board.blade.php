<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-slate-900">Exécution mission</h3>
            <p class="text-sm text-slate-500">Mission #{{ $mission->id }}</p>
        </div>

        @if($checklist)
            <div class="text-sm text-slate-600">
                Progression :
                <span class="font-semibold text-slate-900">{{ $checklist->completion_rate }}%</span>
            </div>
        @endif
    </div>

    @if($successMessage)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ $successMessage }}
        </div>
    @endif

    @if($checklist)
        <div class="rounded-2xl border border-slate-200 p-4 space-y-3">
            <h4 class="font-semibold text-slate-900">Checklist</h4>

            <div class="space-y-2">
                @foreach($checklist->items as $item)
                    <button
                        type="button"
                        wire:click="toggleChecklistItem({{ $item->id }})"
                        class="flex w-full items-center justify-between rounded-xl border px-4 py-3 text-left
                            {{ $item->status === 'completed'
                                ? 'border-emerald-200 bg-emerald-50'
                                : 'border-slate-200 bg-white' }}"
                    >
                        <span class="text-sm font-medium text-slate-800">{{ $item->label }}</span>
                        <span class="text-xs font-semibold {{ $item->status === 'completed' ? 'text-emerald-700' : 'text-slate-500' }}">
                            {{ $item->status === 'completed' ? 'Fait' : 'À faire' }}
                        </span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid gap-6 md:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 p-4 space-y-4">
            <h4 class="font-semibold text-slate-900">Photos avant</h4>

            <input type="file" wire:model="beforePhotos" multiple accept="image/*" class="block w-full text-sm">

            @error('beforePhotos.*')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror

            <button wire:click="uploadBeforePhotos" type="button" class="rounded-xl bg-slate-900 px-4 py-3 text-sm font-medium text-white">
                Envoyer photos avant
            </button>

            <div class="grid grid-cols-2 gap-3">
                @foreach($beforeMedia as $media)
                    <img src="{{ asset('storage/'.$media->path) }}" alt="Photo avant" class="h-32 w-full rounded-xl object-cover border border-slate-200">
                @endforeach
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 p-4 space-y-4">
            <h4 class="font-semibold text-slate-900">Photos après</h4>

            <input type="file" wire:model="afterPhotos" multiple accept="image/*" class="block w-full text-sm">

            @error('afterPhotos.*')
                <p class="text-sm text-red-600">{{ $message }}</p>
            @enderror

            <button wire:click="uploadAfterPhotos" type="button" class="rounded-xl bg-emerald-600 px-4 py-3 text-sm font-medium text-white">
                Envoyer photos après
            </button>

            <div class="grid grid-cols-2 gap-3">
                @foreach($afterMedia as $media)
                    <img src="{{ asset('storage/'.$media->path) }}" alt="Photo après" class="h-32 w-full rounded-xl object-cover border border-slate-200">
                @endforeach
            </div>
        </div>
    </div>
</div>