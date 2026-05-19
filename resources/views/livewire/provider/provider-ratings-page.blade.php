<div class="py-8">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <div>
            <p class="text-sm font-bold uppercase text-indigo-600">Mes avis</p>
            <h1 class="text-3xl font-black text-slate-900">Vos avis clients</h1>
            <p class="text-sm text-slate-500 mt-2">
                Répondez aux avis publics pour renforcer la confiance.
            </p>
        </div>

        @if($profile && $profile->rating_count > 0)
            <div class="rounded-3xl bg-white border shadow-sm p-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-xs uppercase font-bold text-slate-500">Note moyenne</p>
                        <p class="text-3xl font-black text-amber-500">
                            {{ number_format((float) $profile->rating_avg, 1, ',', ' ') }}
                            <span class="text-base font-semibold text-slate-400">/ 5</span>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs uppercase font-bold text-slate-500">Avis publiés</p>
                        <p class="text-3xl font-black text-slate-900">{{ $profile->rating_count }}</p>
                    </div>
                    @if(! empty($profile->rating_dimensions))
                        @foreach($profile->rating_dimensions as $key => $info)
                            @php
                                $labels = ['punctuality' => 'Ponctualité', 'quality' => 'Qualité', 'communication' => 'Communication', 'value' => 'Qualité/prix'];
                            @endphp
                            <div>
                                <p class="text-xs uppercase font-bold text-slate-500">{{ $labels[$key] ?? $key }}</p>
                                <p class="text-2xl font-bold text-slate-700">
                                    {{ number_format((float) $info['avg'], 1, ',', ' ') }}
                                </p>
                            </div>
                            @if($loop->iteration >= 2) @break @endif
                        @endforeach
                    @endif
                </div>
            </div>
        @endif

        {{-- Filtres --}}
        <div class="flex flex-wrap gap-2">
            @foreach([
                'all' => 'Tous',
                'pending_response' => 'En attente de réponse',
                'low' => 'Notes ≤ 3',
                'hidden' => 'Masqués',
            ] as $key => $label)
                <button wire:click="$set('filter', '{{ $key }}')"
                        class="rounded-xl px-3 py-1.5 text-xs font-semibold {{ $filter === $key ? 'bg-indigo-600 text-white' : 'bg-white border text-slate-700 hover:bg-slate-50' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Liste --}}
        <div class="space-y-4">
            @forelse($ratings as $r)
                <div class="rounded-2xl bg-white border p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-3">
                                <span class="text-amber-400 text-lg">
                                    {{ str_repeat('★', (int) ($r->rating ?? $r->note)) }}{{ str_repeat('☆', max(0, 5 - (int) ($r->rating ?? $r->note))) }}
                                </span>
                                <span class="text-sm font-semibold text-slate-700">
                                    {{ $r->client?->name ?? 'Client' }}
                                </span>
                                @if($r->is_hidden)
                                    <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-800">
                                        Masqué
                                    </span>
                                @elseif($r->status === \App\Models\Feedback::STATUS_PENDING)
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-800">
                                        En attente (publication blind)
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-slate-500 mt-1">
                                {{ $r->rendezVous?->booking_reference ? '#'.$r->rendezVous->booking_reference.' · ' : '' }}
                                {{ optional($r->published_at ?? $r->answered_at)->format('d/m/Y') }}
                            </p>
                        </div>
                    </div>

                    @if($r->effectiveComment())
                        <p class="text-sm text-slate-700 mt-3">{{ $r->effectiveComment() }}</p>
                    @endif

                    @if($r->provider_response && $replyingTo !== $r->id)
                        <div class="mt-4 ml-4 rounded-2xl bg-slate-50 border-l-4 border-indigo-400 p-4">
                            <p class="text-xs font-bold text-indigo-700 uppercase">Votre réponse</p>
                            <p class="text-sm text-slate-700 mt-1">{{ $r->provider_response }}</p>
                            <p class="text-xs text-slate-400 mt-1">
                                {{ optional($r->provider_responded_at)->format('d/m/Y') }}
                            </p>
                            <button wire:click="startReply({{ $r->id }})"
                                    class="text-xs font-semibold text-indigo-600 hover:underline mt-2">
                                Modifier
                            </button>
                        </div>
                    @endif

                    {{-- Reply form --}}
                    @if($replyingTo === $r->id)
                        <div class="mt-4 rounded-2xl bg-indigo-50 border border-indigo-200 p-4 space-y-2">
                            <label class="text-sm font-semibold text-indigo-900">Votre réponse</label>
                            <textarea wire:model="responseText" rows="3" maxlength="1000"
                                      class="w-full rounded-xl border-indigo-300 text-sm"></textarea>
                            @error('responseText') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            <div class="flex justify-end gap-2">
                                <button wire:click="cancelReply"
                                        class="rounded-xl border px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                    Annuler
                                </button>
                                <button wire:click="submitReply"
                                        class="rounded-xl bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">
                                    Publier la réponse
                                </button>
                            </div>
                        </div>
                    @endif

                    {{-- Report form --}}
                    @if($reportingId === $r->id)
                        <div class="mt-4 rounded-2xl bg-red-50 border border-red-200 p-4 space-y-3">
                            <label class="text-sm font-semibold text-red-900">Signaler cet avis</label>
                            <select wire:model="reportReason" class="w-full rounded-xl border-red-300 text-sm">
                                <option value="spam">Spam</option>
                                <option value="offensive">Contenu offensant</option>
                                <option value="fake">Faux avis</option>
                                <option value="irrelevant">Non pertinent</option>
                                <option value="discloses_personal_info">Divulgation d'infos personnelles</option>
                                <option value="harassment">Harcèlement</option>
                                <option value="other">Autre</option>
                            </select>
                            <textarea wire:model="reportDetails" rows="2" maxlength="1000"
                                      class="w-full rounded-xl border-red-300 text-sm"
                                      placeholder="Détails (optionnel)"></textarea>
                            <div class="flex justify-end gap-2">
                                <button wire:click="cancelReport"
                                        class="rounded-xl border px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                    Annuler
                                </button>
                                <button wire:click="submitReport"
                                        class="rounded-xl bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700">
                                    Envoyer le signalement
                                </button>
                            </div>
                        </div>
                    @endif

                    {{-- Actions --}}
                    @if($replyingTo !== $r->id && $reportingId !== $r->id && ! $r->is_hidden)
                        <div class="flex gap-3 mt-4 pt-3 border-t">
                            @if(! $r->provider_response)
                                <button wire:click="startReply({{ $r->id }})"
                                        class="text-xs font-semibold text-indigo-600 hover:underline">
                                    Répondre
                                </button>
                            @endif
                            <button wire:click="startReport({{ $r->id }})"
                                    class="text-xs font-semibold text-red-600 hover:underline">
                                Signaler
                            </button>
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-2xl bg-white border border-dashed p-10 text-center text-slate-400">
                    Aucun avis pour le moment.
                </div>
            @endforelse
        </div>

        <div>{{ $ratings->links() }}</div>
    </div>
</div>
