<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-4">
        <div>
            <label class="text-sm text-gray-700 font-medium">Priorité :</label>
            <select wire:model.live="priorite" class="border rounded px-2 py-1 text-sm">
                <option value="">— Toutes —</option>
                <option value="normale">Normale</option>
                <option value="haute">Haute</option>
                <option value="urgente">Urgente</option>
            </select>
        </div>

        <div>
            <label class="text-sm text-gray-700 font-medium">Statut :</label>
            <select wire:model.live="filtreStatus" class="border rounded px-2 py-1 text-sm">
                <option value="">— Tous —</option>
                <option value="en_attente">En attente</option>
                <option value="confirme">Confirmé</option>
                <option value="en_route">En route</option>
                <option value="sur_place">Sur place</option>
                <option value="termine">Terminé</option>
                <option value="refuse">Refusé</option>
            </select>
        </div>

        <div class="flex-1 min-w-[220px]">
            <label class="text-sm text-gray-700 font-medium">Recherche :</label>
            <input
                type="text"
                wire:model.live.debounce.350ms="search"
                class="w-full border rounded px-3 py-2 text-sm"
                placeholder="Client, adresse, ville, référence..."
            >
        </div>
    </div>

    @forelse($rendezVous as $rdv)
        <div class="{{ $selectedRendezVous?->id === $rdv->id ? 'ring-2 ring-indigo-300 rounded-2xl' : '' }}">
            <x-rdv-cleaning-card :rdv="$rdv">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm text-slate-600 pt-2">
                    <div class="space-y-1">
                        <p><span class="font-medium text-slate-800">Début mission :</span> {{ $rdv->mission_started_at?->format('d/m/Y H:i') ?? '—' }}</p>
                        <p><span class="font-medium text-slate-800">Arrivée :</span> {{ $rdv->mission_arrived_at?->format('d/m/Y H:i') ?? '—' }}</p>
                        <p><span class="font-medium text-slate-800">Présence client :</span> {{ $rdv->client_presence_confirmed_at ? 'Confirmée' : 'Non confirmée' }}</p>
                    </div>
                    <div class="space-y-1">
                        <p><span class="font-medium text-slate-800">Photos avant :</span> {{ is_array($rdv->photos_avant) ? count($rdv->photos_avant) : 0 }}</p>
                        <p><span class="font-medium text-slate-800">Photos après :</span> {{ is_array($rdv->photos_apres) ? count($rdv->photos_apres) : 0 }}</p>
                        <p><span class="font-medium text-slate-800">Signature client :</span> {{ $rdv->client_signature_path ? 'Oui' : 'Non' }}</p>
                    </div>
                </div>

                @if($rdv->mission)
                    <div class="mt-3 rounded-xl border border-indigo-200 bg-indigo-50 p-3 text-sm text-indigo-800">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <span class="font-semibold">Mission liée :</span>
                                #{{ $rdv->mission->id }} — statut {{ $rdv->mission->status }}
                            </div>

                            <button
                                wire:click="{{ $selectedRendezVous?->id === $rdv->id ? 'clearSelectedRdv' : 'selectRdv('.$rdv->id.')' }}"
                                class="inline-flex items-center rounded-lg border border-indigo-300 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 transition"
                            >
                                {{ $selectedRendezVous?->id === $rdv->id ? 'Fermer le panneau mission' : 'Ouvrir la mission' }}
                            </button>
                        </div>
                    </div>
                @endif

                @if($rdv->remarque_terrain)
                    <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                        <span class="font-semibold">Remarque terrain :</span> {{ $rdv->remarque_terrain }}
                    </div>
                @endif

                @if($rdv->incident_terrain)
                    <div class="mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                        <span class="font-semibold">Incident :</span> {{ $rdv->incident_terrain }}
                    </div>
                @endif

                <div class="flex flex-wrap gap-3 text-sm pt-3">
                    @if($rdv->status === 'en_attente')
                        <button
                            wire:click="mettreAJourStatut({{ $rdv->id }}, 'confirme')"
                            class="px-3 py-1.5 rounded bg-green-100 text-green-700 hover:bg-green-200 transition">
                            ✅ Confirmer
                        </button>

                        <button
                            wire:click="mettreAJourStatut({{ $rdv->id }}, 'refuse')"
                            class="px-3 py-1.5 rounded bg-red-100 text-red-700 hover:bg-red-200 transition">
                            ❌ Refuser
                        </button>
                    @endif

                    @if($rdv->status === 'confirme')
                        <button
                            wire:click="mettreAJourStatut({{ $rdv->id }}, 'en_route')"
                            class="px-3 py-1.5 rounded bg-blue-100 text-blue-700 hover:bg-blue-200 transition">
                            🚗 En route
                        </button>
                    @endif

                    @if($rdv->status === 'en_route')
                        <button
                            wire:click="ouvrirCheckInMission({{ $rdv->id }})"
                            class="px-3 py-1.5 rounded bg-indigo-100 text-indigo-700 hover:bg-indigo-200 transition">
                            📍 Check-in sur place
                        </button>
                    @endif

                    @if($rdv->status === 'sur_place')
                        <button
                            wire:click="ouvrirRapportFinMission({{ $rdv->id }})"
                            class="px-3 py-1.5 rounded bg-emerald-100 text-emerald-700 hover:bg-emerald-200 transition">
                            ✅ Clôturer la mission
                        </button>
                    @endif

                    @if($rdv->telephone_client)
                        <a
                            href="tel:{{ $rdv->telephone_client }}"
                            class="px-3 py-1.5 rounded bg-green-100 text-green-700 hover:bg-green-200 transition">
                            📞 Appeler
                        </a>
                    @endif

                    @if($rdv->adresse || $rdv->ville)
                        <a
                            href="https://www.google.com/maps/search/?api=1&query={{ urlencode(($rdv->adresse ?? '') . ' ' . ($rdv->ville ?? '')) }}"
                            target="_blank"
                            class="px-3 py-1.5 rounded bg-sky-100 text-sky-700 hover:bg-sky-200 transition">
                            📍 GPS
                        </a>
                    @endif

                    @if(Route::has('employe.incident'))
                        <a href="{{ route('employe.incident') }}" class="px-3 py-1.5 rounded bg-red-100 text-red-700 hover:bg-red-200 transition">
                            🚨 Signaler un incident
                        </a>
                    @endif
                </div>
            </x-rdv-cleaning-card>
        </div>
    @empty
        <div class="bg-white border rounded-xl p-6 text-center text-gray-500 italic">
            Aucun rendez-vous trouvé.
        </div>
    @endforelse

    <div class="mt-4">
        {{ $rendezVous->links() }}
    </div>

    @if($showCheckInModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
        <div class="bg-white w-full max-w-2xl rounded-xl shadow-xl p-6 space-y-4">
            <div>
                <h3 class="text-xl font-semibold text-gray-800">Check-in terrain</h3>
                <p class="text-sm text-gray-500">Validez l’arrivée, l’état d’accès et capturez les éléments de départ.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach([
                    'acces_ok' => 'Accès OK',
                    'materiel_ok' => 'Matériel OK',
                    'zone_securisee' => 'Zone sécurisée',
                    'etat_initial_capture' => 'État initial capturé',
                    'client_present' => 'Client présent',
                ] as $key => $label)
                <label class="flex items-center gap-2 rounded-lg border p-3 text-sm text-slate-700">
                    <input type="checkbox" wire:model="terrain_checklist.{{ $key }}">
                    <span>{{ $label }}</span>
                </label>
                @endforeach
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Remarque terrain</label>
                <textarea wire:model="remarque_terrain" rows="3" class="w-full border rounded px-3 py-2 text-sm" placeholder="Code d’accès, difficulté d’accès, état des lieux..."></textarea>
                @error('remarque_terrain') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Photos avant intervention</label>
                <input type="file" wire:model="photos_avant" multiple accept="image/*" class="w-full text-sm border rounded px-3 py-2">
                @error('photos_avant.*') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            @if($photos_avant)
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @foreach($photos_avant as $photo)
                <img src="{{ $photo->temporaryUrl() }}" alt="Aperçu photo avant intervention" class="w-full h-28 object-cover rounded border">
                @endforeach
            </div>
            @endif

            <div class="flex justify-end gap-3 pt-2">
                <button wire:click="fermerCheckInMission" class="px-4 py-2 rounded border text-sm text-gray-700 hover:bg-gray-50">
                    Annuler
                </button>
                <button wire:click="sauverCheckInMission" class="px-4 py-2 rounded bg-indigo-600 text-white text-sm hover:bg-indigo-700">
                    Enregistrer le check-in
                </button>
            </div>
        </div>
    </div>
    @endif

    @if($showRapportModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4 overflow-y-auto">
        <div class="bg-white w-full max-w-3xl rounded-xl shadow-xl p-6 space-y-4 my-8">
            <div>
                <h3 class="text-xl font-semibold text-gray-800">Rapport de fin de mission</h3>
                <p class="text-sm text-gray-500">Ajoutez le compte-rendu complet avant de clôturer la mission.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Durée réelle (minutes)</label>
                    <input type="number" min="15" wire:model="duree_reelle" class="w-full border rounded px-3 py-2 text-sm">
                    @error('duree_reelle') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <label class="flex items-center gap-2 rounded-lg border p-3 text-sm text-slate-700 mt-6 md:mt-0">
                    <input type="checkbox" wire:model="client_presence_confirmee">
                    <span>Présence client confirmée</span>
                </label>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Commentaire de fin de mission</label>
                <textarea wire:model="commentaire_fin_mission" rows="4" class="w-full border rounded px-3 py-2 text-sm" placeholder="Résumé du travail effectué, état final, recommandations..."></textarea>
                @error('commentaire_fin_mission') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Incident ou anomalie terrain</label>
                <textarea wire:model="incident_terrain" rows="3" class="w-full border rounded px-3 py-2 text-sm" placeholder="Détaillez ici un incident, un litige ou une anomalie constatée."></textarea>
                @error('incident_terrain') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Photos après intervention</label>
                <input type="file" wire:model="photos_apres" multiple accept="image/*" class="w-full text-sm border rounded px-3 py-2">
                @error('photos_apres.*') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            @if($photos_apres)
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @foreach($photos_apres as $photo)
                <img src="{{ $photo->temporaryUrl() }}" alt="Aperçu photo après intervention" class="w-full h-28 object-cover rounded border">
                @endforeach
            </div>
            @endif

            <div x-data="cleanuxSignaturePad($wire)" class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="block text-sm font-medium text-gray-700">Signature client (optionnelle)</label>
                    <button type="button" @click="clear()" class="text-sm text-slate-500 hover:text-slate-700">Effacer</button>
                </div>
                <canvas x-ref="canvas" x-init="init()" class="w-full h-44 rounded-xl border bg-slate-50"></canvas>
                <p class="text-xs text-slate-500">Le client peut signer au doigt ou à la souris directement sur cet espace.</p>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button wire:click="fermerRapportFinMission" class="px-4 py-2 rounded border text-sm text-gray-700 hover:bg-gray-50">
                    Annuler
                </button>

                <button wire:click="sauverRapportFinMission" class="px-4 py-2 rounded bg-emerald-600 text-white text-sm hover:bg-emerald-700">
                    Enregistrer et terminer
                </button>
            </div>
        </div>
    </div>
    @endif

    @once
        <script>
            window.cleanuxSignaturePad = function ($wire) {
                return {
                    canvas: null,
                    ctx: null,
                    drawing: false,
                    init() {
                        this.canvas = this.$refs.canvas;
                        this.ctx = this.canvas.getContext('2d');
                        const ratio = window.devicePixelRatio || 1;
                        const rect = this.canvas.getBoundingClientRect();
                        this.canvas.width = rect.width * ratio;
                        this.canvas.height = rect.height * ratio;
                        this.ctx.scale(ratio, ratio);
                        this.ctx.lineWidth = 2;
                        this.ctx.lineCap = 'round';
                        this.ctx.strokeStyle = '#0f172a';

                        const start = (x, y) => {
                            this.drawing = true;
                            this.ctx.beginPath();
                            this.ctx.moveTo(x, y);
                        };
                        const draw = (x, y) => {
                            if (! this.drawing) return;
                            this.ctx.lineTo(x, y);
                            this.ctx.stroke();
                            $wire.set('client_signature_data', this.canvas.toDataURL('image/png'));
                        };
                        const stop = () => {
                            this.drawing = false;
                        };
                        const pos = (e) => {
                            const r = this.canvas.getBoundingClientRect();
                            if (e.touches && e.touches[0]) {
                                return [e.touches[0].clientX - r.left, e.touches[0].clientY - r.top];
                            }
                            return [e.clientX - r.left, e.clientY - r.top];
                        };

                        this.canvas.addEventListener('mousedown', e => { const [x,y] = pos(e); start(x,y); });
                        this.canvas.addEventListener('mousemove', e => { const [x,y] = pos(e); draw(x,y); });
                        window.addEventListener('mouseup', stop);
                        this.canvas.addEventListener('touchstart', e => { e.preventDefault(); const [x,y] = pos(e); start(x,y); }, { passive: false });
                        this.canvas.addEventListener('touchmove', e => { e.preventDefault(); const [x,y] = pos(e); draw(x,y); }, { passive: false });
                        window.addEventListener('touchend', stop);
                    },
                    clear() {
                        if (! this.ctx || ! this.canvas) return;
                        this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                        $wire.set('client_signature_data', null);
                    }
                }
            }
        </script>
    @endonce
</div>
