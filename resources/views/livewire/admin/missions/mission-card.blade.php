<div class="bg-white border rounded-2xl shadow-sm p-4">
    <div class="flex flex-col md:flex-row md:justify-between gap-3">
        <div class="min-w-0 break-words">
            <p class="font-semibold text-slate-900 text-lg">
                {{ $rdv->service_display_name }}
            </p>
            <p class="text-sm text-gray-600">
                📅 {{ $rdv->date }} à {{ $rdv->heure }}
            </p>
            <p class="text-sm text-gray-600">
                👤 {{ $rdv->client->name ?? '—' }} • 🧑‍💼 {{ $rdv->employe->name ?? '—' }}
            </p>
            <p class="text-sm text-gray-600">
                📍 {{ $rdv->adresse ?? '—' }}, {{ $rdv->ville ?? '—' }}
            </p>
        </div>

        <div class="flex items-start gap-2">
            <x-badge :status="$rdv->status" />
            <x-priority-badge :priority="$rdv->priorite" />
        </div>

        <div class="flex flex-col gap-2 sm:flex-row md:flex-col lg:flex-row">
            <button
                type="button"
                wire:click="dispatchRendezVous({{ $rdv->id }})"
                class="rounded-xl bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
            >
                ⚡ Dispatch auto
            </button>

            <button
                type="button"
                wire:click="previewDispatch({{ $rdv->id }})"
                class="rounded-xl border px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
            >
                👀 Voir scoring
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
                    class="rounded-xl border px-3 py-2 text-center text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    📂 Ouvrir la mission
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
                    class="rounded-xl border px-3 py-2 text-center text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    🔁 Gérer la série
                </a>
            @endif
        </div>
    </div>
</div>
