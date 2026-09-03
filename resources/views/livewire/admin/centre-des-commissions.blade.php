{{--
    LE CENTRE DES COMMISSIONS.

    Quatre onglets : ce qui est réglé, ce que ça donnerait, ce que disent les chiffres, et ce qui
    a changé. Le simulateur vient AVANT le conseiller, parce qu'on écrit moins de bêtises quand on
    peut essayer sans écrire.

    Chaque conseil porte son chiffre et son geste. Aucun ne s'applique tout seul : une
    recommandation sur l'argent se vérifie, elle ne s'obéit pas.
--}}
<div class="space-y-6">

    <x-page-shell
        :eyebrow="__('Propriété de la plateforme')"
        :title="__('Centre des commissions')"
        :subtitle="__('Ce que la plateforme prélève, métier par métier, zone par zone — et ce que les chiffres en disent.')" />

    @if($message)
        <div class="brio-alerte brio-alerte-success !mb-0"><span aria-hidden="true">✅</span><span>{{ $message }}</span></div>
    @endif

    @if($erreur)
        <div class="brio-alerte brio-alerte-danger !mb-0"><span aria-hidden="true">⚠️</span><span>{{ $erreur }}</span></div>
    @endif

    <x-app-card>
        <div class="mb-4 flex flex-wrap gap-2">
            @foreach ([
                'regles' => __('Règles') . ' (' . $this->regles->count() . ')',
                'simulateur' => __('Simulateur'),
                'conseiller' => __('Le conseiller') . ' (' . count($this->conseils) . ')',
                'historique' => __('Historique'),
            ] as $cle => $libelleOnglet)
                <button type="button" wire:click="$set('onglet', '{{ $cle }}')"
                        class="{{ $onglet === $cle ? 'brio-btn-primary' : 'brio-btn-secondary' }} !px-3 !py-1.5 !text-xs">
                    {{ $libelleOnglet }}
                </button>
            @endforeach
        </div>

        {{-- ── LES RÈGLES ──────────────────────────────────────────────── --}}
        @if($onglet === 'regles')
            <div class="grid gap-4 lg:grid-cols-3">
                <div class="space-y-2 lg:col-span-2">
                    @forelse($this->regles as $regle)
                        <div class="brio-list-item !p-3 {{ $regle->is_active ? '' : 'opacity-60' }}"
                             wire:key="regle-{{ $regle->id }}">
                            <div class="min-w-0">
                                <p class="font-semibold">
                                    {{ $regle->label }}
                                    <span class="ml-1 font-black tabular-nums">{{ rtrim(rtrim(number_format($regle->percent, 2, ',', ' '), '0'), ',') }} %</span>
                                </p>
                                <p class="text-xs opacity-70">
                                    {{ $regle->libelleDuCas() }}
                                    @if($regle->min_cents !== null) · {{ __('plancher') }} <x-money :amount="$regle->min_cents / 100" /> @endif
                                    @if($regle->priority > 0) · {{ __('priorité') }} {{ $regle->priority }} @endif
                                    @if($regle->starts_on || $regle->ends_on)
                                        · {{ $regle->starts_on?->format('d/m/Y') ?? '…' }} → {{ $regle->ends_on?->format('d/m/Y') ?? '…' }}
                                    @endif
                                </p>
                                @if($regle->note)
                                    <p class="mt-1 text-xs opacity-70">{{ $regle->note }}</p>
                                @endif
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <x-ui.badge :tone="$regle->is_active ? 'success' : 'neutral'"
                                            :label="$regle->is_active ? __('Active') : __('Suspendue')" />
                                <button type="button" wire:click="editerLaRegle({{ $regle->id }})"
                                        class="brio-btn-ligne !text-xs">{{ __('Modifier') }}</button>
                                <button type="button" wire:click="basculerLaRegle({{ $regle->id }})"
                                        class="brio-btn-ligne !text-xs">{{ $regle->is_active ? __('Suspendre') : __('Réactiver') }}</button>
                                <button type="button" wire:click="supprimerLaRegle({{ $regle->id }})"
                                        wire:confirm="{{ __('Supprimer cette règle ? Les missions déjà conclues gardent le taux qu’elles ont payé.') }}"
                                        class="brio-btn-ligne-danger !text-xs">{{ __('Supprimer') }}</button>
                            </div>
                        </div>
                    @empty
                        <x-empty-state icon="⚖️" :title="__('Aucune règle : les taux d’origine s’appliquent')"
                                       :message="__('Prestations 15 %, location entre membres 25 %, pourboires 0 %. Posez une règle pour changer l’un d’eux — le reste ne bouge pas.')" />
                    @endforelse
                </div>

                {{-- LE FORMULAIRE --}}
                <div class="space-y-3">
                    <h3 class="text-sm font-bold">{{ $regleEnEdition ? __('Modifier la règle') : __('Nouvelle règle') }}</h3>

                    <div>
                        <label for="c-libelle" class="mb-1 block text-sm font-semibold">{{ __('Nom') }}</label>
                        <input id="c-libelle" wire:model="libelle" type="text" class="w-full"
                               placeholder="{{ __('Course à 8 %') }}">
                        @error('libelle') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs opacity-70">{{ __('C’est ce nom qui s’affiche sur les écrans pour expliquer le taux.') }}</p>
                    </div>

                    <div>
                        <label for="c-module" class="mb-1 block text-sm font-semibold">{{ __('Module') }}</label>
                        <select id="c-module" wire:model.live="module" class="w-full">
                            @foreach(\App\Models\CommissionRule::MODULES as $cle => $nom)
                                <option value="{{ $cle }}">{{ $nom }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="c-percent" class="mb-1 block text-sm font-semibold">{{ __('Taux (%)') }}</label>
                            <input id="c-percent" wire:model="pourcentage" type="number" step="0.01" min="0" max="100" class="w-full">
                            @error('pourcentage') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="c-plancher" class="mb-1 block text-sm font-semibold">{{ __('Plancher (centimes)') }}</label>
                            <input id="c-plancher" wire:model="plancherCents" type="number" min="0" class="w-full" placeholder="{{ __('par défaut') }}">
                        </div>
                    </div>

                    <p class="text-xs opacity-70">
                        {{ __('Pour rendre un service RÉELLEMENT gratuit, mettez le taux à 0 ET le plancher à 0 : sinon le plancher par défaut prélève quand même.') }}
                    </p>

                    @if($module === \App\Models\CommissionRule::MODULE_PRESTATION)
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label for="c-metier" class="mb-1 block text-sm font-semibold">{{ __('Métier') }}</label>
                                <select id="c-metier" wire:model="metier" class="w-full">
                                    <option value="">{{ __('Tous') }}</option>
                                    @foreach($this->metiers as $m)
                                        <option value="{{ $m->id }}" wire:key="m-{{ $m->id }}">{{ $m->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="c-zone" class="mb-1 block text-sm font-semibold">{{ __('Zone') }}</label>
                                <select id="c-zone" wire:model="zone" class="w-full">
                                    <option value="">{{ __('Toutes') }}</option>
                                    @foreach($this->zones as $z)
                                        <option value="{{ $z->id }}" wire:key="z-{{ $z->id }}">{{ $z->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @else
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label for="c-type" class="mb-1 block text-sm font-semibold">{{ __('Type de bien') }}</label>
                                <input id="c-type" wire:model="typeDeBien" type="text" class="w-full" placeholder="vehicle, stay…">
                            </div>

                            <div>
                                <label for="c-duree" class="mb-1 block text-sm font-semibold">{{ __('À partir de (jours)') }}</label>
                                <input id="c-duree" wire:model="dureeMinimum" type="number" min="1" class="w-full" placeholder="14">
                            </div>
                        </div>
                        <p class="text-xs opacity-70">
                            {{ __('« 20 %, puis 5 % après deux semaines » : deux règles, la seconde à partir de 14 jours.') }}
                        </p>
                    @endif

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="c-debut" class="mb-1 block text-sm font-semibold">{{ __('Du') }}</label>
                            <input id="c-debut" wire:model="debut" type="date" class="w-full">
                        </div>
                        <div>
                            <label for="c-fin" class="mb-1 block text-sm font-semibold">{{ __('Au') }}</label>
                            <input id="c-fin" wire:model="fin" type="date" class="w-full">
                        </div>
                    </div>

                    <div>
                        <label for="c-priorite" class="mb-1 block text-sm font-semibold">{{ __('Priorité') }}</label>
                        <input id="c-priorite" wire:model="priorite" type="number" min="0" max="999" class="w-full">
                        <p class="mt-1 text-xs opacity-70">{{ __('Ne sert qu’à départager deux règles aussi précises l’une que l’autre.') }}</p>
                    </div>

                    <div>
                        <label for="c-note" class="mb-1 block text-sm font-semibold">{{ __('Note interne') }}</label>
                        <input id="c-note" wire:model="noteInterne" type="text" class="w-full"
                               placeholder="{{ __('Pourquoi ce taux ?') }}">
                    </div>

                    <div class="flex gap-2">
                        <button type="button" wire:click="enregistrerLaRegle" class="brio-btn-primary flex-1">{{ __('Enregistrer') }}</button>
                        @if($regleEnEdition)
                            <button type="button" wire:click="nouvelleRegle" class="brio-btn-ligne">{{ __('Annuler') }}</button>
                        @endif
                    </div>
                </div>
            </div>

        {{-- ── LE SIMULATEUR ───────────────────────────────────────────── --}}
        @elseif($onglet === 'simulateur')
            @php($sim = $this->simulation)

            <div class="grid gap-4 lg:grid-cols-3">
                <div class="space-y-3">
                    <h3 class="text-sm font-bold">{{ __('Le cas à essayer') }}</h3>

                    <div>
                        <label for="s-module" class="mb-1 block text-sm font-semibold">{{ __('Module') }}</label>
                        <select id="s-module" wire:model.live="simModule" class="w-full">
                            @foreach(\App\Models\CommissionRule::MODULES as $cle => $nom)
                                <option value="{{ $cle }}">{{ $nom }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="s-metier" class="mb-1 block text-sm font-semibold">{{ __('Métier') }}</label>
                        <select id="s-metier" wire:model.live="simMetier" class="w-full">
                            <option value="">{{ __('Aucun') }}</option>
                            @foreach($this->metiers as $m)
                                <option value="{{ $m->id }}" wire:key="sm-{{ $m->id }}">{{ $m->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="s-zone" class="mb-1 block text-sm font-semibold">{{ __('Zone') }}</label>
                        <select id="s-zone" wire:model.live="simZone" class="w-full">
                            <option value="">{{ __('Aucune') }}</option>
                            @foreach($this->zones as $z)
                                <option value="{{ $z->id }}" wire:key="sz-{{ $z->id }}">{{ $z->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label for="s-duree" class="mb-1 block text-sm font-semibold">{{ __('Durée (jours)') }}</label>
                            <input id="s-duree" wire:model.live="simDuree" type="number" min="1" class="w-full">
                        </div>
                        <div>
                            <label for="s-montant" class="mb-1 block text-sm font-semibold">{{ __('Montant') }}</label>
                            <input id="s-montant" wire:model.live="simMontantEuros" type="number" min="0" class="w-full">
                        </div>
                    </div>
                </div>

                <div class="space-y-4 lg:col-span-2">
                    <x-app-card :title="__('Ce qui s’appliquerait')">
                        <p class="text-2xl font-black tracking-tight">
                            {{ rtrim(rtrim(number_format($sim['taux']->pourcentage(), 2, ',', ' '), '0'), ',') }} %
                        </p>
                        <p class="mt-1 text-sm opacity-70">{{ $sim['taux']->note() }}</p>

                        <div class="mt-4 grid grid-cols-3 gap-3 text-sm">
                            <div>
                                <p class="text-xs uppercase tracking-wide opacity-70">{{ __('Le client paie') }}</p>
                                <p class="font-bold tabular-nums"><x-money :amount="$sim['partage']['total_cents'] / 100" :currency="$sim['partage']['currency']" /></p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-wide opacity-70">{{ __('La plateforme garde') }}</p>
                                <p class="font-bold tabular-nums"><x-money :amount="$sim['partage']['platform_fee_cents'] / 100" :currency="$sim['partage']['currency']" /></p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-wide opacity-70">{{ __('Le prestataire reçoit') }}</p>
                                <p class="font-bold tabular-nums"><x-money :amount="$sim['partage']['provider_payout_cents'] / 100" :currency="$sim['partage']['currency']" /></p>
                            </div>
                        </div>

                        @if($sim['partage']['minimum_applied'])
                            <p class="mt-3 text-xs opacity-70">
                                {{ __('Le plancher de commission mord sur ce montant : le taux réellement encaissé est de :taux %.', [
                                    'taux' => round($sim['partage']['effective_commission_rate'] * 100, 2),
                                ]) }}
                            </p>
                        @endif
                    </x-app-card>

                    {{-- CE QUE LA RÈGLE GAGNANTE MASQUE : le piège classique d'un tel système. --}}
                    <x-app-card :title="__('Les règles qui couvrent ce cas')"
                                :subtitle="__('La première gagne. Les suivantes sont masquées par elle — utile à savoir avant de croire qu’on a baissé un prix.')">
                        <div class="space-y-2">
                            @forelse($sim['applicables'] as $index => $applicable)
                                <div class="brio-list-item !p-3" wire:key="app-{{ $applicable->id }}">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold">
                                            {{ $applicable->label }}
                                            <span class="tabular-nums">{{ rtrim(rtrim(number_format($applicable->percent, 2, ',', ' '), '0'), ',') }} %</span>
                                        </p>
                                        <p class="text-xs opacity-70">{{ $applicable->libelleDuCas() }}</p>
                                    </div>
                                    <x-ui.badge :tone="$index === 0 ? 'success' : 'neutral'"
                                                :label="$index === 0 ? __('Gagne') : __('Masquée')" />
                                </div>
                            @empty
                                <p class="text-sm opacity-70">
                                    {{ __('Aucune règle ne couvre ce cas : le taux d’origine du module s’applique.') }}
                                </p>
                            @endforelse
                        </div>
                    </x-app-card>
                </div>
            </div>

        {{-- ── LE CONSEILLER ───────────────────────────────────────────── --}}
        @elseif($onglet === 'conseiller')
            <p class="mb-3 text-sm opacity-70">
                {{ __('Aucune intelligence artificielle ici, et c’est voulu : chaque conseil porte le chiffre qui le déclenche. Un avis qu’on ne peut pas vérifier ne se conteste pas.') }}
            </p>

            <div class="space-y-3">
                @foreach($this->conseils as $conseil)
                    @php($tons = ['danger' => 'danger', 'attention' => 'warning', 'bien' => 'success', 'neutre' => 'neutral'])
                    <div class="brio-card !p-4" wire:key="conseil-{{ $loop->index }}">
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-bold">{{ $conseil['titre'] }}</p>
                            <x-ui.badge :tone="$tons[$conseil['ton']] ?? 'neutral'" :label="ucfirst($conseil['ton'])" />
                        </div>
                        <p class="mt-2 text-sm opacity-80">{{ $conseil['constat'] }}</p>
                        <p class="mt-2 text-sm">{{ $conseil['geste'] }}</p>
                    </div>
                @endforeach
            </div>

            <h3 class="mt-6 text-sm font-bold">{{ __('Ce que chaque métier rapporte') }}</h3>
            <div class="mt-2 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left">
                            <th class="py-2 pr-3 font-semibold">{{ __('Métier') }}</th>
                            <th class="py-2 pr-3 font-semibold">{{ __('Missions') }}</th>
                            <th class="py-2 pr-3 font-semibold">{{ __('Commission') }}</th>
                            <th class="py-2 pr-3 font-semibold">{{ __('Taux réglé') }}</th>
                            <th class="py-2 pr-3 font-semibold">{{ __('Taux encaissé') }}</th>
                            <th class="py-2 font-semibold">{{ __('Sans prestataire') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->lectureParMetier as $ligne)
                            <tr class="border-t border-slate-200/60 dark:border-slate-700" wire:key="lec-{{ $ligne['trade_id'] }}">
                                <td class="py-2 pr-3 font-semibold">{{ $ligne['metier'] }}</td>
                                <td class="py-2 pr-3 tabular-nums">{{ $ligne['volume'] }}</td>
                                <td class="py-2 pr-3 tabular-nums"><x-money :amount="$ligne['commission_cents'] / 100" /></td>
                                <td class="py-2 pr-3 tabular-nums">{{ $ligne['taux_regle'] }} %</td>
                                <td class="py-2 pr-3 tabular-nums">{{ $ligne['taux_effectif'] }} %</td>
                                <td class="py-2 tabular-nums">{{ $ligne['part_sans_prestataire'] }} %</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-3 text-sm opacity-70">{{ __('Aucune mission sur les trente derniers jours.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        {{-- ── L'HISTORIQUE ────────────────────────────────────────────── --}}
        @else
            <p class="mb-3 text-sm opacity-70">
                {{ __('Un changement de taux qu’on ne peut pas dater ne se conteste pas. Tout est ici, même les règles supprimées.') }}
            </p>

            <div class="space-y-2">
                @forelse($this->historique as $revision)
                    <div class="brio-list-item !p-3" wire:key="rev-{{ $revision->id }}">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold">
                                {{ $revision->regle?->label ?? data_get($revision->snapshot, 'label', __('règle supprimée')) }}
                                <span class="font-normal opacity-70">
                                    @if($revision->percent_before !== null && $revision->percent_after !== null)
                                        {{ $revision->percent_before }} % → {{ $revision->percent_after }} %
                                    @elseif($revision->percent_after !== null)
                                        {{ __('créée à') }} {{ $revision->percent_after }} %
                                    @else
                                        {{ __('supprimée, elle était à') }} {{ $revision->percent_before }} %
                                    @endif
                                </span>
                            </p>
                            <p class="text-xs opacity-70">
                                {{ $revision->acteur?->name ?? __('inconnu') }}
                                · {{ $revision->created_at?->translatedFormat('d/m/Y à H:i') }}
                                · {{ $revision->actor_ip ?? '—' }}
                            </p>
                        </div>
                        <x-ui.badge tone="neutral" :label="$revision->action" />
                    </div>
                @empty
                    <p class="text-sm opacity-70">{{ __('Aucun changement enregistré.') }}</p>
                @endforelse
            </div>
        @endif
    </x-app-card>
</div>
