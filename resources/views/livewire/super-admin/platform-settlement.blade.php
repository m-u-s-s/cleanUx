{{--
    Le registre de règlement Brio.

    Même idiome visuel que le cockpit super-administrateur et que la console : mêmes coquilles,
    même largeur, même rythme. Ces espaces appartiennent à la même personne.

    L'ORDRE DE LA PAGE EST L'ORDRE DES PRIORITÉS. L'alerte sur les devises sans compte de secours
    passe AVANT les montants : un solde se lit à tout moment, alors qu'un secours manquant ne se
    découvre utilement que le jour où il ne manque pas encore.
--}}
<div class="min-h-screen bg-slate-50 dark:bg-slate-900">
    <div class="mx-auto max-w-7xl space-y-8 px-4 pb-16 pt-6 sm:px-6 lg:px-8">

        <div class="ui-page-header">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="ui-page-eyebrow">Super administration</p>
                    <h1 class="ui-page-title">Registre de règlement</h1>
                    <p class="ui-page-subtitle">
                        Où part la commission Brio, par devise — et par quelle banque elle transite.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="https://dashboard.stripe.com/settings/payouts"
                       target="_blank" rel="noopener noreferrer"
                       class="brio-btn-primary inline-flex items-center gap-2">
                        <span>Changer la banque chez Stripe</span>
                    </a>
                    @if(Route::has('super-admin.dashboard'))
                        <a href="{{ route('super-admin.dashboard') }}" class="brio-btn-secondary inline-flex items-center gap-2">
                            <span>Cockpit</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Pourquoi le bouton mène ailleurs, dit une fois et clairement. --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
            <p class="font-semibold text-slate-900 dark:text-white">Ce registre atteste, il ne redirige pas.</p>
            <p class="mt-2">
                La banque qui reçoit réellement les versements se règle chez Stripe, derrière sa double
                authentification et sa vérification bancaire. C’est délibéré : si cette page pouvait
                rediriger les versements, un compte super-administrateur compromis suffirait à détourner
                l’encaissement suivant.
            </p>
        </div>

        @if($avis)
            <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-800 dark:border-indigo-800 dark:bg-indigo-950 dark:text-indigo-200">
                {{ $avis }}
            </div>
        @endif

        {{-- L'INDICATEUR QUI COMPTE. Changer de banque prend deux minutes chez Stripe ; faire
             vérifier un IBAN ajouté dans l'urgence prend des jours ouvrés. --}}
        @if(count($devisesSansSecours) > 0)
            <div class="rounded-2xl border border-amber-300 bg-amber-50 p-5 dark:border-amber-700 dark:bg-amber-950">
                <p class="font-semibold text-amber-900 dark:text-amber-100">
                    Aucun compte de secours vérifié pour :
                    <span class="uppercase">{{ implode(', ', $devisesSansSecours) }}</span>
                </p>
                <p class="mt-2 text-sm text-amber-800 dark:text-amber-200">
                    Sur ces devises, un changement de banque prendra le délai de vérification bancaire —
                    plusieurs jours ouvrés — et non une journée. Déclarez un compte de secours dans une
                    autre banque, faites-le vérifier chez Stripe, puis marquez-le vérifié ici.
                </p>
            </div>
        @else
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                Chaque devise encaissée dispose d’un compte de secours vérifié : une bascule de banque
                est possible sans délai bancaire.
            </div>
        @endif

        {{-- Commission encaissée --}}
        <div>
            <h2 class="mb-3 text-lg font-semibold text-slate-900 dark:text-white">Commission encaissée</h2>
            @if(count($commission) === 0)
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Aucune commission enregistrée pour l’instant. La colonne se remplit à la capture du
                    paiement, quand la mission est clôturée.
                </p>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($commission as $devise => $ligne)
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
                            <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $devise }}</p>
                            <p class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">
                                {{ number_format($ligne['montant'], 2, ',', ' ') }}
                            </p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                sur {{ $ligne['missions'] }} mission(s)
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Registre par devise --}}
        <div>
            <h2 class="mb-3 text-lg font-semibold text-slate-900 dark:text-white">Comptes déclarés</h2>

            @if(count($comptesParDevise) === 0)
                <p class="text-sm text-slate-500 dark:text-slate-400">Aucun compte déclaré.</p>
            @endif

            @foreach($comptesParDevise as $devise => $comptes)
                <div class="mb-6">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        {{ $devise }}
                    </p>
                    {{-- Le tableau défile dans SON conteneur : la page ne doit jamais défiler
                         horizontalement sur un écran étroit. --}}
                    <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-700">
                        <table class="min-w-full divide-y divide-slate-200 bg-white text-sm dark:divide-slate-700 dark:bg-slate-800">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                                <tr>
                                    <th class="px-4 py-3">Libellé</th>
                                    <th class="px-4 py-3">Banque</th>
                                    <th class="px-4 py-3">IBAN</th>
                                    <th class="px-4 py-3">Rôle</th>
                                    <th class="px-4 py-3">État</th>
                                    <th class="px-4 py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                @foreach($comptes as $compte)
                                    <tr class="text-slate-700 dark:text-slate-200">
                                        <td class="px-4 py-3">
                                            <span class="font-medium text-slate-900 dark:text-white">{{ $compte->label }}</span>
                                            @if($compte->country)
                                                <span class="ml-1 text-xs text-slate-500 dark:text-slate-400">{{ $compte->country }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">{{ $compte->bank_name ?: '—' }}</td>
                                        <td class="px-4 py-3 font-mono">{{ $compte->libelleMasque() }}</td>
                                        <td class="px-4 py-3">
                                            @if($compte->role === \App\Models\PlatformSettlementAccount::ROLE_PRIMARY)
                                                <span class="rounded-full bg-indigo-100 px-2 py-1 text-xs font-semibold text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">Principal</span>
                                            @else
                                                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-700 dark:text-slate-200">Secours</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($compte->status === \App\Models\PlatformSettlementAccount::STATUS_VERIFIED)
                                                <span class="text-emerald-700 dark:text-emerald-300">Vérifié</span>
                                            @elseif($compte->status === \App\Models\PlatformSettlementAccount::STATUS_RETIRED)
                                                <span class="text-slate-400">Retiré</span>
                                            @else
                                                <span class="text-amber-700 dark:text-amber-300">Déclaré</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap gap-2">
                                                @if($compte->status === \App\Models\PlatformSettlementAccount::STATUS_DRAFT)
                                                    <button type="button" wire:click="marquerVerifie({{ $compte->id }})"
                                                            class="brio-btn-secondary text-xs">Marquer vérifié</button>
                                                @endif
                                                @if($compte->status === \App\Models\PlatformSettlementAccount::STATUS_VERIFIED
                                                    && $compte->role !== \App\Models\PlatformSettlementAccount::ROLE_PRIMARY)
                                                    <button type="button" wire:click="promouvoir({{ $compte->id }})"
                                                            class="brio-btn-secondary text-xs">Passer en principal</button>
                                                @endif
                                                @if($compte->status !== \App\Models\PlatformSettlementAccount::STATUS_RETIRED)
                                                    <button type="button" wire:click="retirer({{ $compte->id }})"
                                                            class="brio-btn-secondary text-xs">Retirer</button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Déclarer un compte --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Déclarer un compte</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Quatre derniers caractères de l’IBAN seulement — le registre reconnaît un compte, il ne
                le rejoue pas.
            </p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <label class="block">
                    <span class="text-sm text-slate-700 dark:text-slate-200">Libellé</span>
                    <input type="text" wire:model="label" class="ui-input mt-1 w-full" placeholder="BNP Fortis — secours">
                    @error('label') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="text-sm text-slate-700 dark:text-slate-200">Devise</span>
                    <input type="text" wire:model="currency" maxlength="3" class="ui-input mt-1 w-full" placeholder="eur">
                    @error('currency') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="text-sm text-slate-700 dark:text-slate-200">Pays</span>
                    <input type="text" wire:model="country" maxlength="2" class="ui-input mt-1 w-full" placeholder="BE">
                    @error('country') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="text-sm text-slate-700 dark:text-slate-200">Banque</span>
                    <input type="text" wire:model="bank_name" class="ui-input mt-1 w-full">
                    @error('bank_name') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="text-sm text-slate-700 dark:text-slate-200">Titulaire</span>
                    <input type="text" wire:model="holder_name" class="ui-input mt-1 w-full">
                    @error('holder_name') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="text-sm text-slate-700 dark:text-slate-200">4 derniers de l’IBAN</span>
                    <input type="text" wire:model="iban_last4" maxlength="4" class="ui-input mt-1 w-full" placeholder="4321">
                    @error('iban_last4') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="text-sm text-slate-700 dark:text-slate-200">Rôle</span>
                    <select wire:model="role" class="ui-input mt-1 w-full">
                        <option value="backup">Secours</option>
                        <option value="primary">Principal</option>
                    </select>
                    @error('role') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>
                <label class="block sm:col-span-2">
                    <span class="text-sm text-slate-700 dark:text-slate-200">Notes</span>
                    <input type="text" wire:model="notes" class="ui-input mt-1 w-full">
                    @error('notes') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>
            </div>

            <button type="button" wire:click="enregistrer" class="brio-btn-primary mt-4">
                Enregistrer au registre
            </button>
        </div>

        {{-- L'ATTESTATION : ce que Stripe a réellement exécuté. --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Derniers versements Stripe</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Lus chez Stripe, pas saisis ici : c’est ce qui distingue une attestation d’une
                déclaration.
            </p>

            @if(! ($versements['disponible'] ?? false))
                <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                    {{ $versements['raison'] ?? 'Lecture indisponible.' }}
                </p>
            @elseif(count($versements['versements'] ?? []) === 0)
                <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">Aucun versement pour l’instant.</p>
            @else
                <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3">Montant</th>
                                <th class="px-4 py-3">Devise</th>
                                <th class="px-4 py-3">Destination</th>
                                <th class="px-4 py-3">Arrivée</th>
                                <th class="px-4 py-3">État</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            @foreach($versements['versements'] as $versement)
                                <tr class="text-slate-700 dark:text-slate-200">
                                    <td class="px-4 py-3">{{ number_format($versement['montant'], 2, ',', ' ') }}</td>
                                    <td class="px-4 py-3 uppercase">{{ $versement['devise'] }}</td>
                                    <td class="px-4 py-3 font-mono text-xs">{{ $versement['destination'] ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $versement['arrivee'] ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $versement['statut'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
