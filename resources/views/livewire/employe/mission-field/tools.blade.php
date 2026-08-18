<section class="space-y-5">
    @if($erreur)
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">
            {{ $erreur }}
        </div>
    @endif

    @if($succes)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
            {{ $succes }}
        </div>
    @endif

    {{-- ── ACCÉDER AU LIEU ─────────────────────────────────────────────────
         Le refus est DIT, jamais une fiche vide : une fiche vide se lit comme une donnée manquante
         et fait appeler le support pour rien. --}}
    <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm space-y-2">
        <h3 class="font-black text-slate-900">Accéder au lieu</h3>

        @if($ficheDAcces['available'] ?? false)
            @if($ficheDAcces['floor'] ?? null)
                <p class="text-sm text-slate-600">Étage : {{ $ficheDAcces['floor'] }}</p>
            @endif
            @if($ficheDAcces['access_instructions'] ?? null)
                <p class="text-sm text-slate-600">{{ $ficheDAcces['access_instructions'] }}</p>
            @endif
            @if($ficheDAcces['access_window'] ?? null)
                <p class="text-sm text-slate-600">Accès {{ $ficheDAcces['access_window'] }}</p>
            @endif
            @if($ficheDAcces['alarm_code_required'] ?? false)
                {{-- L'alarme demande une manœuvre chronométrée : à lire AVANT d'ouvrir la porte. --}}
                <p class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                    Alarme à désarmer
                </p>
            @endif
        @else
            <p class="text-sm text-slate-500">{{ $ficheDAcces['message'] ?? 'Confirmez votre arrivée pour afficher les informations d’accès.' }}</p>
        @endif
    </div>

    {{-- ── SIGNALER UN IMPRÉVU ─────────────────────────────────────────── --}}
    <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm space-y-3">
        <h3 class="font-black text-slate-900">Signaler un imprévu</h3>
        <p class="text-sm text-slate-500">
            Le client est prévenu tout de suite, et le dossier de litige est pré-rempli s’il en ouvre un.
        </p>

        <form wire:submit="signalerUnImprevu" class="space-y-3">
            <div class="flex flex-wrap gap-2">
                @foreach($categories as $categorie)
                    <button type="button" wire:click="$set('incidentType', '{{ $categorie }}')"
                            class="rounded-full border px-3 py-1.5 text-xs font-semibold
                                   {{ $incidentType === $categorie
                                      ? 'border-blue-500 bg-blue-500 text-white'
                                      : 'border-slate-300 text-slate-700' }}">
                        {{ \App\Models\MissionIncident::libelleType($categorie) }}
                    </button>
                @endforeach
            </div>
            @error('incidentType') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

            <textarea wire:model="incidentDescription" rows="3"
                      placeholder="Trace d’humidité derrière le meuble, présente à mon arrivée."
                      class="w-full rounded-xl border-slate-300 text-sm"></textarea>
            @error('incidentDescription') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror

            <button type="submit" class="brio-btn-primary">Signaler</button>
        </form>
    </div>

    {{-- ── PROPOSER UN SUPPLÉMENT ──────────────────────────────────────────
         Rien à ajouter sur une course : son prix est fixé par le trajet. Le bloc n'est pas grisé,
         il n'est pas rendu — un formulaire visible et inerte se remplit quand même, et le refus
         arrive après la saisie. --}}
    @if($moteur !== 'vehicule')
    <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm space-y-3">
        <h3 class="font-black text-slate-900">Proposer un supplément</h3>
        <p class="text-sm text-slate-500">
            Le client répond depuis son téléphone. Son devis d’origine ne bouge pas : le supplément
            est une ligne à part, plafonnée à 500 €.
        </p>

        <form wire:submit="proposerUnSupplement" class="flex flex-wrap items-end gap-3">
            <label class="flex-1 min-w-[12rem]">
                <span class="text-xs font-medium text-slate-600">Ce que vous proposez</span>
                <input type="text" wire:model="extraLabel" placeholder="Nettoyage des vitres"
                       class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                @error('extraLabel') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="w-32">
                <span class="text-xs font-medium text-slate-600">Prix (€)</span>
                <input type="number" step="0.01" wire:model="extraPrix" placeholder="25"
                       class="mt-1 w-full rounded-xl border-slate-300 text-sm">
                @error('extraPrix') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>

            <button type="submit" class="brio-btn-primary">Proposer</button>
        </form>

        @if($extras->isNotEmpty())
            <ul class="divide-y divide-slate-100 pt-2">
                @foreach($extras as $extra)
                    <li class="flex items-center justify-between gap-3 py-2 text-sm" wire:key="extra-{{ $extra->id }}">
                        <span class="text-slate-800">
                            {{ $extra->label }} · {{ number_format($extra->price_cents / 100, 2, ',', ' ') }} {{ $extra->currency }}
                        </span>
                        {{-- Trois états lisibles d'un coup d'œil : le prestataire cherche s'il peut
                             commencer, pas un historique de statuts. --}}
                        <span class="text-xs italic text-slate-500">
                            @if($extra->status === 'proposed') En attente de réponse
                            @elseif($extra->status === 'declined') Refusé par le client
                            @else Accepté — vous pouvez le faire
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
    @endif

    {{-- ── LE COMPTEUR, MOTEUR HORAIRE SEULEMENT ───────────────────────────
         Il se rend nul de lui-même hors mission vendue au temps : la vue n'a pas à savoir ce qui
         décide, et une seconde règle ici finirait par contredire la première. --}}
    @if($horloge['applies'] ?? false)
    <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm space-y-2">
        <h3 class="font-black text-slate-900">Temps acheté</h3>

        <p class="text-3xl font-black {{ ($horloge['remaining_minutes'] ?? 0) < 0 ? 'text-rose-600' : 'text-slate-900' }}">
            @if(($horloge['remaining_minutes'] ?? 0) >= 0)
                {{ $horloge['remaining_minutes'] }} min restantes
            @else
                {{ abs($horloge['remaining_minutes']) }} min de dépassement
            @endif
        </p>

        <p class="text-sm text-slate-500">
            {{ $horloge['purchased_minutes'] }} min achetées ·
            échéance {{ \Illuminate\Support\Carbon::parse($horloge['deadline_at'])->format('H:i') }}
            @if(($horloge['billable_overtime_minutes'] ?? 0) > 0)
                · {{ number_format(($horloge['overtime_amount_cents'] ?? 0) / 100, 2, ',', ' ') }} € de dépassement facturable
            @endif
        </p>

        {{-- LA RÈGLE VIENT DU SERVEUR : une rédaction locale divergerait des conditions générales
             dès la première modification. --}}
        <p class="text-xs text-slate-500">{{ $horloge['rule']['provider'] ?? '' }}</p>
    </div>
    @endif

    {{-- ── NOUVEAU DEVIS, MOTEUR À DOMICILE SEULEMENT ──────────────────────
         AVANT le supplément dans le temps : la révision se décide en arrivant, le supplément se
         découvre en travaillant. --}}
    @if($fenetreRevision !== null)
    <div class="rounded-[2rem] border border-indigo-200 bg-indigo-50 p-5 shadow-sm space-y-3">
        <h3 class="font-black text-indigo-900">Nouveau devis</h3>

        @if($revision && $revision->attendLeClient())
            <p class="text-2xl font-black text-indigo-900">
                {{ number_format($revision->revised_total_cents / 100, 2, ',', ' ') }} {{ $revision->currency }}
            </p>
            <p class="text-sm text-indigo-800">
                Envoyé. Le client répond depuis son téléphone — son devis d’origine était de
                {{ number_format($revision->original_total_cents / 100, 2, ',', ' ') }} {{ $revision->currency }}.
            </p>
            <button type="button" wire:click="retirerLeNouveauDevis({{ $revision->id }})"
                    class="rounded-lg border border-indigo-300 px-4 py-2 text-sm font-semibold text-indigo-800">
                Retirer ma proposition
            </button>
        @elseif($fenetreRevision['open'])
            <p class="text-sm text-indigo-800">
                À faire maintenant, avant de commencer. Un imprévu découvert en travaillant se
                propose en supplément. Une photo « avant » est obligatoire.
            </p>

            <form wire:submit="proposerUnNouveauDevis" class="flex flex-wrap items-end gap-3">
                <label class="w-40">
                    <span class="text-xs font-medium text-indigo-800">Prix du service (€)</span>
                    <input type="number" step="0.01" wire:model="revisionPrix" placeholder="300"
                           class="mt-1 w-full rounded-xl border-indigo-300 text-sm">
                    @error('revisionPrix') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>

                <label class="flex-1 min-w-[14rem]">
                    <span class="text-xs font-medium text-indigo-800">Ce que vous constatez</span>
                    <input type="text" wire:model="revisionMotif"
                           placeholder="Deux cents mètres carrés annoncés vingt."
                           class="mt-1 w-full rounded-xl border-indigo-300 text-sm">
                    @error('revisionMotif') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                </label>

                <button type="submit" class="brio-btn-primary">Envoyer au client</button>
            </form>
        @else
            {{-- LE MOTIF, PAS UN FORMULAIRE GRISÉ : il dit quel geste employer à la place. --}}
            <p class="text-sm italic text-indigo-800">{{ $fenetreRevision['reason'] }}</p>
        @endif
    </div>
    @endif
</section>
