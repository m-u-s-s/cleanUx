{{--
    LA FICHE D'UN RENDEZ-VOUS, ET DE QUOI AGIR.

    L'agenda montrait la semaine sans jamais donner prise dessus : reperer une mission sans
    intervenant obligeait a quitter l'ecran pour la page Missions. Tout ce qui se decide ici
    passe par `AgendaHebdomadaire`, qui relit la reservation SOUS la portee de zone.

    L'annulation n'y figure pas : elle passe par `CancellationV2\CancellationEngine` — frais,
    quotas, remboursement. Un bouton « Annuler » qui ecrirait le statut a la main court-circuiterait
    des regles d'argent.
--}}
{{-- UNE SEULE FORME DE DIRECTIVE PHP PAR VUE — en bloc ici, jamais en ligne. La forme en
     ligne se fait fermer par la premiere fermeture de bloc rencontree plus bas, et tout ce
     qui les separe part en PHP brut : la vue tombe sur « unexpected token endif », a une
     ligne qui n'a rien a voir. Ecrire la directive dans un commentaire suffit a l'armer. --}}
@php
    $rdv = $this->rdvSelectionne;
@endphp

@if($rdv)
    @php
        $intervenant = $rdv->intervenant();
        $urgent = $rdv->priorite === 'urgente';
        $mission = $rdv->missions->sortByDesc('id')->first();
        $duree = ($rdv->duree ?? $rdv->estimated_duration_minutes ?? 90);
    @endphp

    <div
        class="brio-modal-fond grid place-items-center p-4"
        x-data
        x-on:keydown.escape.window="$wire.fermerRdv()"
        wire:key="modale-rdv-{{ $rdv->id }}">
        {{-- Le fond ferme la modale ; `stop` sur le panneau evite qu'un clic dedans la ferme. --}}
        <div class="absolute inset-0" x-on:click="$wire.fermerRdv()" aria-hidden="true"></div>

        <div
            class="brio-modal {{ $urgent ? 'brio-modal-danger' : '' }}"
            role="dialog"
            aria-modal="true"
            aria-labelledby="titre-rdv-agenda"
            x-on:click.stop>

            <p class="brio-eyebrow">
                {{ $rdv->date?->translatedFormat('l d F') ?? $rdv->date }}
                · {{ substr((string) $rdv->heure, 0, 5) }}
            </p>

            <h2 id="titre-rdv-agenda" class="brio-modal-titre mt-2">
                {{ $rdv->service_display_name }}
            </h2>

            <div class="mt-3 flex flex-wrap items-center gap-2">
                <x-badge :status="$rdv->status" />
                <x-priority-badge :priority="$rdv->priorite" />

                @if($rdv->booking_reference)
                    <span class="brio-chip">{{ $rdv->booking_reference }}</span>
                @endif
            </div>

            {{-- ─────────────── CE QU'IL FAUT SAVOIR ─────────────── --}}
            <dl class="mt-5 grid grid-cols-1 gap-x-4 gap-y-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Client</dt>
                    <dd class="mt-0.5 font-semibold text-slate-900">{{ $rdv->client?->name ?? '—' }}</dd>
                    @if($rdv->contact_phone || $rdv->client?->phone)
                        <dd class="text-xs text-slate-500">{{ $rdv->contact_phone ?: $rdv->client?->phone }}</dd>
                    @endif
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Intervenant</dt>
                    <dd class="mt-0.5 font-semibold {{ $intervenant ? 'text-slate-900' : 'text-amber-600 dark:text-amber-400' }}">
                        {{ $intervenant?->name ?? 'Aucun — à assigner' }}
                    </dd>
                </div>

                @if($rdv->organizationAccount)
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Société</dt>
                        <dd class="mt-0.5 text-slate-900">{{ $rdv->organizationAccount->name }}</dd>
                        @if($rdv->organizationSite)
                            <dd class="text-xs text-slate-500">{{ $rdv->organizationSite->name }}</dd>
                        @endif
                    </div>
                @endif

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Lieu</dt>
                    <dd class="mt-0.5 text-slate-900">
                        {{ collect([$rdv->adresse, $rdv->code_postal, $rdv->ville])->filter()->implode(' · ') ?: '—' }}
                    </dd>
                    @if($rdv->serviceZone)
                        <dd class="text-xs text-slate-500">Zone : {{ $rdv->serviceZone->name }}</dd>
                    @endif
                </div>

                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Durée</dt>
                    <dd class="mt-0.5 tabular-nums text-slate-900">{{ $duree }} min <span class="text-xs text-slate-500">(+30 de trajet)</span></dd>
                </div>

                @if($rdv->final_price !== null || $rdv->estimated_price !== null)
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            {{ $rdv->final_price !== null ? 'Montant final' : 'Estimation' }}
                        </dt>
                        <dd class="mt-0.5 tabular-nums font-semibold text-slate-900">
                            {{-- La devise de LA RESERVATION : un rendez-vous marocain ne
                                 s'affiche pas en euros parce que l'admin est belge. --}}
                            <x-money :amount="(float) ($rdv->final_price ?? $rdv->estimated_price)" :currency="$rdv->currency" />
                        </dd>
                    </div>
                @endif
            </dl>

            @if($rdv->customer_comment)
                <div class="brio-note mt-4">
                    <span class="font-semibold">Brief du client :</span> {{ $rdv->customer_comment }}
                </div>
            @endif

            {{-- ─────────────── AFFECTER ET REPLANIFIER ─────────────── --}}
            <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Affecter · replanifier</p>

                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="sm:col-span-2">
                        <span class="ui-label">Intervenant</span>
                        <select wire:model="affectationEmploye" class="ui-input mt-1">
                            <option value="">— Choisir un intervenant —</option>
                            @foreach($this->employes as $employe)
                                <option value="{{ $employe->id }}">{{ $employe->name }}</option>
                            @endforeach
                        </select>
                        @error('affectationEmploye') <span class="ui-error-msg">{{ $message }}</span> @enderror
                    </label>

                    <label>
                        <span class="ui-label">Date</span>
                        <input type="date" wire:model="affectationDate" class="ui-input mt-1">
                        @error('affectationDate') <span class="ui-error-msg">{{ $message }}</span> @enderror
                    </label>

                    <label>
                        <span class="ui-label">Heure</span>
                        <input type="time" wire:model="affectationHeure" class="ui-input mt-1">
                        @error('affectationHeure') <span class="ui-error-msg">{{ $message }}</span> @enderror
                    </label>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" wire:click="enregistrerAffectation" wire:loading.attr="disabled" class="brio-btn-primary">
                        <span wire:loading.remove wire:target="enregistrerAffectation">Enregistrer</span>
                        <span wire:loading wire:target="enregistrerAffectation">Enregistrement…</span>
                    </button>

                    {{-- Le meme moteur que la page Missions : on ne reinvente pas un choix
                         d'intervenant a cote de celui qui fait autorite. --}}
                    <button type="button" wire:click="dispatchAutomatique" wire:loading.attr="disabled" class="brio-btn-secondary">
                        <span wire:loading.remove wire:target="dispatchAutomatique">⚡ Affectation automatique</span>
                        <span wire:loading wire:target="dispatchAutomatique">Recherche…</span>
                    </button>
                </div>
            </div>

            {{-- ─────────────── STATUT ET PRIORITÉ ─────────────── --}}
            <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Statut · priorité</p>

                <div class="mt-3 flex flex-wrap gap-2">
                    @if($rdv->status !== \App\Support\Domain\BookingStatus::CONFIRME)
                        <button type="button" wire:click="changerStatut('confirme')" class="brio-btn-secondary">✅ Confirmer</button>
                    @endif

                    @if($rdv->status !== \App\Support\Domain\BookingStatus::EN_ATTENTE)
                        <button type="button" wire:click="changerStatut('en_attente')" class="brio-btn-secondary">↩︎ Remettre en attente</button>
                    @endif

                    @if($rdv->status !== \App\Support\Domain\BookingStatus::TERMINE)
                        <button type="button" wire:click="changerStatut('termine')" class="brio-btn-secondary">🏁 Marquer terminé</button>
                    @endif

                    <button type="button" wire:click="basculerUrgence" class="{{ $urgent ? 'brio-btn-secondary' : 'brio-btn-danger' }}">
                        {{ $urgent ? '↓ Priorité normale' : '🚨 Marquer urgent' }}
                    </button>
                </div>
            </div>

            <div class="brio-modal-actions">
                @if($mission && Route::has('admin.missions.show'))
                    <a href="{{ route('admin.missions.show', $mission) }}" class="brio-btn-secondary">Fiche mission</a>
                @endif

                <button type="button" wire:click="fermerRdv" class="brio-btn-primary">Fermer</button>
            </div>
        </div>
    </div>
@endif
