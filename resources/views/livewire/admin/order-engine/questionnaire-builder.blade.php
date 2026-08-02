{{--
    Constructeur de parcours : édition à gauche, aperçu à droite.

    L'aperçu monte le VRAI composant client. Ce que voit l'administrateur ici est, au balisage
    près, ce que verra le client — pas une imitation qui divergera au premier changement.
--}}
<div class="space-y-6">

    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm text-slate-500">{{ $trade->sector?->name ?? 'Métier non rattaché' }}</p>
            <h1 class="text-2xl font-semibold text-slate-900">Parcours — {{ $trade->name }}</h1>
            <p class="mt-1 text-sm text-slate-500">
                {{ $this->questions()->count() }} question{{ $this->questions()->count() > 1 ? 's' : '' }}
                @if (! $this->canPublish())
                    · <span class="font-medium text-rose-700">publication bloquée</span>
                @endif
            </p>
        </div>

        <button type="button" wire:click="startNew"
            class="min-h-[44px] rounded-xl bg-slate-900 px-5 text-sm font-medium text-white transition hover:bg-slate-800">
            Ajouter une question
        </button>
    </header>

    @if ($flash)
        <p class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ $flash }}</p>
    @endif

    {{--
        Les avertissements du validateur. Ils ne sont pas décoratifs : ce sont eux qui empêchent un
        parcours de dériver vers quinze questions sans échappatoire, une à la fois, chacune
        justifiable prise isolément.
    --}}
    @if (count($this->issues()))
        <ul class="space-y-2">
            @foreach ($this->issues() as $issue)
                <li @class([
                    'flex gap-3 rounded-xl px-4 py-3 text-sm',
                    'bg-rose-50 text-rose-900' => $issue['severity'] === 'error',
                    'bg-amber-50 text-amber-900' => $issue['severity'] !== 'error',
                ])>
                    <span aria-hidden="true">{{ $issue['severity'] === 'error' ? '⨯' : '!' }}</span>
                    <span>{{ $issue['message'] }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_420px]">

        {{-- ─── Édition ─────────────────────────────────────────────────────────────────── --}}
        <section class="space-y-3" aria-label="Édition du questionnaire">

            {{--
                Glisser-déposer pour réordonner. Les flèches restent : elles seules fonctionnent au
                clavier et avec un lecteur d'écran, et le glisser-déposer n'a jamais su le faire.
            --}}
            <div x-data="questionSorter()" x-init="boot()" data-sortable-root>
            @forelse ($this->questions() as $index => $question)
                <article draggable="true" data-question-id="{{ $question->id }}" @class([
                    'rounded-2xl border bg-white p-4',
                    'border-slate-200' => $question->is_active,
                    'border-dashed border-slate-300 opacity-60' => ! $question->is_active,
                ]) wire:key="q-{{ $question->id }}">

                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="truncate text-[15px] font-medium text-slate-900">{{ $question->label }}</h2>
                            <p class="mt-0.5 font-mono text-xs text-slate-400">{{ $question->code }} · {{ $question->type }}</p>
                        </div>

                        {{-- L'ordre s'enregistre immédiatement : un ordre à penser à sauver finit perdu. --}}
                        <div class="flex shrink-0 items-center gap-1">
                            <button type="button" wire:click="move({{ $question->id }}, -1)" aria-label="Monter"
                                class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 disabled:opacity-30"
                                @disabled($index === 0)>↑</button>
                            <button type="button" wire:click="move({{ $question->id }}, 1)" aria-label="Descendre"
                                class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 disabled:opacity-30"
                                @disabled($index === $this->questions()->count() - 1)>↓</button>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                        @if ($question->is_required)
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">Obligatoire</span>
                        @endif
                        @if ($question->is_essential)
                            <span class="rounded-full bg-sky-50 px-2.5 py-1 text-sky-700">Posée en mode urgent</span>
                        @endif
                        @unless ($question->allows_unknown)
                            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-amber-800">Sans porte de sortie</span>
                        @endunless
                    </div>

                    <div class="mt-3 flex flex-wrap gap-3 text-sm">
                        <button type="button" wire:click="edit({{ $question->id }})"
                            class="font-medium text-slate-700 underline underline-offset-4 hover:text-slate-900">Modifier</button>
                        <button type="button" wire:click="toggleActive({{ $question->id }})"
                            class="text-slate-500 underline underline-offset-4 hover:text-slate-800">
                            {{ $question->is_active ? 'Retirer du parcours' : 'Remettre en ligne' }}
                        </button>
                        <button type="button" wire:click="confirmArchive({{ $question->id }})"
                            class="text-rose-700 underline underline-offset-4 hover:text-rose-900">Archiver</button>
                        @if ($question->isOptionBased())
                            <button type="button" wire:click="addOption({{ $question->id }})"
                                class="text-slate-500 underline underline-offset-4 hover:text-slate-800">+ réponse</button>
                        @endif
                    </div>

                    {{--
                        Les traductions, repliees : la plupart du temps on edite le francais. Le
                        badge dit ce qui MANQUE, parce qu'un trou de traduction se decouvre sinon
                        en production, par un client qui ne comprend pas la question.
                    --}}
                    @php($missing = $question->missingLocales())
                    <details class="mt-3 border-t border-slate-100 pt-3">
                        <summary class="cursor-pointer text-sm text-slate-600">
                            Traductions
                            @if ($missing)
                                <span class="ml-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-800">
                                    {{ count($missing) }} manquante{{ count($missing) > 1 ? 's' : '' }}
                                </span>
                            @else
                                <span class="ml-1 text-xs text-emerald-700">complètes</span>
                            @endif
                        </summary>

                        <div class="mt-3 space-y-3">
                            @foreach ($this->translationLocales() as $code => $name)
                                <div>
                                    <label for="t-{{ $question->id }}-{{ $code }}"
                                        class="block text-xs font-medium text-slate-500">{{ $name }}</label>
                                    <input id="t-{{ $question->id }}-{{ $code }}" type="text"
                                        value="{{ $question->translations->where('field', 'label')->firstWhere('locale', $code)?->value }}"
                                        placeholder="{{ $question->label }}"
                                        wire:change="saveTranslation({{ $question->id }}, '{{ $code }}', 'label', $event.target.value)"
                                        class="mt-1 w-full rounded-lg border-slate-300 text-sm focus:border-slate-900 focus:ring-0">
                                </div>
                            @endforeach
                            <p class="text-xs text-slate-400">
                                Laisser vide affiche le libellé français : mieux vaut la mauvaise langue qu’un blanc.
                            </p>
                        </div>
                    </details>

                    @if ($question->options->isNotEmpty())
                        <ul class="mt-3 space-y-1 border-t border-slate-100 pt-3">
                            @foreach ($question->options as $option)
                                <li class="flex items-center justify-between gap-3 text-sm" wire:key="o-{{ $option->id }}">
                                    <span @class(['text-slate-700', 'line-through opacity-50' => ! $option->is_active])>
                                        {{ $option->label }}
                                        @if ($option->is_default)
                                            <span class="ml-1 text-xs text-emerald-700">(défaut)</span>
                                        @endif
                                    </span>
                                    <span class="shrink-0 tabular-nums text-slate-500">
                                        @if ($option->price_modifier_cents)
                                            {{ $option->price_modifier_cents > 0 ? '+' : '' }}{{ number_format($option->price_modifier_cents / 100, 2, ',', ' ') }} €
                                        @endif
                                        @if ($option->price_multiplier)
                                            ×{{ $option->price_multiplier }}
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </article>
            @empty
                <p class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">
                    Aucune question. Commencez par la plus déterminante pour le prix — la surface, le type d’intervention.
                </p>
            @endforelse
            </div>

            {{-- ─── Bibliotheque ─────────────────────────────────────────────────────────── --}}
            @if ($this->libraryQuestions()->isNotEmpty())
                <details class="rounded-2xl border border-slate-200 bg-white p-4">
                    <summary class="cursor-pointer text-sm font-medium text-slate-900">
                        Bibliothèque · {{ $this->libraryQuestions()->count() }} question(s) réutilisable(s)
                    </summary>

                    <p class="mt-2 text-xs text-slate-500">
                        Reprendre crée une COPIE dans ce métier. Ajuster le prix ici ne touchera pas
                        les autres métiers qui l’ont reprise.
                    </p>

                    <ul class="mt-3 space-y-2">
                        @foreach ($this->libraryQuestions() as $template)
                            <li class="flex items-center justify-between gap-3" wire:key="lib-{{ $template->id }}">
                                <span class="min-w-0">
                                    <span class="block truncate text-sm text-slate-800">{{ $template->label }}</span>
                                    <span class="font-mono text-xs text-slate-400">{{ $template->code }}</span>
                                </span>
                                <button type="button" wire:click="adoptFromLibrary({{ $template->id }})"
                                    class="shrink-0 rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-800 hover:bg-slate-50">
                                    Reprendre
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </section>

        {{-- ─── Aperçu et simulateur ────────────────────────────────────────────────────── --}}
        <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start" aria-label="Aperçu client">

            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Aperçu client</h2>

                    <select wire:model.live="previewMode"
                        class="rounded-lg border-slate-300 py-1.5 text-xs text-slate-700 focus:border-slate-900 focus:ring-0"
                        aria-label="Mode simulé">
                        <option value="scheduled">Planifié</option>
                        <option value="asap">Immédiat</option>
                    </select>
                </div>

                {{-- Le VRAI composant client, pas une maquette. --}}
                <div class="max-w-[390px]">
                    @foreach ($this->questions()->where('is_active', true) as $question)
                        @livewire('order-engine.question-renderer',
                            ['question' => $question, 'preview' => true],
                            key('preview-'.$question->id))
                    @endforeach
                </div>
            </div>

            {{--
                Le simulateur. Une grille faite d'additions, de multiplicateurs et de coefficients
                par unité ne se vérifie pas de tête : c'est en la voyant se construire ligne par
                ligne qu'on repère le zéro de trop.
            --}}
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Prix construit</h2>

                @if ($this->quote()->quoteOnly)
                    <p class="mt-3 text-sm text-slate-600">
                        Ce métier est au devis obligatoire : aucun prix n’est annoncé au client.
                    </p>
                @else
                    <ul class="mt-3 space-y-1.5 text-sm">
                        @foreach ($this->quote()->lines as $line)
                            <li class="flex items-baseline justify-between gap-3">
                                <span class="min-w-0 text-slate-600">
                                    {{ $line['label'] }}
                                    @if ($line['detail'])
                                        <span class="block text-xs text-slate-400">{{ $line['detail'] }}</span>
                                    @endif
                                </span>
                                <span class="shrink-0 tabular-nums text-slate-900">
                                    {{ number_format($line['min_cents'] / 100, 2, ',', ' ') }} €
                                    @if ($line['max_cents'] !== $line['min_cents'])
                                        – {{ number_format($line['max_cents'] / 100, 2, ',', ' ') }} €
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-4 flex items-baseline justify-between border-t border-slate-200 pt-3">
                        <span class="text-sm font-medium text-slate-700">Estimation</span>
                        <span class="text-xl font-semibold tabular-nums text-slate-900">
                            @if ($this->quote()->isExact())
                                {{ number_format($this->quote()->minCents / 100, 2, ',', ' ') }} €
                            @else
                                {{ number_format($this->quote()->minCents / 100, 0, ',', ' ') }}
                                – {{ number_format($this->quote()->maxCents / 100, 0, ',', ' ') }} €
                            @endif
                        </span>
                    </div>

                    @unless ($this->quote()->isExact())
                        <p class="mt-1 text-xs text-slate-500">
                            Fourchette : une réponse « je ne sais pas » élargit l’estimation au lieu de bloquer.
                        </p>
                    @endunless
                @endif
            </div>
        </aside>
    </div>

    {{-- ─── Formulaire d'édition ────────────────────────────────────────────────────────── --}}
    @if ($editingId !== null)
        <div class="rounded-2xl border border-slate-300 bg-white p-5" role="region" aria-label="Édition d’une question">
            <h2 class="text-lg font-semibold text-slate-900">
                {{ $editingId ? 'Modifier la question' : 'Nouvelle question' }}
            </h2>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label class="sm:col-span-2">
                    <span class="mb-1 block text-sm font-medium text-slate-700">Question posée au client</span>
                    <input type="text" wire:model.live.debounce.400ms="form.label"
                        class="w-full rounded-xl border-slate-300 focus:border-slate-900 focus:ring-0">
                    @error('form.label') <span class="mt-1 block text-sm text-rose-700">{{ $message }}</span> @enderror
                </label>

                <label>
                    <span class="mb-1 block text-sm font-medium text-slate-700">Code</span>
                    <input type="text" wire:model="form.code" @disabled($this->codeIsLocked())
                        class="w-full rounded-xl border-slate-300 font-mono text-sm focus:border-slate-900 focus:ring-0 disabled:bg-slate-100">
                    <span class="mt-1 block text-xs text-slate-500">
                        @if ($this->codeIsLocked())
                            Verrouillé : des commandes citent ce code. Le renommer rendrait leurs devis inexplicables.
                        @else
                            Clé stable des réponses. Elle ne changera plus dès la première commande.
                        @endif
                    </span>
                    @error('form.code') <span class="mt-1 block text-sm text-rose-700">{{ $message }}</span> @enderror
                </label>

                <label>
                    <span class="mb-1 block text-sm font-medium text-slate-700">Type</span>
                    <select wire:model.live="form.type" class="w-full rounded-xl border-slate-300 focus:border-slate-900 focus:ring-0">
                        @foreach ($this->questionTypes() as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="sm:col-span-2">
                    <span class="mb-1 block text-sm font-medium text-slate-700">Aide affichée sous la question</span>
                    <input type="text" wire:model="form.help_text"
                        class="w-full rounded-xl border-slate-300 focus:border-slate-900 focus:ring-0">
                </label>

                <label>
                    <span class="mb-1 block text-sm font-medium text-slate-700">Disposition</span>
                    <select wire:model.live="form.layout" class="w-full rounded-xl border-slate-300 focus:border-slate-900 focus:ring-0">
                        <option value="cards">Cartes</option>
                        <option value="chips">Pastilles</option>
                        <option value="dropdown">Liste déroulante</option>
                        <option value="slider">Curseur</option>
                        <option value="counter">Compteur</option>
                    </select>
                </label>

                <label>
                    <span class="mb-1 block text-sm font-medium text-slate-700">Impact sur le prix</span>
                    <select wire:model.live="form.pricing_mode" class="w-full rounded-xl border-slate-300 focus:border-slate-900 focus:ring-0">
                        <option value="none">Aucun</option>
                        <option value="add">Montant fixe</option>
                        <option value="multiply">Coefficient</option>
                        <option value="per_unit">Par unité</option>
                    </select>
                </label>

                <label>
                    <span class="mb-1 block text-sm font-medium text-slate-700">Coefficient (centimes)</span>
                    <input type="number" wire:model="form.pricing_coefficient"
                        class="w-full rounded-xl border-slate-300 tabular-nums focus:border-slate-900 focus:ring-0">
                </label>

                <div class="grid grid-cols-3 gap-2 sm:col-span-2">
                    @foreach (['min' => 'Minimum', 'max' => 'Maximum', 'step' => 'Pas'] as $key => $legend)
                        <label>
                            <span class="mb-1 block text-sm font-medium text-slate-700">{{ $legend }}</span>
                            <input type="number" wire:model="form.{{ $key }}"
                                class="w-full rounded-xl border-slate-300 tabular-nums focus:border-slate-900 focus:ring-0">
                        </label>
                    @endforeach
                </div>

                <div class="space-y-2 sm:col-span-2">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="form.is_required" class="rounded border-slate-300 text-slate-900">
                        Obligatoire
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="form.allows_unknown" class="rounded border-slate-300 text-slate-900">
                        Offrir « je ne sais pas » — sans quoi un client qui ignore la réponse est bloqué
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="form.is_essential" class="rounded border-slate-300 text-slate-900">
                        Poser aussi en mode urgent — le questionnaire y est réduit à l’essentiel
                    </label>
                </div>
            </div>

            <div class="mt-5 flex gap-3">
                <button type="button" wire:click="save"
                    class="min-h-[44px] rounded-xl bg-slate-900 px-5 text-sm font-medium text-white hover:bg-slate-800">
                    Enregistrer
                </button>
                <button type="button" wire:click="cancel"
                    class="min-h-[44px] rounded-xl px-5 text-sm font-medium text-slate-600 hover:text-slate-900">
                    Annuler
                </button>
            </div>
        </div>
    @endif

    {{-- ─── Confirmation d'archivage ────────────────────────────────────────────────────── --}}
    @if ($archiveImpact)
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5" role="alertdialog" aria-label="Confirmer l’archivage">
            <h2 class="text-lg font-semibold text-rose-900">Archiver cette question ?</h2>
            <p class="mt-2 text-sm leading-relaxed text-rose-900">{{ $archiveImpact['summary'] }}</p>

            <div class="mt-4 flex gap-3">
                <button type="button" wire:click="archive"
                    class="min-h-[44px] rounded-xl bg-rose-700 px-5 text-sm font-medium text-white hover:bg-rose-800">
                    Archiver
                </button>
                <button type="button" wire:click="cancelArchive"
                    class="min-h-[44px] rounded-xl px-5 text-sm font-medium text-rose-900 hover:underline">
                    Annuler
                </button>
            </div>
        </div>
    @endif

    {{--
        ─── Mise en ligne, historique et portabilité ────────────────────────────────────────

        Ces quatre gestes existaient dans le composant sans qu'aucun bouton n'y mène : publier,
        exporter, importer, dupliquer. Un test Livewire qui appelle `publish()` directement ne
        prouve jamais qu'un bouton l'appelle — il prouve que la méthode marche, ce qui n'est pas la
        même chose, et c'est exactement ainsi qu'un écran devient inutilisable sans qu'une suite
        verte ne bronche.
    --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-5" aria-labelledby="mise-en-ligne">
        <h2 id="mise-en-ligne" class="text-lg font-semibold text-slate-900">Mise en ligne</h2>

        @error('publication')
            <p class="mt-3 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">{{ $message }}</p>
        @enderror

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <button type="button" wire:click="publish" @disabled(! $this->canPublish())
                class="min-h-[44px] rounded-xl bg-slate-900 px-5 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-40">
                Publier ce parcours
            </button>

            @if ($this->hasUnpublishedChanges())
                <span class="text-sm text-amber-800">Des modifications ne sont pas encore en ligne.</span>
            @elseif ($this->currentRevision())
                <span class="text-sm text-slate-500">
                    En ligne : version {{ $this->currentRevision()->version }}.
                </span>
            @else
                <span class="text-sm text-slate-500">Jamais publié.</span>
            @endif
        </div>

        {{-- L'historique, et le moyen d'y revenir. Figer des versions sans pouvoir les rejouer ne
             sert qu'à constater les dégâts. --}}
        @if ($this->revisions->isNotEmpty())
            <div class="mt-6 border-t border-slate-100 pt-5">
                <h3 class="text-sm font-semibold text-slate-900">Historique</h3>

                <ul class="mt-3 space-y-1.5">
                    @foreach ($this->revisions as $revision)
                        <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 px-4 py-2.5 text-sm"
                            wire:key="revision-{{ $revision->id }}">
                            <span class="text-slate-700">
                                Version {{ $revision->version }}
                                @if ($revision->published_at)
                                    · {{ $revision->published_at->translatedFormat('j F Y à H\hi') }}
                                @endif
                                @if ($revision->publishedBy)
                                    · {{ $revision->publishedBy->name }}
                                @endif
                            </span>

                            @unless ($loop->first)
                                <button type="button" wire:click="restoreRevision({{ $revision->id }})"
                                    wire:confirm="Remettre la version {{ $revision->version }} en ligne ? Elle repartira sous un nouveau numéro ; les versions intermédiaires restent consultables."
                                    class="min-h-[36px] rounded-lg px-3 text-sm font-medium text-slate-900 underline-offset-2 hover:underline">
                                    Restaurer
                                </button>
                            @endunless
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Portabilité : sortir un parcours d'un environnement ET l'y faire entrer. --}}
        <div class="mt-6 grid gap-5 border-t border-slate-100 pt-5 sm:grid-cols-2">
            <div>
                <h3 class="text-sm font-semibold text-slate-900">Exporter</h3>
                <p class="mt-1 text-sm text-slate-500">Le questionnaire complet, en JSON.</p>
                <button type="button" wire:click="export"
                    class="mt-3 min-h-[44px] rounded-xl border border-slate-300 px-4 text-sm font-medium text-slate-900 hover:bg-slate-50">
                    Télécharger le JSON
                </button>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-slate-900">Importer</h3>
                <p class="mt-1 text-sm text-slate-500">
                    Rien n’est supprimé : un import complète et met à jour, il ne remet pas à zéro.
                </p>

                <input type="file" wire:model="importFile" accept="application/json,.json"
                    aria-label="Fichier JSON d’un parcours exporté"
                    class="mt-3 block w-full text-sm text-slate-600 file:mr-3 file:min-h-[36px] file:rounded-lg file:border file:border-slate-300 file:bg-white file:px-3 file:text-sm file:font-medium file:text-slate-900">

                @error('importFile')
                    <p class="mt-2 text-sm text-rose-700" role="alert">{{ $message }}</p>
                @enderror

                <button type="button" wire:click="import" @disabled(! $importFile)
                    class="mt-3 min-h-[44px] rounded-xl border border-slate-300 px-4 text-sm font-medium text-slate-900 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40">
                    Importer ce fichier
                </button>
            </div>
        </div>

        {{-- Dupliquer : « Peinture intérieure » et « Peinture extérieure » partagent l'essentiel. --}}
        @if ($this->duplicationTargets()->isNotEmpty())
            <div class="mt-6 border-t border-slate-100 pt-5">
                <h3 class="text-sm font-semibold text-slate-900">Recopier vers un autre métier</h3>

                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($this->duplicationTargets() as $target)
                        <button type="button" wire:click="duplicateTo({{ $target->id }})"
                            wire:confirm="Recopier ce questionnaire vers « {{ $target->name }} » ? Les questions existantes y seront mises à jour, aucune ne sera supprimée."
                            wire:key="dup-{{ $target->id }}"
                            class="min-h-[36px] rounded-lg border border-slate-300 px-3 text-sm text-slate-700 hover:bg-slate-50">
                            {{ $target->name }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    </section>
</div>

@push('scripts')
<script>
    window.questionSorter = () => ({
        dragged: null,

        boot() {
            const root = this.$el;

            root.addEventListener('dragstart', (e) => {
                this.dragged = e.target.closest('[data-question-id]');
                if (this.dragged) {
                    e.dataTransfer.effectAllowed = 'move';
                    this.dragged.style.opacity = '0.4';
                }
            });

            root.addEventListener('dragend', () => {
                if (this.dragged) {
                    this.dragged.style.opacity = '';
                }
                this.dragged = null;
            });

            root.addEventListener('dragover', (e) => {
                e.preventDefault();
                const over = e.target.closest('[data-question-id]');

                if (! over || ! this.dragged || over === this.dragged) {
                    return;
                }

                // Insertion avant ou apres selon le cote survole : sans ce test, deposer sur la
                // moitie basse d'une carte la placerait quand meme au-dessus.
                const box = over.getBoundingClientRect();
                const after = (e.clientY - box.top) > (box.height / 2);
                over.parentNode.insertBefore(this.dragged, after ? over.nextSibling : over);
            });

            root.addEventListener('drop', (e) => {
                e.preventDefault();
                this.commit();
            });
        },

        commit() {
            const ids = Array.from(this.$el.querySelectorAll('[data-question-id]'))
                .map((el) => el.dataset.questionId);

            // Le serveur revalide : l'ordre vient du navigateur, il n'est pas cru sur parole.
            this.$wire.reorder(ids);
        },
    });
</script>
@endpush
