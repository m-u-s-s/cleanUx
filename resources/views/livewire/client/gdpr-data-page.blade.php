<div class="py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <div>
            <p class="text-sm font-bold uppercase text-indigo-600">RGPD</p>
            <h1 class="text-3xl font-black text-slate-900">Mes données personnelles</h1>
            <p class="text-sm text-slate-500 mt-2">
                Conformément au RGPD, vous pouvez exporter vos données ou demander leur suppression à tout moment.
            </p>
        </div>

        {{-- Export --}}
        <div class="rounded-2xl border bg-white p-6 shadow-sm space-y-3">
            <h2 class="text-lg font-bold text-slate-900">Exporter mes données (Art. 15 / 20)</h2>
            <p class="text-sm text-slate-600">
                Vous recevrez un fichier JSON contenant : profil, réservations, paiements, avis,
                réclamations, parrainages, vérifications d'identité, notifications.
            </p>

            @if($latestExport)
                <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-4">
                    <p class="text-sm font-bold text-emerald-900">
                        Export disponible — {{ $latestExport->reference }}
                    </p>
                    <p class="text-xs text-emerald-700 mt-1">
                        Généré le {{ optional($latestExport->fulfilled_at)->format('d/m/Y H:i') }} ·
                        Disponible jusqu'au {{ optional($latestExport->expires_at)->format('d/m/Y') }} ·
                        Taille : {{ $latestExport->export_file_size ? number_format($latestExport->export_file_size / 1024, 1, ',', ' ') . ' Ko' : '—' }}
                    </p>
                    <button wire:click="downloadExport({{ $latestExport->id }})"
                            class="mt-3 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                        Télécharger
                    </button>
                </div>
            @endif

            <button wire:click="requestExport"
                    wire:loading.attr="disabled"
                    class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                <span wire:loading.remove>Générer un nouvel export</span>
                <span wire:loading>Génération en cours...</span>
            </button>
        </div>

        {{-- Erasure --}}
        <div class="rounded-2xl border bg-white p-6 shadow-sm space-y-3">
            <h2 class="text-lg font-bold text-slate-900">Supprimer mon compte (Art. 17)</h2>
            <p class="text-sm text-slate-600">
                Demande de suppression effective sous {{ config('gdpr.erasure_grace_period_days') }} jours
                (délai de réflexion). Vos données personnelles seront anonymisées,
                les obligations comptables seront préservées conformément à la loi.
            </p>

            @if($activeErasure)
                <div class="rounded-xl bg-amber-50 border border-amber-200 p-4">
                    <p class="text-sm font-bold text-amber-900">
                        ⏳ Suppression programmée — {{ $activeErasure->reference }}
                    </p>
                    <p class="text-xs text-amber-700 mt-1">
                        Exécution prévue le
                        <span class="font-bold">{{ optional($activeErasure->grace_period_ends_at)->format('d/m/Y') }}</span>.
                        Vous pouvez annuler avant cette date.
                    </p>
                    <button wire:click="cancelErasure({{ $activeErasure->id }})"
                            wire:confirm="Annuler la suppression de votre compte ?"
                            class="mt-3 rounded-xl bg-white border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Annuler la suppression
                    </button>
                </div>
            @else
                <div class="rounded-xl bg-red-50 border border-red-200 p-4 space-y-3">
                    <label class="block text-xs font-semibold uppercase text-red-700">Raison (optionnel)</label>
                    <textarea wire:model="erasureReason" rows="2" maxlength="2000"
                              class="w-full rounded-xl border-red-300 text-sm bg-white"
                              placeholder="Pourquoi souhaitez-vous supprimer votre compte ?"></textarea>

                    <label class="flex items-start gap-3 text-sm text-red-800">
                        <input type="checkbox" wire:model="confirmErasure" class="rounded mt-0.5" />
                        <span>
                            Je comprends que cette action est <strong>irréversible</strong> après le délai de réflexion
                            ({{ config('gdpr.erasure_grace_period_days') }} jours).
                            Mes données personnelles seront anonymisées.
                        </span>
                    </label>
                    @error('confirmErasure') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                    <button wire:click="requestErasure"
                            wire:confirm="Programmer la suppression de votre compte ?"
                            class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                        Demander la suppression
                    </button>
                </div>
            @endif
        </div>

        {{-- History --}}
        <div class="rounded-2xl border bg-white shadow-sm">
            <div class="p-4 border-b">
                <h2 class="text-lg font-bold text-slate-900">Historique de mes demandes</h2>
            </div>
            <div class="divide-y">
                @forelse($requests as $req)
                    <div class="p-4 flex items-center justify-between">
                        <div>
                            <p class="font-mono text-xs text-slate-500">{{ $req->reference }}</p>
                            <p class="font-bold text-sm text-slate-900 mt-1">
                                {{ ucfirst($req->type) }}
                            </p>
                            <p class="text-xs text-slate-500 mt-1">
                                Demandé le {{ optional($req->requested_at)->format('d/m/Y H:i') }}
                                @if($req->fulfilled_at)
                                    · Exécuté le {{ $req->fulfilled_at->format('d/m/Y') }}
                                @endif
                            </p>
                        </div>
                        <span @class([
                            'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold',
                            'bg-emerald-100 text-emerald-800' => $req->status === 'fulfilled',
                            'bg-amber-100 text-amber-800' => in_array($req->status, ['pending', 'processing', 'awaiting_grace_period', 'awaiting_confirmation']),
                            'bg-red-100 text-red-800' => $req->status === 'rejected',
                            'bg-slate-100 text-slate-700' => in_array($req->status, ['cancelled', 'expired']),
                        ])>{{ $req->status }}</span>
                    </div>
                @empty
                    <div class="p-8 text-center text-slate-400">Aucune demande enregistrée.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
