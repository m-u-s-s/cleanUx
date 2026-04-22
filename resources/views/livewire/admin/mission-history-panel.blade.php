<div class="space-y-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-lg font-semibold text-slate-900">Historique mission</h3>

        <div class="mt-4 space-y-3">
            @foreach($mission->events as $event)
                <div class="rounded-xl border border-slate-200 px-4 py-3">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="font-medium text-slate-900">{{ $event->title }}</p>
                            @if($event->description)
                                <p class="text-sm text-slate-600">{{ $event->description }}</p>
                            @endif
                        </div>
                        <div class="text-right text-sm text-slate-500">
                            <p>{{ optional($event->happened_at)->format('d/m/Y H:i') }}</p>
                            <p>{{ $event->actor?->name ?? 'Système' }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>