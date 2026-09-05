<div class="bg-white border rounded-2xl shadow-sm p-4">
    <div class="flex flex-col md:flex-row md:justify-between gap-3">
        <div class="min-w-0 break-words">
            <p class="font-semibold text-slate-900 text-lg">
                {{ $rdv->service_display_name }}
            </p>
            <p class="text-sm text-gray-600">
                {{-- `join()` PREND SON SEPARATEUR POUR UNE COLONNE quand le premier element est un
                     objet : `$rdv->date` est un Carbon, et la date sortait vide. D'ou le formatage. --}}
                <svg class="mr-1.5 inline h-3.5 w-3.5 shrink-0 -translate-y-px opacity-70" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>{{ collect([$rdv->date?->format('d/m/Y'), \Illuminate\Support\Str::of((string) $rdv->heure)->substr(0, 5)->trim()->value() ?: null])->filter()->join(' à ') ?: 'Date non renseignée' }}
            </p>
            <p class="text-sm text-gray-600">
                <svg class="mr-1.5 inline h-3.5 w-3.5 shrink-0 -translate-y-px opacity-70" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>{{ $rdv->client->name ?? '—' }} • {{ $rdv->employe->name ?? '—' }}
            </p>
            <p class="text-sm text-gray-600">
                <svg class="mr-1.5 inline h-3.5 w-3.5 shrink-0 -translate-y-px opacity-70" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg>{{ collect([$rdv->adresse, $rdv->ville])->filter()->join(', ') ?: 'Adresse non renseignée' }}
            </p>
        </div>

        <div class="flex items-start gap-2">
            <x-badge :status="$rdv->status" />
            <x-priority-badge :priority="$rdv->priorite" />
        </div>

        {{-- Une barre d’actions qui s’enroule : trois blocs empilés faisaient une carte
             deux fois trop haute. `brio-btn-ligne` borne leur hauteur à la cible tactile. --}}
        <div class="flex flex-wrap items-center gap-1">
            {{--
                LE DISPATCH AUTOMATIQUE NE S'AFFICHE QUE LA OU IL A UN SENS.

                Il etait rendu sur CHAQUE ligne, y compris sur les rendez-vous deja confirmes
                avec un intervenant nomme juste a cote — et sans confirmation. Un clic
                remplacait cet intervenant, sans question posee et sans retour possible.

                Il ne reste donc que sur les rendez-vous SANS intervenant, et il demande.
            --}}
            @if (! $rdv->employe_id)
                <button
                    type="button"
                    wire:click="dispatchRendezVous({{ $rdv->id }})"
                    wire:confirm="Affecter automatiquement un prestataire à « {{ $rdv->service_name ?? 'ce rendez-vous' }} » ? Le choix est fait par le moteur, et le rendez-vous passe en confirmé."
                    class="brio-btn-ligne gap-1.5"
                >
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" /></svg>
                    Dispatch auto
                </button>
            @endif

            <button
                type="button"
                wire:click="previewDispatch({{ $rdv->id }})"
                class="brio-btn-ligne gap-1.5"
            >
                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" /></svg>
                Voir scoring
            </button>

            {{--
                LE DÉTAIL DE LA MISSION N'AVAIT AUCUNE PORTE (ajouté le 2026-08-05).

                `admin.missions.show` existe, sa vue existe, et elle porte l'export PDF de la
                mission ainsi que les deux exports CSV qualité — mais rien n'y menait : cette liste
                ne proposait que deux actions Livewire, sans un seul lien. La page et ses trois
                exports n'étaient atteignables qu'en tapant l'URL à la main.

                Le lien n'apparaît que si le rendez-vous a bien une mission rattachée : tous n'en
                ont pas, et `route()` sur une relation nulle ferait tomber la liste entière.
            --}}
            @if ($rdv->mission && Route::has('admin.missions.show'))
                <a
                    href="{{ route('admin.missions.show', $rdv->mission) }}"
                    class="brio-btn-ligne brio-btn-ligne-accent gap-1.5 font-semibold"
                >
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                    Ouvrir la mission
                </a>
            @endif

            {{--
                GÉRER LA SÉRIE RÉCURRENTE (ajouté le 2026-08-05).

                Le client dispose de ce lien sur sa propre carte de rendez-vous
                (`client.rendezvous.series`) ; l'administration avait la page équivalente,
                `admin.recurrence.edit`, sans qu'aucun écran n'y mène.

                Affiché seulement quand la réservation appartient à une série — sinon la page
                n'aurait rien à éditer. `$rdv` est un Booking, ce qu'attend
                `EditRecurringBooking::mount(Booking $rendezVous)` malgré le nom du paramètre.
            --}}
            @if ($rdv->recurring_series_id && Route::has('admin.recurrence.edit'))
                <a
                    href="{{ route('admin.recurrence.edit', $rdv) }}"
                    class="brio-btn-ligne gap-1.5"
                >
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992V4.356M2.985 19.644v-4.992h4.992m-4.99 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182" /></svg>
                    Gérer la série
                </a>
            @endif
        </div>
    </div>
</div>
