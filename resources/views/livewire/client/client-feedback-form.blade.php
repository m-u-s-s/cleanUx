<div class="py-8">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <div>
            <p class="text-sm font-bold uppercase text-indigo-600">Votre avis</p>
            <h1 class="text-3xl font-black text-slate-900">
                Notez votre prestation #{{ $rendezVous->booking_reference }}
            </h1>
            <p class="text-sm text-slate-500 mt-2">
                Votre prestataire : <span class="font-semibold">{{ $rendezVous->employe?->name ?? '—' }}</span>
                @if($rendezVous->mission_finished_at)
                    · terminée le {{ $rendezVous->mission_finished_at->format('d/m/Y') }}
                @endif
            </p>
        </div>

        @if($submitted)
            <div class="rounded-3xl border border-emerald-200 bg-emerald-50 p-6">
                <p class="text-lg font-bold text-emerald-900">Merci !</p>
                <p class="text-sm text-emerald-700 mt-2">
                    Votre avis a été enregistré. Il sera publié une fois que le prestataire vous aura noté
                    ou après 14 jours.
                </p>
                <a href="{{ route('client.dashboard') }}"
                   class="inline-block mt-4 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    Retour au dashboard
                </a>
            </div>
        @else
            <div class="rounded-3xl border bg-white p-6 shadow-sm space-y-6">

                {{-- Note globale (étoiles cliquables) --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-3">
                        Note globale
                    </label>
                    <div class="flex items-center gap-1">
                        @for($i = 1; $i <= 5; $i++)
                            <button type="button"
                                    wire:click="setRating({{ $i }})"
                                    class="text-4xl transition-colors {{ $i <= $rating ? 'text-amber-400' : 'text-slate-200' }} hover:text-amber-300">
                                ★
                            </button>
                        @endfor
                        <span class="ml-3 text-sm text-slate-500">{{ $rating }}/5</span>
                    </div>
                    @error('rating') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Dimensions --}}
                @php
                    $dimensions = [
                        ['key' => 'punctuality',   'label' => 'Ponctualité',         'value' => $punctuality],
                        ['key' => 'quality',       'label' => 'Qualité',             'value' => $quality],
                        ['key' => 'communication', 'label' => 'Communication',       'value' => $communication],
                        ['key' => 'value',         'label' => 'Rapport qualité/prix','value' => $value],
                    ];
                @endphp
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($dimensions as $dim)
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <p class="text-sm font-semibold text-slate-700">{{ $dim['label'] }}</p>
                            <div class="flex items-center gap-1 mt-2">
                                @for($i = 1; $i <= 5; $i++)
                                    <button type="button"
                                            wire:click="setDimension('{{ $dim['key'] }}', {{ $i }})"
                                            class="text-2xl transition-colors {{ $i <= ($dim['value'] ?? 0) ? 'text-amber-400' : 'text-slate-200' }} hover:text-amber-300">
                                        ★
                                    </button>
                                @endfor
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Commentaire --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">
                        Commentaire (optionnel)
                    </label>
                    <textarea wire:model="comment" rows="4"
                              maxlength="2000"
                              class="w-full rounded-2xl border-slate-300 text-sm"
                              placeholder="Partagez votre expérience…"></textarea>
                    @error('comment') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Public toggle --}}
                <label class="flex items-start gap-3 text-sm text-slate-700 cursor-pointer">
                    <input type="checkbox" wire:model="is_public" class="rounded mt-0.5" />
                    <span>
                        <span class="font-semibold">Publier mon avis publiquement</span>
                        <span class="block text-xs text-slate-500 mt-1">
                            Décochez pour garder cet avis privé (visible uniquement par CleanUx et le prestataire).
                        </span>
                    </span>
                </label>

                @if($globalError)
                    <div class="rounded-2xl bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                        {{ $globalError }}
                    </div>
                @endif

                <div class="flex justify-end gap-3 pt-4 border-t">
                    <a href="{{ route('client.dashboard') }}"
                       class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Annuler
                    </a>
                    <button wire:click="submit"
                            class="rounded-xl bg-indigo-600 px-6 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        {{ $existingFeedback ? 'Mettre à jour mon avis' : 'Envoyer mon avis' }}
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>
