<div class="py-6 max-w-3xl mx-auto px-4">

    <div class="text-center mb-6">
        <p class="text-3xl mb-2">🤖</p>
        <h1 class="text-2xl font-black text-slate-900">Devis IA depuis photo</h1>
        <p class="text-sm text-slate-500">Prends une photo, choisis le métier, reçois un devis estimé en quelques secondes.</p>
    </div>

    @if (! $result)
        <div class="rounded-2xl bg-white border shadow-sm p-6">
            <div class="space-y-4">
                <div>
                    <label class="text-xs font-bold text-slate-600">Métier *</label>
                    <select wire:model="tradeId" class="w-full rounded-lg border-slate-300 text-sm mt-1">
                        <option value="">-- Choisir un métier --</option>
                        @foreach ($trades as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </select>
                    @error('tradeId') <p class="text-rose-500 text-xs">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="text-xs font-bold text-slate-600">Photo de la prestation *</label>
                    <input wire:model="photo" type="file" accept="image/*" class="w-full text-sm mt-1 file:rounded-lg file:border-0 file:bg-indigo-50 file:text-indigo-700 file:px-3 file:py-2 file:font-semibold">
                    @error('photo') <p class="text-rose-500 text-xs">{{ $message }}</p> @enderror
                    <p class="text-xs text-slate-400 mt-1">PNG/JPG/WEBP, max 5 MB. Conseil : éclairage clair, plan large.</p>
                </div>

                @if ($photo)
                    <div class="rounded-lg overflow-hidden border">
                        <img src="{{ $photo->temporaryUrl() }}" alt="Preview" class="w-full max-h-96 object-contain bg-slate-50">
                    </div>
                @endif

                <div>
                    <label class="text-xs font-bold text-slate-600">Note (optionnel)</label>
                    <textarea wire:model="note" rows="2" maxlength="1000" placeholder="Précisions, surface si connue, état actuel, contraintes..." class="w-full rounded-lg border-slate-300 text-sm mt-1"></textarea>
                </div>

                @if ($error)
                    <div class="rounded-lg bg-rose-50 border border-rose-200 p-3 text-sm text-rose-700">
                        {{ $error }}
                    </div>
                @endif

                <button wire:click="estimate" wire:loading.attr="disabled" @disabled($estimating)
                        class="w-full rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white py-3 text-sm font-bold disabled:opacity-50">
                    <span wire:loading.remove wire:target="estimate">Estimer mon devis</span>
                    <span wire:loading wire:target="estimate">🔄 Analyse en cours...</span>
                </button>
                <p class="text-xs text-slate-400 text-center">L'IA analyse votre photo en ~10 secondes. Le devis est indicatif (±15-20%).</p>
            </div>
        </div>
    @else
        {{-- Résultat estimation --}}
        <div class="rounded-2xl bg-white border shadow-sm p-6">
            <div class="text-center mb-6">
                <p class="text-xs uppercase font-bold text-emerald-600">Devis estimé</p>
                <h2 class="text-3xl font-black text-slate-900 mt-1">{{ $result['trade_name'] ?? '' }}</h2>
            </div>

            <div class="rounded-xl bg-gradient-to-br from-indigo-50 to-purple-50 p-6 text-center mb-4">
                <p class="text-xs text-slate-600">Fourchette de prix</p>
                <p class="text-4xl font-black text-indigo-700 mt-1">
                    {{ number_format($result['prix_min_eur'] ?? 0, 0, ',', ' ') }}€ –
                    {{ number_format($result['prix_max_eur'] ?? 0, 0, ',', ' ') }}€
                </p>
                <p class="text-xs text-slate-500 mt-2">
                    Durée estimée : <strong>{{ round(($result['duree_estimee_min'] ?? 0) / 60, 1) }}h</strong>
                    @if (!empty($result['surface_estimee_m2']))
                        · Surface : <strong>{{ $result['surface_estimee_m2'] }} m²</strong>
                    @endif
                </p>
            </div>

            @if (!empty($result['confiance']))
                <div class="mb-4">
                    <div class="flex justify-between text-xs mb-1">
                        <span class="text-slate-600">Confiance de l'IA</span>
                        <span class="font-bold text-{{ ($result['confiance'] ?? 0) >= 70 ? 'emerald' : (($result['confiance'] ?? 0) >= 50 ? 'amber' : 'rose') }}-600">
                            {{ $result['confiance'] }}%
                        </span>
                    </div>
                    <div class="w-full bg-slate-100 h-2 rounded">
                        <div class="bg-{{ ($result['confiance'] ?? 0) >= 70 ? 'emerald' : (($result['confiance'] ?? 0) >= 50 ? 'amber' : 'rose') }}-500 h-2 rounded transition-all" style="width: {{ $result['confiance'] }}%"></div>
                    </div>
                </div>
            @endif

            @if (!empty($result['etat_observe']))
                <div class="mb-3">
                    <p class="text-xs font-bold text-slate-600 uppercase">État observé</p>
                    <p class="text-sm text-slate-700 mt-1">{{ $result['etat_observe'] }}</p>
                </div>
            @endif

            @if (!empty($result['observations']))
                <div class="mb-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
                    💬 {{ $result['observations'] }}
                </div>
            @endif

            @if (!empty($result['options_suggerees']))
                <div class="mb-4">
                    <p class="text-xs font-bold text-slate-600 uppercase mb-2">Options suggérées</p>
                    <ul class="space-y-1">
                        @foreach ($result['options_suggerees'] as $opt)
                            <li class="text-sm text-slate-700">→ {{ $opt }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 text-xs text-amber-800 mb-4">
                ⚠ Devis indicatif basé sur analyse photo. Le prix final sera confirmé par le prestataire après visite ou échange.
            </div>

            <div class="grid grid-cols-2 gap-2">
                <button wire:click="reset_" class="rounded-lg border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Refaire une estimation
                </button>
                <a href="{{ Route::has('client.rendezvous.create') ? route('client.rendezvous.create') : '/dashboard/client/rendez-vous/nouveau' }}" class="rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 text-sm font-bold text-center">
                    Réserver maintenant →
                </a>
            </div>
        </div>
    @endif
</div>
