<div class="space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ __('Disponibilités employé') }}</h1>
            <p class="text-sm text-gray-500">{{ __('Ajoute tes créneaux, modifie-les et bloque une journée si nécessaire.') }}</p>
        </div>
        <div class="flex gap-2">
            <button wire:click="previousWeek" class="px-4 py-2 rounded-lg border text-sm">{{ __('← Semaine précédente') }}</button>
            <button wire:click="nextWeek" class="px-4 py-2 rounded-lg border text-sm">{{ __('Semaine suivante →') }}</button>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-1 bg-white rounded-2xl border shadow-sm p-4 space-y-4">
            <div>
                <h2 class="font-semibold text-gray-900">{{ $editingId ? __('Modifier un créneau') : __('Ajouter un créneau') }}</h2>
            </div>
            <div>
                <label class="block text-sm text-gray-600 mb-1">{{ __('Date') }}</label>
                <input type="date" wire:model="date" class="w-full border rounded-lg px-3 py-2 text-sm">
                @error('date') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">{{ __('Début') }}</label>
                    <input type="time" wire:model="heure_debut" class="w-full border rounded-lg px-3 py-2 text-sm">
                    @error('heure_debut') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">{{ __('Fin') }}</label>
                    <input type="time" wire:model="heure_fin" class="w-full border rounded-lg px-3 py-2 text-sm">
                    @error('heure_fin') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex gap-2">
                <button wire:click="save" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm">{{ $editingId ? __('Mettre à jour') : __('Ajouter') }}</button>
                <button wire:click="resetForm" class="px-4 py-2 rounded-lg border text-sm">{{ __('Réinitialiser') }}</button>
            </div>
        </div>

        <div class="xl:col-span-2 bg-white rounded-2xl border shadow-sm p-4 space-y-4">
            <h2 class="font-semibold text-gray-900">{{ __('Semaine du :date', ['date' => \Carbon\Carbon::parse($weekStart)->translatedFormat('d F Y')]) }}</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                @foreach($weekDays as $day)
                    @php $slots = $slotsByDay[$day->toDateString()] ?? collect(); @endphp
                    <div class="border rounded-2xl p-4 space-y-3 {{ $day->isToday() ? 'ring-2 ring-blue-200 border-blue-300' : '' }}">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-semibold text-gray-900">{{ $day->translatedFormat('l') }}</p>
                                <p class="text-xs text-gray-500">{{ $day->translatedFormat('d/m/Y') }}</p>
                            </div>
                            <button wire:click="blockDay('{{ $day->toDateString() }}')" class="text-xs px-2 py-1 rounded bg-red-50 text-red-600">{{ __('Bloquer') }}</button>
                        </div>

                        <div class="space-y-2">
                            @forelse($slots as $slot)
                                <div class="rounded-xl border bg-gray-50 p-3">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-sm font-medium text-gray-800">{{ substr($slot->heure_debut, 0, 5) }} → {{ substr($slot->heure_fin, 0, 5) }}</p>
                                        <div class="flex gap-2">
                                            <button wire:click="edit({{ $slot->id }})" class="text-xs text-blue-600">{{ __('Modifier') }}</button>
                                            <button wire:click="delete({{ $slot->id }})" class="text-xs text-red-600">{{ __('Supprimer') }}</button>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500 italic">{{ __('Aucun créneau.') }}</p>
                            @endforelse
                        </div>

                        <div class="pt-2 border-t">
                            @livewire('modifier-limite-jour', [
                                'date' => $day->toDateString(),
                                'user_id' => auth()->id(),
                                'fromAdmin' => false,
                            ], key('dispo-limit-'.$day->toDateString().'-'.auth()->id()))
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
