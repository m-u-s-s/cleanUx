<div class="space-y-6">
    <x-page-shell
        title="Applications mobiles"
        subtitle="Les liens de téléchargement affichés en bas de la page d'accueil, et les accroches qui les accompagnent."
        eyebrow="Plateforme"
    >
        <x-slot:actions>
            <a href="{{ url('/') }}#telecharger" target="_blank" rel="noopener" class="brio-btn-secondary">
                Voir la page publique
            </a>
        </x-slot:actions>
    </x-page-shell>

    @php $apercu = $this->apercu(); @endphp

    @if(! $apercu['client']['visible'] && ! $apercu['prestataire']['visible'])
        {{-- Deux cartes eteintes, c'est un bloc entierement absent de la home : on le dit ici. --}}
        <div role="alert" class="brio-alerte brio-alerte-warning">
            Aucune carte n'est publiée : le bloc de téléchargement n'apparaît pas du tout sur la page d'accueil.
        </div>
    @endif

    <form wire:submit="enregistrer" class="space-y-6">

        @foreach(\App\Support\Apps\LiensApplications::APPLICATIONS as $application)
            @php
                $estClient = $application === \App\Support\Apps\LiensApplications::CLIENT;
                $titre = $estClient ? 'Application client — Brio' : 'Application prestataire — Brio Provider';
                $propVisible = $estClient ? 'clientVisible' : 'prestataireVisible';
            @endphp

            <x-app-card :title="$titre"
                        subtitle="Un champ laissé vide masque son bouton : aucun lien mort n'est jamais affiché au public.">
                <div class="space-y-5">

                    <label class="flex items-start gap-3">
                        <input type="checkbox" wire:model.live="{{ $propVisible }}"
                               class="mt-1 rounded border-gray-300 text-blue-600 shadow-sm">
                        <span>
                            <span class="brio-field-label">Publier cette carte</span>
                            <span class="brio-section-subtitle block">
                                Décoché, la carte disparaît de la page d'accueil même si les liens sont remplis.
                            </span>
                        </span>
                    </label>

                    @foreach(\App\Livewire\Admin\ApplicationsMobiles::DESTINATIONS as $destination => $definition)
                        @php $cle = \App\Support\Apps\LiensApplications::cleLien($application, $destination); @endphp
                        <div>
                            <label for="{{ $cle }}" class="brio-field-label">{{ $definition['libelle'] }}</label>

                            <input id="{{ $cle }}" type="url" inputmode="url" class="w-full"
                                   placeholder="https://…"
                                   wire:model.blur="liens.{{ $cle }}">

                            <p class="brio-section-subtitle mt-1">{{ $definition['aide'] }}</p>

                            @error('liens.'.$cle)
                                <p class="mt-1 text-xs font-semibold" style="color: var(--brio-danger);">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach

                    {{-- Ce que le public verra vraiment, calcule par le meme code que le gabarit. --}}
                    <div class="rounded-2xl border p-4"
                         style="border-color: var(--brio-border); background: var(--brio-card);">
                        <p class="brio-field-label">Ce que le visiteur verra</p>

                        @if($apercu[$application]['visible'])
                            <ul class="mt-2 flex flex-wrap gap-2">
                                @foreach($apercu[$application]['liens'] as $destination => $lien)
                                    <li class="rounded-full px-3 py-1 text-xs font-semibold"
                                        style="background: rgb(var(--brio-accent-rgb) / .16); color: var(--brio-accent-texte);">
                                        {{ \App\Livewire\Admin\ApplicationsMobiles::DESTINATIONS[$destination]['libelle'] }}
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="brio-section-subtitle mt-1">
                                Rien : la carte est éteinte, ou aucun lien <code>https://</code> n'est enregistré.
                            </p>
                        @endif
                    </div>
                </div>
            </x-app-card>
        @endforeach

        <x-app-card title="Le QR code"
                    subtitle="Sur ordinateur, scanner est le seul geste possible. Le QR est calculé depuis le lien unique — rien à téléverser.">
            <label class="flex items-start gap-3">
                <input type="checkbox" wire:model.live="qrActif"
                       class="mt-1 rounded border-gray-300 text-blue-600 shadow-sm">
                <span>
                    <span class="brio-field-label">Afficher le QR sur écran large</span>
                    <span class="brio-section-subtitle block">
                        Il utilise le lien unique s'il existe, sinon le premier magasin renseigné.
                    </span>
                </span>
            </label>
        </x-app-card>

        <x-app-card title="Les accroches"
                    subtitle="Elles sont enregistrées au même endroit que le centre de traductions : les corriger ici ou là revient au même.">
            <div class="space-y-5">
                <div>
                    <label for="locale-textes" class="brio-field-label">Langue</label>
                    <select id="locale-textes" class="w-full" wire:model.live="locale">
                        @foreach($this->localesEditables() as $code => $definition)
                            <option value="{{ $code }}">{{ $definition['flag'] ?? '' }} {{ $definition['native_name'] ?? $code }}</option>
                        @endforeach
                    </select>
                    @error('locale')
                        <p class="mt-1 text-xs font-semibold" style="color: var(--brio-danger);">{{ $message }}</p>
                    @enderror
                </div>

                @foreach(\App\Livewire\Admin\ApplicationsMobiles::TEXTES as $plate => $champ)
                    <div>
                        <label for="texte-{{ $plate }}" class="brio-field-label">{{ $champ['libelle'] }}</label>

                        @if(str_ends_with($plate, 'accroche'))
                            <textarea id="texte-{{ $plate }}" rows="2" class="w-full"
                                      wire:model.blur="textes.{{ $plate }}"></textarea>
                        @else
                            <input id="texte-{{ $plate }}" type="text" class="w-full"
                                   wire:model.blur="textes.{{ $plate }}">
                        @endif

                        @error('textes.'.$plate)
                            <p class="mt-1 text-xs font-semibold" style="color: var(--brio-danger);">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach
            </div>
        </x-app-card>

        <div class="flex items-center gap-3">
            <button type="submit" class="brio-btn-primary">Enregistrer</button>
            <span class="brio-section-subtitle" wire:loading wire:target="enregistrer">Enregistrement…</span>
        </div>
    </form>
</div>
