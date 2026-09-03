{{--
    RÔLES ET PERMISSIONS.

    Trois onglets pour trois questions qui se posent vraiment : quels rôles existent, que peut
    cette personne, et — la lecture inverse, qui n'existait nulle part — qui peut toucher à quoi.

    Une capacité qu'on ne détient pas soi-même s'affiche GRISÉE plutôt que cachée : savoir qu'elle
    existe et qu'elle n'est pas à sa portée vaut mieux que croire qu'elle n'existe pas.
--}}
<div class="space-y-6">

    <x-page-shell
        :eyebrow="__('Comptes et sécurité')"
        :title="__('Rôles et permissions')"
        :subtitle="__('Ce que chaque administrateur peut ouvrir, et par quelle capacité.')" />

    @if($message)
        <div class="brio-alerte brio-alerte-success !mb-0"><span aria-hidden="true">✅</span><span>{{ $message }}</span></div>
    @endif

    @if($erreur)
        <div class="brio-alerte brio-alerte-danger !mb-0"><span aria-hidden="true">⚠️</span><span>{{ $erreur }}</span></div>
    @endif

    <x-app-card>
        <div class="mb-4 flex flex-wrap gap-2">
            @foreach ([
                'roles' => __('Rôles') . ' (' . $this->roles->count() . ')',
                'personnes' => __('Administrateurs') . ' (' . $this->administrateurs->count() . ')',
                'capacites' => __('Par capacité'),
            ] as $cle => $libelle)
                <button type="button" wire:click="$set('onglet', '{{ $cle }}')"
                        class="{{ $onglet === $cle ? 'brio-btn-primary' : 'brio-btn-secondary' }} !px-3 !py-1.5 !text-xs">
                    {{ $libelle }}
                </button>
            @endforeach
        </div>

        {{-- ── LES RÔLES ───────────────────────────────────────────────── --}}
        @if($onglet === 'roles')
            <div class="grid gap-4 lg:grid-cols-3">
                <div class="space-y-2 lg:col-span-2">
                    @forelse($this->roles as $role)
                        <div class="brio-list-item !p-3" wire:key="role-{{ $role->id }}">
                            <div class="min-w-0">
                                <p class="font-semibold">{{ $role->name }}</p>
                                <p class="text-xs opacity-70">
                                    {{ trans_choice('{0}Aucune capacité|{1}:count capacité|[2,*]:count capacités', count($role->capacites()), ['count' => count($role->capacites())]) }}
                                    · {{ trans_choice('{0}Personne|{1}:count administrateur|[2,*]:count administrateurs', $role->utilisateurs_count, ['count' => $role->utilisateurs_count]) }}
                                    @if($role->access_scope) · {{ __('périmètre') }} : {{ $role->access_scope }} @endif
                                </p>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <button type="button" wire:click="editerLeRole({{ $role->id }})"
                                        class="brio-btn-ligne !text-xs">{{ __('Modifier') }}</button>
                                <button type="button" wire:click="supprimerLeRole({{ $role->id }})"
                                        wire:confirm="{{ __('Supprimer ce rôle ? Les comptes concernés gardent leurs capacités individuelles et perdent celles du rôle.') }}"
                                        class="brio-btn-ligne-danger !text-xs">{{ __('Supprimer') }}</button>
                            </div>
                        </div>
                    @empty
                        <x-empty-state icon="🗝️" :title="__('Aucun rôle pour l’instant')"
                                       :message="__('Un rôle nomme un paquet de capacités : « Comptable », « Support », « Modération ». Vingt et une cases se cochent mal une par une, et se recopient encore plus mal.')" />
                    @endforelse
                </div>

                <div class="space-y-3">
                    <h3 class="text-sm font-bold">
                        {{ $roleEnEdition ? __('Modifier le rôle') : __('Nouveau rôle') }}
                    </h3>

                    <div>
                        <label for="nom-role" class="mb-1 block text-sm font-semibold">{{ __('Nom') }}</label>
                        <input id="nom-role" wire:model="nomDuRole" type="text" class="w-full"
                               placeholder="{{ __('Comptable, Support, Modération…') }}">
                        @error('nomDuRole') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="perimetre-role" class="mb-1 block text-sm font-semibold">{{ __('Périmètre imposé') }}</label>
                        <select id="perimetre-role" wire:model="perimetreDuRole" class="w-full">
                            <option value="">{{ __('Ne l’impose pas') }}</option>
                            <option value="all">{{ __('Toute la plateforme') }}</option>
                            <option value="zone">{{ __('Une seule zone') }}</option>
                            <option value="readonly">{{ __('Lecture seule') }}</option>
                        </select>
                    </div>

                    <fieldset>
                        <legend class="mb-1 text-sm font-semibold">{{ __('Capacités') }}</legend>
                        <div class="max-h-72 space-y-1 overflow-y-auto pr-1">
                            @foreach($this->capacites as $cle => $libelle)
                                @php($accordable = in_array($cle, $this->capacitesAccordables, true))
                                <label class="flex items-start gap-2 text-sm {{ $accordable ? '' : 'opacity-50' }}"
                                       wire:key="cap-role-{{ $cle }}">
                                    <input type="checkbox" wire:model="capacitesDuRole" value="{{ $cle }}"
                                           class="mt-0.5" @disabled(! $accordable)>
                                    <span>
                                        {{ $libelle }}
                                        @unless($accordable)
                                            <span class="block text-xs">{{ __('vous ne la détenez pas') }}</span>
                                        @endunless
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="flex gap-2">
                        <button type="button" wire:click="enregistrerLeRole" class="brio-btn-primary flex-1">
                            {{ __('Enregistrer') }}
                        </button>
                        @if($roleEnEdition)
                            <button type="button" wire:click="nouveauRole" class="brio-btn-ligne">{{ __('Annuler') }}</button>
                        @endif
                    </div>
                </div>
            </div>

        {{-- ── LES ADMINISTRATEURS ─────────────────────────────────────── --}}
        @elseif($onglet === 'personnes')
            <div class="space-y-2">
                @foreach($this->administrateurs as $admin)
                    <div class="brio-list-item !p-3" wire:key="admin-{{ $admin->id }}">
                        <div class="min-w-0">
                            <p class="font-semibold">
                                {{ $admin->name }}
                                @if($admin->isSuperAdmin())
                                    <x-ui.badge tone="warning" :label="__('Siège')" />
                                @endif
                            </p>
                            <p class="text-xs opacity-70">
                                {{ collect([
                                    $admin->email,
                                    $admin->adminRole?->name ?? __('sans rôle'),
                                    $admin->isSuperAdmin()
                                        ? __('toutes les capacités')
                                        : trans_choice('{0}aucune capacité|{1}:count capacité|[2,*]:count capacités', count($admin->permissionList()), ['count' => count($admin->permissionList())]),
                                    __('périmètre') . ' : ' . ($admin->access_scope ?: 'all'),
                                ])->filter()->join(' · ') }}
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            @if($admin->isSuperAdmin())
                                <span class="text-xs opacity-70">{{ __('se règle depuis Le siège') }}</span>
                            @elseif($admin->id === auth()->id())
                                <span class="text-xs opacity-70">{{ __('c’est vous') }}</span>
                            @else
                                <button type="button" wire:click="editerLAdministrateur({{ $admin->id }})"
                                        class="brio-btn-ligne !text-xs">{{ __('Régler') }}</button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if($adminEnEdition)
                @php($cible = $this->administrateurs->firstWhere('id', $adminEnEdition))
                <div class="mt-4 border-t border-slate-200/60 pt-4 dark:border-slate-700">
                    <h3 class="text-sm font-bold">{{ __('Régler :nom', ['nom' => $cible?->name]) }}</h3>

                    <div class="mt-3 grid gap-4 md:grid-cols-2">
                        <div class="space-y-3">
                            <div>
                                <label for="role-assigne" class="mb-1 block text-sm font-semibold">{{ __('Rôle') }}</label>
                                <select id="role-assigne" wire:model="roleAssigne" class="w-full">
                                    <option value="">{{ __('Sans rôle') }}</option>
                                    @foreach($this->roles as $role)
                                        <option value="{{ $role->id }}" wire:key="opt-role-{{ $role->id }}">{{ $role->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="perimetre" class="mb-1 block text-sm font-semibold">{{ __('Périmètre') }}</label>
                                <select id="perimetre" wire:model.live="perimetre" class="w-full">
                                    <option value="all">{{ __('Toute la plateforme') }}</option>
                                    <option value="zone">{{ __('Une seule zone') }}</option>
                                    <option value="readonly">{{ __('Lecture seule') }}</option>
                                </select>
                                @error('perimetre') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            @if($perimetre === 'zone')
                                <div>
                                    <label for="zone" class="mb-1 block text-sm font-semibold">{{ __('Zone gérée') }}</label>
                                    <select id="zone" wire:model="zoneGeree" class="w-full">
                                        <option value="">{{ __('Choisir une zone') }}</option>
                                        @foreach($this->zones as $zone)
                                            <option value="{{ $zone->id }}" wire:key="zone-{{ $zone->id }}">{{ $zone->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        </div>

                        <fieldset>
                            <legend class="mb-1 text-sm font-semibold">{{ __('Capacités en plus du rôle') }}</legend>
                            <p class="mb-2 text-xs opacity-70">
                                {{ __('Celles du rôle ne se décochent pas ici : les retirer donnerait l’illusion de les enlever alors que le rôle les redonne.') }}
                            </p>
                            <div class="max-h-64 space-y-1 overflow-y-auto pr-1">
                                @foreach($this->capacites as $cle => $libelle)
                                    @php($accordable = in_array($cle, $this->capacitesAccordables, true))
                                    <label class="flex items-start gap-2 text-sm {{ $accordable ? '' : 'opacity-50' }}"
                                           wire:key="cap-admin-{{ $cle }}">
                                        <input type="checkbox" wire:model="capacitesEnPlus" value="{{ $cle }}"
                                               class="mt-0.5" @disabled(! $accordable)>
                                        <span>{{ $libelle }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    </div>

                    <div class="mt-3 flex gap-2">
                        <button type="button" wire:click="enregistrerLAdministrateur" class="brio-btn-primary">
                            {{ __('Enregistrer') }}
                        </button>
                        <button type="button" wire:click="annulerLEdition" class="brio-btn-ligne">{{ __('Annuler') }}</button>
                    </div>
                </div>
            @endif

        {{-- ── PAR CAPACITÉ : la lecture inverse ───────────────────────── --}}
        @else
            <p class="mb-3 text-sm opacity-70">
                {{ __('Pour chaque capacité : ce qu’elle ouvre, et qui la détient. C’est la seule vue qui répond à « qui peut toucher à l’argent ? ».') }}
            </p>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left">
                            <th class="py-2 pr-3 font-semibold">{{ __('Capacité') }}</th>
                            <th class="py-2 pr-3 font-semibold">{{ __('Écrans ouverts') }}</th>
                            <th class="py-2 font-semibold">{{ __('Qui la détient') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->parCapacite as $cle => $ligne)
                            <tr class="border-t border-slate-200/60 align-top dark:border-slate-700" wire:key="inv-{{ $cle }}">
                                <td class="py-2 pr-3">
                                    <span class="font-semibold">{{ $ligne['libelle'] }}</span>
                                    <span class="block text-xs opacity-70">{{ $cle }}</span>
                                </td>
                                <td class="py-2 pr-3 text-xs">
                                    {{ $ligne['ecrans'] === [] ? __('Aucun écran ne la déclare') : implode(', ', $ligne['ecrans']) }}
                                </td>
                                <td class="py-2 text-xs">
                                    @if($ligne['porteurs'] === [])
                                        <span class="opacity-70">{{ __('Personne') }}</span>
                                    @else
                                        {{ implode(', ', $ligne['porteurs']) }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-app-card>
</div>
