{{-- L ECRAN N AFFICHAIT QU UN APERCU de six gabarits codes en dur. Il les EDITE desormais :
     blocs, variables, theme d apercu, sans qu une balise soit ecrite a la main. --}}
<div class="space-y-6">
    <x-page-shell
        eyebrow="Communication"
        title="Emails produit"
        subtitle="Composez vos e-mails en blocs, habillez-les d’un thème, et voyez le résultat avant qu’il ne parte.">
        <x-slot:actions>
            <span class="brio-inline-stat">{{ $this->reperes['actifs'] }}/{{ $this->reperes['gabarits'] }} actifs</span>
            <span class="brio-inline-stat">{{ $this->reperes['saisons'] }} saison(s)</span>
            <button type="button" wire:click="nouveauGabarit" class="brio-btn-primary">Nouveau gabarit</button>
        </x-slot:actions>
    </x-page-shell>

    {{-- DEUX VOLETS D UN MEME STUDIO : le contenu d un cote, l habillage de l autre. --}}
    <div class="flex gap-2" role="tablist" aria-label="Volets du studio">
        <button type="button" role="tab" wire:click="$set('onglet', 'gabarits')"
                aria-selected="{{ $onglet === 'gabarits' ? 'true' : 'false' }}"
                @class(['brio-btn-ligne', 'brio-btn-primary' => $onglet === 'gabarits'])>
            Gabarits
        </button>
        <button type="button" role="tab" wire:click="$set('onglet', 'themes')"
                aria-selected="{{ $onglet === 'themes' ? 'true' : 'false' }}"
                @class(['brio-btn-ligne', 'brio-btn-primary' => $onglet === 'themes'])>
            Thèmes &amp; saisons
        </button>
        <button type="button" role="tab" wire:click="$set('onglet', 'mesure')"
                aria-selected="{{ $onglet === 'mesure' ? 'true' : 'false' }}"
                @class(['brio-btn-ligne', 'brio-btn-primary' => $onglet === 'mesure'])>
            Mesure
        </button>
    </div>

    @if($onglet === 'themes')
        <livewire:admin.email-themes-studio />
    @elseif($onglet === 'mesure')
        <livewire:admin.email-mesure-studio />
    @else
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">

        {{-- ── La bibliothèque ─────────────────────────────────────────── --}}
        <div class="xl:col-span-3">
            <x-app-card title="Bibliothèque" subtitle="Vos gabarits, par catégorie.">
                <label class="sr-only" for="filtre-categorie">Catégorie</label>
                <select id="filtre-categorie" wire:model.live="filtreCategorie" class="mb-3 w-full">
                    <option value="">Toutes les catégories</option>
                    <option value="transactionnel">Transactionnel</option>
                    <option value="rappel">Rappel</option>
                    <option value="marketing">Marketing</option>
                    <option value="fraude">Fraude &amp; sécurité</option>
                    <option value="interne">Interne</option>
                </select>

                <div class="space-y-1">
                    @forelse($this->gabarits as $gabarit)
                        <button type="button"
                                wire:key="gab-{{ $gabarit->id }}"
                                wire:click="$set('templateKey', '{{ $gabarit->code }}')"
                                @class([
                                    'w-full rounded-xl border px-3 py-2 text-left text-sm transition',
                                    'border-transparent hover:opacity-80' => $gabarit->code !== $templateKey,
                                    'border-current font-semibold' => $gabarit->code === $templateKey,
                                ])>
                            <span class="block">{{ $gabarit->name }}</span>
                            <span class="block text-xs opacity-70">
                                {{ $gabarit->category }}
                                @unless($gabarit->is_active) · inactif @endunless
                            </span>
                        </button>
                    @empty
                        <p class="text-sm opacity-70">Aucun gabarit dans cette catégorie.</p>
                    @endforelse
                </div>
            </x-app-card>
        </div>

        {{-- ── L’éditeur ───────────────────────────────────────────────── --}}
        <div class="xl:col-span-5">
            @if($this->gabaritCourant)
                <x-app-card title="Contenu" subtitle="Les blocs se composent, se déplacent et se suppriment. Aucune balise à écrire.">
                    <form wire:submit="enregistrer" class="space-y-4">
                        <div class="grid gap-3 md:grid-cols-2">
                            <div>
                                <label for="gab-nom" class="mb-1 block text-sm font-semibold">Nom</label>
                                <input id="gab-nom" wire:model="nom" type="text" class="w-full">
                                @error('nom') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="gab-categorie" class="mb-1 block text-sm font-semibold">Catégorie</label>
                                <select id="gab-categorie" wire:model="categorie" class="w-full">
                                    <option value="transactionnel">Transactionnel</option>
                                    <option value="rappel">Rappel</option>
                                    <option value="marketing">Marketing</option>
                                    <option value="fraude">Fraude &amp; sécurité</option>
                                    <option value="interne">Interne</option>
                                </select>
                                @error('categorie') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="md:col-span-2">
                                <label for="gab-objet" class="mb-1 block text-sm font-semibold">Objet</label>
                                <input id="gab-objet" wire:model.live.debounce.400ms="objet" type="text" class="w-full">
                                @error('objet') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="md:col-span-2">
                                <label for="gab-preheader" class="mb-1 block text-sm font-semibold">
                                    Ligne d’aperçu
                                    <span class="font-normal opacity-70">— le gris que Gmail affiche sous l’objet</span>
                                </label>
                                <input id="gab-preheader" wire:model.live.debounce.400ms="preheader" type="text" class="w-full">
                            </div>

                            <div class="md:col-span-2">
                                <label for="gab-variables" class="mb-1 block text-sm font-semibold">
                                    Variables autorisées
                                    <span class="font-normal opacity-70">— séparées par des virgules</span>
                                </label>
                                <input id="gab-variables" wire:model.live.debounce.500ms="variables" type="text"
                                       class="w-full" placeholder="client_name, service, date">
                            </div>

                            <div>
                                <label for="gab-theme" class="mb-1 block text-sm font-semibold">Thème imposé</label>
                                <select id="gab-theme" wire:model="themeImpose" class="w-full">
                                    <option value="">Suivre la saison</option>
                                    @foreach($this->themes as $theme)
                                        <option value="{{ $theme->id }}">{{ $theme->name }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs opacity-70">
                                    Une facture ne se déguise pas : imposez son thème pour qu’elle ignore les saisons.
                                </p>
                            </div>

                            <div class="flex items-end">
                                <label class="flex items-center gap-2 text-sm">
                                    <input wire:model="actif" type="checkbox"> Gabarit actif
                                </label>
                            </div>
                        </div>

                        {{-- ── Les blocs ──────────────────────────────────── --}}
                        <div class="space-y-2">
                            @foreach($blocs as $i => $bloc)
                                <div class="rounded-2xl border border-slate-200/80 p-3 dark:border-slate-700"
                                     wire:key="bloc-{{ $i }}">
                                    <div class="mb-2 flex items-center justify-between gap-2">
                                        <span class="brio-chip">{{ $bloc['type'] }}</span>

                                        <div class="flex gap-1">
                                            <button type="button" wire:click="deplacerBloc({{ $i }}, -1)"
                                                    class="brio-btn-ligne" aria-label="Monter ce bloc">↑</button>
                                            <button type="button" wire:click="deplacerBloc({{ $i }}, 1)"
                                                    class="brio-btn-ligne" aria-label="Descendre ce bloc">↓</button>
                                            <button type="button" wire:click="retirerBloc({{ $i }})"
                                                    class="brio-btn-ligne-danger" aria-label="Retirer ce bloc">✕</button>
                                        </div>
                                    </div>

                                    @if(in_array($bloc['type'], ['heading', 'paragraph', 'highlight'], true))
                                        <label class="sr-only" for="bloc-texte-{{ $i }}">Texte du bloc {{ $i + 1 }}</label>
                                        <textarea id="bloc-texte-{{ $i }}" rows="2" class="w-full"
                                                  wire:model.live.debounce.500ms="blocs.{{ $i }}.text"></textarea>

                                    @elseif($bloc['type'] === 'button')
                                        <div class="grid gap-2 md:grid-cols-2">
                                            <input aria-label="Libellé du bouton" class="w-full" type="text"
                                                   wire:model.live.debounce.500ms="blocs.{{ $i }}.text">
                                            <input aria-label="Adresse du bouton" class="w-full" type="url"
                                                   wire:model.live.debounce.500ms="blocs.{{ $i }}.url">
                                        </div>

                                    @elseif($bloc['type'] === 'image')
                                        <div class="grid gap-2 md:grid-cols-2">
                                            <input aria-label="Adresse de l’image" class="w-full" type="url"
                                                   wire:model.live.debounce.500ms="blocs.{{ $i }}.url">
                                            <input aria-label="Texte de remplacement" class="w-full" type="text"
                                                   placeholder="Texte alternatif"
                                                   wire:model.live.debounce.500ms="blocs.{{ $i }}.alt">
                                        </div>

                                    @elseif($bloc['type'] === 'details')
                                        <div class="space-y-2">
                                            @foreach($bloc['rows'] ?? [] as $l => $ligne)
                                                <div class="flex gap-2" wire:key="bloc-{{ $i }}-ligne-{{ $l }}">
                                                    <input aria-label="Libellé" class="w-1/3" type="text"
                                                           wire:model.live.debounce.500ms="blocs.{{ $i }}.rows.{{ $l }}.label">
                                                    <input aria-label="Valeur" class="flex-1" type="text"
                                                           wire:model.live.debounce.500ms="blocs.{{ $i }}.rows.{{ $l }}.value">
                                                    <button type="button" wire:click="retirerLigne({{ $i }}, {{ $l }})"
                                                            class="brio-btn-ligne" aria-label="Retirer cette ligne">✕</button>
                                                </div>
                                            @endforeach

                                            <button type="button" wire:click="ajouterLigne({{ $i }})"
                                                    class="brio-btn-ligne">Ajouter une ligne</button>
                                        </div>

                                    @elseif($bloc['type'] === 'spacer')
                                        <label class="text-sm" for="bloc-hauteur-{{ $i }}">Hauteur (px)</label>
                                        <input id="bloc-hauteur-{{ $i }}" type="number" min="4" max="80" class="w-28"
                                               wire:model.live.debounce.500ms="blocs.{{ $i }}.height">

                                    @else
                                        <p class="text-sm opacity-70">Séparateur — rien à régler.</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <div class="flex flex-wrap gap-2">
                            @foreach(['heading' => 'Titre', 'paragraph' => 'Paragraphe', 'highlight' => 'Encart',
                                      'details' => 'Détails', 'button' => 'Bouton', 'image' => 'Image',
                                      'divider' => 'Séparateur', 'spacer' => 'Espace'] as $type => $libelle)
                                <button type="button" wire:click="ajouterBloc('{{ $type }}')" class="brio-btn-ligne">
                                    + {{ $libelle }}
                                </button>
                            @endforeach
                        </div>

                        <div class="flex flex-wrap justify-end gap-2 border-t border-slate-200/80 pt-3 dark:border-slate-700">
                            <button type="button" wire:click="dupliquer" class="brio-btn-ligne">Dupliquer</button>
                            <button type="button"
                                    wire:click="demanderLaSuppression({{ $this->gabaritCourant->id }})"
                                    class="brio-btn-ligne-danger">Supprimer</button>
                            <button type="submit" class="brio-btn-primary">Enregistrer</button>
                        </div>
                    </form>
                </x-app-card>
            @else
                <x-empty-state icon="✉️" title="Aucun gabarit"
                               message="Créez votre premier gabarit pour commencer à composer." />
            @endif
        </div>

        {{-- ── Les règles d’envoi ──────────────────────────── --}}
        @if($this->gabaritCourant)
            <div class="xl:col-span-5 xl:col-start-4">
                <x-app-card title="Envoi"
                            subtitle="Quand ce gabarit part, et combien de fois. Un gabarit peut porter plusieurs règles.">
                    @forelse($this->regles as $regle)
                        <div class="mb-3 rounded-2xl border border-slate-200/80 p-3 dark:border-slate-700"
                             wire:key="regle-{{ $regle->id }}">
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <span class="font-semibold">{{ $regle->name }}</span>

                                <div class="flex items-center gap-2">
                                    <span class="brio-chip">{{ $regle->is_active ? 'active' : 'inactive' }}</span>
                                    <button type="button" wire:click="basculerUneRegle({{ $regle->id }})"
                                            class="brio-btn-ligne">
                                        {{ $regle->is_active ? 'Suspendre' : 'Activer' }}
                                    </button>
                                    <button type="button" wire:click="retirerUneRegle({{ $regle->id }})"
                                            class="brio-btn-ligne-danger" aria-label="Supprimer cette règle">&times;</button>
                                </div>
                            </div>

                            <p class="mb-3 text-sm opacity-70">{{ $regle->enUnePhrase() }}</p>

                            <div class="grid gap-2 md:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold" for="r-nom-{{ $regle->id }}">Nom</label>
                                    <input id="r-nom-{{ $regle->id }}" type="text" class="w-full"
                                           wire:model="brouillons.{{ $regle->id }}.name">
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs font-semibold" for="r-type-{{ $regle->id }}">Déclencheur</label>
                                    <select id="r-type-{{ $regle->id }}" class="w-full"
                                            wire:model="brouillons.{{ $regle->id }}.trigger_type">
                                        <option value="manual">Manuel</option>
                                        <option value="event">À un événement</option>
                                        <option value="schedule">À une fréquence</option>
                                        <option value="reminder">En rappel</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs font-semibold" for="r-cle-{{ $regle->id }}">
                                        Événement ou jalon
                                    </label>
                                    <input id="r-cle-{{ $regle->id }}" type="text" class="w-full"
                                           placeholder="booking.confirmed"
                                           wire:model="brouillons.{{ $regle->id }}.trigger_key">
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs font-semibold" for="r-decalage-{{ $regle->id }}">
                                        Décalage (minutes) <span class="font-normal opacity-70">— négatif = avant</span>
                                    </label>
                                    <input id="r-decalage-{{ $regle->id }}" type="number" class="w-full"
                                           wire:model="brouillons.{{ $regle->id }}.offset_minutes">
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs font-semibold" for="r-freq-{{ $regle->id }}">Fréquence</label>
                                    <select id="r-freq-{{ $regle->id }}" class="w-full"
                                            wire:model="brouillons.{{ $regle->id }}.frequency">
                                        <option value="">—</option>
                                        <option value="daily">Chaque jour</option>
                                        <option value="weekly">Chaque semaine</option>
                                        <option value="monthly">Chaque mois</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs font-semibold" for="r-heure-{{ $regle->id }}">Heure d’envoi</label>
                                    <input id="r-heure-{{ $regle->id }}" type="number" min="0" max="23" class="w-full"
                                           wire:model="brouillons.{{ $regle->id }}.hour">
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs font-semibold" for="r-plafond-{{ $regle->id }}">
                                        Plafond par destinataire <span class="font-normal opacity-70">— 0 = aucun</span>
                                    </label>
                                    <input id="r-plafond-{{ $regle->id }}" type="number" min="0" class="w-full"
                                           wire:model="brouillons.{{ $regle->id }}.cap_per_recipient">
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs font-semibold" for="r-fenetre-{{ $regle->id }}">
                                        Fenêtre du plafond (heures)
                                    </label>
                                    <input id="r-fenetre-{{ $regle->id }}" type="number" min="1" class="w-full"
                                           wire:model="brouillons.{{ $regle->id }}.cap_window_hours">
                                </div>

                                <label class="flex items-start gap-2 text-sm md:col-span-2">
                                    <input type="checkbox" class="mt-0.5"
                                           wire:model="brouillons.{{ $regle->id }}.respects_opt_out">
                                    <span>
                                        Respecter le désabonnement
                                        <span class="block text-xs opacity-70">
                                            Ne peut que RESSERRER : une campagne marketing respecte le refus quoi
                                            qu’il arrive, et une alerte de fraude ne se refuse jamais.
                                        </span>
                                    </span>
                                </label>

                                <div class="flex justify-end md:col-span-2">
                                    <button type="button" wire:click="enregistrerUneRegle({{ $regle->id }})"
                                            class="brio-btn-ligne">Enregistrer cette règle</button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="mb-3 text-sm opacity-70">
                            Aucune règle : ce gabarit ne part que sur demande explicite.
                        </p>
                    @endforelse

                    <button type="button" wire:click="ajouterUneRegle" class="brio-btn-ligne">
                        Ajouter une règle d’envoi
                    </button>
                </x-app-card>
            </div>
        @endif

        {{-- ── L’aperçu ────────────────────────────────────────────────── --}}
        <div class="xl:col-span-4 space-y-4">
            <x-app-card title="Aperçu email" subtitle="Le rendu réel, sous le thème que vous choisissez.">
                <div class="mb-3 space-y-2">
                    <label class="sr-only" for="theme-apercu">Thème d’aperçu</label>
                    <select id="theme-apercu" wire:model.live="themeDApercu" class="w-full">
                        <option value="">Thème du jour (celui qui partira)</option>
                        @foreach($this->themes as $theme)
                            <option value="{{ $theme->id }}">
                                {{ $theme->name }}@if($theme->starts_on) — saison @endif
                            </option>
                        @endforeach
                    </select>

                    <p class="text-sm"><span class="font-semibold">Objet :</span> {{ $subject }}</p>

                    <button type="button" wire:click="generatePreview" class="brio-btn-ligne">
                        Consigner cet aperçu
                    </button>
                </div>

                {{-- LE DOCUMENT VIT DANS UN CADRE, PAS DANS LA PAGE. Injecte tel quel, son `<body>`
                     fusionnerait avec celui de l administration et repeindrait le fond, mode sombre
                     compris. `srcdoc` le porte en ATTRIBUT, donc echappe ; `sandbox` sans
                     `allow-scripts` interdit a un gabarit d executer quoi que ce soit. --}}
                <iframe title="Aperçu de l’e-mail"
                        sandbox
                        class="h-[560px] w-full rounded-2xl border border-slate-200/80 bg-white dark:border-slate-700"
                        srcdoc="{{ $previewHtml }}"></iframe>
            </x-app-card>

            <x-app-card title="Journal des aperçus" subtitle="Les aperçus consignés, les plus récents d’abord.">
                @forelse($recentLogs as $log)
                    <p class="border-b border-slate-200/60 py-1.5 text-sm last:border-0 dark:border-slate-700">
                        <span class="font-semibold">{{ $log->template_key }}</span>
                        <span class="opacity-70">· {{ $log->created_at?->format('d/m/Y H:i') }}</span>
                    </p>
                @empty
                    <p class="text-sm opacity-70">Aucun aperçu consigné pour le moment.</p>
                @endforelse
            </x-app-card>
        </div>
    </div>
    @endif

    {{-- ── Confirmation de suppression ──────────────────────────────────── --}}
    @if($gabaritASupprimer)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
             role="dialog" aria-modal="true" aria-labelledby="titre-suppression-gabarit">
            <div class="brio-card w-full max-w-md p-6">
                <h2 id="titre-suppression-gabarit" class="brio-section-title">Supprimer ce gabarit ?</h2>
                <p class="brio-section-subtitle mt-1">
                    Le gabarit disparaît définitivement. Les e-mails déjà partis ne sont pas touchés.
                </p>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="annulerLaSuppression" class="brio-btn-ligne">Annuler</button>
                    <button type="button" wire:click="supprimer" class="brio-btn-danger">Supprimer</button>
                </div>
            </div>
        </div>
    @endif
</div>
