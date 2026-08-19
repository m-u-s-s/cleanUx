<div class="space-y-5">
    @if($erreur)
        {{-- LE MOTIF DU DOMAINE S'AFFICHE : « la liste est figée depuis 10:30 » dit ce qu'un
             « une erreur est survenue » laisserait deviner, et fait réessayer pour rien. --}}
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">
            {{ $erreur }}
        </div>
    @endif

    @if(($retard['en_retard'] ?? false))
        {{--
            LE RETARD PASSE DEVANT TOUT LE RESTE.

            Ce n'est pas le retard qui fait annuler, c'est le silence : dix minutes sans nouvelle
            valent une heure annoncee. Trois informations, dans cet ordre — de combien, ce que le
            prestataire repond, et ce qu'on peut faire.
        --}}
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 space-y-3" data-testid="retard-prestataire">
            <h3 class="font-semibold text-amber-900">{{ $retard['minutes'] }} min de retard</h3>

            <p class="text-sm text-amber-800">
                @if(data_get($retard, 'annonce.arrivee_at'))
                    Le prestataire annonce son arrivee vers
                    {{ \Illuminate\Support\Carbon::parse($retard['annonce']['arrivee_at'])->format('H:i') }}
                    @if(data_get($retard, 'annonce.motif')) — {{ $retard['annonce']['motif'] }} @endif
                @else
                    Le prestataire n’a pas encore repondu.
                @endif
            </p>

            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="decaler('plus_tard')"
                        class="rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm font-medium text-amber-900 hover:bg-amber-100"
                        data-testid="retard-decaler-plus-tard">
                    Plus tard aujourd’hui
                </button>
                <button type="button" wire:click="decaler('demain')"
                        class="rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm font-medium text-amber-900 hover:bg-amber-100"
                        data-testid="retard-decaler-demain">
                    Demain, meme heure
                </button>
            </div>

            @if($retard['annulation_gratuite'])
                {{-- La gratuite ne se promet que si le serveur la donne : un bouton « sans frais »
                     suivi de frais est pire qu'une absence de bouton. --}}
                <p class="text-xs text-amber-700" data-testid="retard-gratuit">
                    Vous pouvez annuler cette intervention sans frais.
                </p>
            @endif
        </div>
    @endif

    @if($revision && $revision->attendLeClient())
        {{--
            LE NOUVEAU DEVIS EN PREMIER : c'est la seule chose de cette page qui engage le PRIX,
            et elle attend une réponse pendant que le prestataire est devant la porte.

            Fond « brand » et non « danger » : une révision n'est pas un incident, c'est une
            décision à prendre. La signaler comme un problème ferait refuser par réflexe.
        --}}
        <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5 space-y-3" wire:key="revision-{{ $revision->id }}">
            <h3 class="font-semibold text-indigo-900">Nouveau devis proposé</h3>

            <div class="flex flex-wrap items-end gap-6">
                <div>
                    <p class="text-xs text-indigo-700">Devis d’origine</p>
                    <p class="text-lg text-indigo-900 line-through">
                        {{ number_format($revision->original_total_cents / 100, 2, ',', ' ') }} {{ $revision->currency }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-indigo-700">Nouveau devis</p>
                    <p class="text-3xl font-bold text-indigo-900">
                        {{ number_format($revision->revised_total_cents / 100, 2, ',', ' ') }} {{ $revision->currency }}
                    </p>
                </div>
            </div>

            @php($promo = data_get($revision->discount_breakdown, 'promo'))
            @if($promo && data_get($promo, 'code'))
                {{-- LA REMISE EST NOMMÉE : le client cherche d'abord ce qu'est devenu son code. --}}
                <p class="text-xs text-indigo-700">
                    Votre code {{ data_get($promo, 'code') }} reste appliqué —
                    {{ number_format(((int) data_get($promo, 'discount_cents', 0)) / 100, 2, ',', ' ') }} € de remise.
                </p>
            @endif

            <p class="text-sm text-indigo-900">{{ $revision->reason_text }}</p>

            <div class="flex flex-wrap gap-2 pt-1">
                <button type="button" wire:click="accepterLaRevision({{ $revision->id }})" class="brio-btn-primary">
                    Accepter
                </button>
                <button type="button" wire:click="refuserLaRevision({{ $revision->id }}, 'continue')"
                        class="rounded-lg border border-indigo-300 px-4 py-2 text-sm font-semibold text-indigo-800">
                    Refuser, continuez au prix d’origine
                </button>
                <button type="button" wire:click="refuserLaRevision({{ $revision->id }}, 'stop')"
                        wire:confirm="Arrêter l’intervention ? Le prestataire n’a pas commencé : vous ne payez rien."
                        class="rounded-lg border border-rose-300 px-4 py-2 text-sm font-semibold text-rose-700">
                    Refuser et arrêter
                </button>
            </div>
        </div>
    @endif

    @if($todo && $todo['engine'] !== null && $todo['engine'] !== 'vehicule')
        <div class="rounded-2xl border border-slate-200 bg-white p-5 space-y-3">
            <h3 class="font-semibold text-slate-900">Ma liste de tâches</h3>

            {{-- CE QUE LA LISTE ENGAGE, DIT AVANT LE CHAMP DE SAISIE. --}}
            <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                Le prestataire ne pourra pas terminer tant que ces tâches ne sont pas faites.
                @if($todo['window']['open'] && $todo['window']['minutes_left'] !== null)
                    Vous pouvez modifier cette liste pendant encore {{ $todo['window']['minutes_left'] }} min.
                @endif
            </p>

            <ul class="divide-y divide-slate-100">
                @forelse($todo['items'] as $item)
                    <li class="flex items-center justify-between gap-3 py-2" wire:key="tache-{{ $item['id'] }}">
                        <span class="text-sm {{ $item['done'] ? 'text-slate-400 line-through' : 'text-slate-800' }}">
                            {{ $item['label'] }}
                        </span>
                        @if($item['removable'] && $todo['window']['open'])
                            <button type="button" wire:click="retirerUneTache({{ $item['id'] }})"
                                    class="text-xs font-semibold text-slate-500 hover:text-rose-600"
                                    aria-label="Retirer « {{ $item['label'] }} »">
                                Retirer
                            </button>
                        @endif
                    </li>
                @empty
                    <li class="py-2 text-sm text-slate-500">
                        Rien pour l’instant. Sans liste, le prestataire termine dès qu’il a fini.
                    </li>
                @endforelse
            </ul>

            @if($todo['window']['open'])
                <form wire:submit="ajouterUneTache" class="flex flex-wrap items-end gap-2">
                    <label class="flex-1 min-w-[12rem]">
                        <span class="text-xs font-medium text-slate-600">Ajouter une tâche</span>
                        <input type="text" wire:model="nouvelleTache" placeholder="Nettoyer la hotte"
                               class="mt-1 w-full rounded-lg border-slate-300 text-sm">
                    </label>
                    <button type="submit" class="brio-btn-primary">Ajouter</button>
                </form>

                @if(! empty($todo['suggestions']))
                    <div class="flex flex-wrap gap-2 pt-1">
                        <span class="text-xs text-slate-500">Souvent demandé :</span>
                        @foreach($todo['suggestions'] as $suggestion)
                            <button type="button" wire:click="$set('nouvelleTache', '{{ addslashes($suggestion) }}')"
                                    class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700">
                                + {{ $suggestion }}
                            </button>
                        @endforeach
                    </div>
                @endif
            @else
                {{-- LE MOTIF SE MONTRE : une liste qui cesse d'accepter sans rien dire passe pour une panne. --}}
                <p class="text-sm italic text-slate-500">{{ $todo['window']['reason'] }}</p>
            @endif
        </div>
    @endif

    {{--
        ANNULER — en bas, et c'est voulu.

        C'est le geste dont on ne veut pas qu'il soit le premier vu. Il reste atteignable sans
        chercher, mais après ce qui peut encore sauver l'intervention : le devis à accepter, la
        liste à corriger.
    --}}
    {{--
        LA CONSIGNE DE DERNIÈRE MINUTE — sans fenêtre, contrairement à la liste.

        Un digicode qui change à 17 h doit pouvoir se dire à 17 h, même si la liste est figée depuis
        longtemps : c'est le prestataire qu'elle dépanne, pas le client qu'elle avantage.
    --}}
    @if($mission)
    <form wire:submit="enregistrerLaConsigne"
          class="rounded-2xl border border-slate-200 bg-white p-5 space-y-3">
        <h3 class="font-semibold text-slate-900">Consigne d’accès de dernière minute</h3>
        <p class="text-xs text-slate-500">
            Elle s’ajoute à ce que le prestataire sait déjà, sans remplacer les consignes de votre
            carnet de lieux.
        </p>
        <textarea wire:model="consigne" rows="2" placeholder="Le digicode est 4589."
                  class="w-full rounded-xl border-slate-300 text-sm"></textarea>
        @error('consigne') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
        <button type="submit" class="brio-btn-primary">Envoyer au prestataire</button>
    </form>
    @endif

    @if($mission)
    <div class="flex flex-wrap items-center gap-3 pt-2">
        @if($ligne)
            {{-- APPELER PAR LE NUMÉRO RELAIS : celui de la plateforme, jamais celui de l'autre. --}}
            <a href="tel:{{ $ligne['numero'] }}"
               class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm
                      font-semibold text-slate-700 hover:bg-slate-50">
                Appeler
            </a>
        @endif

        <livewire:shared.annuler-la-mission :booking="$mission->booking" role="client"
                                            :key="'annuler-client-'.$mission->id" />
    </div>
    @endif
</div>
