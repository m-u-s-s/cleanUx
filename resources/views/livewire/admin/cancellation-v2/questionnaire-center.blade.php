{{-- Onglet « Questionnaire » du centre des annulations : la page porte le titre. --}}
<div class="space-y-6">
    <div>
        <h3 class="brio-section-title">Questionnaire d’annulation</h3>
        <p class="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
            Ce que l’on demande à quelqu’un qui annule. Chaque réponse décide des frais, ou renvoie
            vers un autre geste — un chantier trop gros n’est pas une annulation.
        </p>
    </div>

    @if($erreur)
        {{-- LE MOTIF S'AFFICHE : un interrupteur qui ne bouge pas sans explication fait cliquer trois fois. --}}
        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800
                    dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200">
            {{ $erreur }}
        </div>
    @endif

    <form wire:submit="ajouterQuestion"
          class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
            Ajouter une question
        </h3>

        <div class="mt-4 grid gap-4 md:grid-cols-4">
            <label class="block">
                <span class="text-xs font-medium text-slate-600 dark:text-slate-300">Code (stable)</span>
                <input type="text" wire:model="nouvelleQuestionCode" placeholder="client_cancel_why"
                       class="mt-1 w-full rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-800">
                @error('nouvelleQuestionCode') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>

            <label class="block md:col-span-2">
                <span class="text-xs font-medium text-slate-600 dark:text-slate-300">Question posée</span>
                <input type="text" wire:model="nouvelleQuestionLabel" placeholder="Que se passe-t-il ?"
                       class="mt-1 w-full rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-800">
                @error('nouvelleQuestionLabel') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
            </label>

            <div class="grid grid-cols-2 gap-2">
                <label class="block">
                    <span class="text-xs font-medium text-slate-600 dark:text-slate-300">Posée à</span>
                    <select wire:model="nouvelleQuestionAudience"
                            class="mt-1 w-full rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-800">
                        <option value="client">Client</option>
                        <option value="provider">Prestataire</option>
                        <option value="both">Les deux</option>
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-600 dark:text-slate-300">Moteur</span>
                    <select wire:model="nouvelleQuestionMoteur"
                            class="mt-1 w-full rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-800">
                        <option value="">Tous</option>
                        <option value="domicile">À domicile</option>
                        <option value="horaire">À l’heure</option>
                        <option value="vehicule">Véhicule</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <button type="submit" class="brio-btn-primary">Ajouter</button>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Elle naît inactive : ajoutez-lui au moins une réponse avant de l’activer.
            </p>
        </div>
    </form>
    <div class="space-y-4">
        @forelse($questions as $question)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-900">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold text-slate-900 dark:text-slate-100">{{ $question->label }}</h3>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600
                                         dark:bg-slate-800 dark:text-slate-300">{{ $question->audience }}</span>
                            @if($question->engine)
                                <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-medium text-indigo-700
                                             dark:bg-indigo-950 dark:text-indigo-300">{{ $question->engine }}</span>
                            @endif
                            @if(! $question->is_active)
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800
                                             dark:bg-amber-950 dark:text-amber-300">inactive</span>
                            @endif
                        </div>
                        <p class="mt-1 font-mono text-xs text-slate-400">{{ $question->code }}</p>
                        @if($question->help_text)
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $question->help_text }}</p>
                        @endif
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="basculerQuestion({{ $question->id }})"
                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700
                                       dark:border-slate-600 dark:text-slate-200">
                            {{ $question->is_active ? 'Désactiver' : 'Activer' }}
                        </button>
                        <button type="button" wire:click="retirerQuestion({{ $question->id }})"
                                wire:confirm="Retirer cette question ? Les dossiers déjà clos restent lisibles."
                                class="rounded-lg border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700
                                       dark:border-rose-800 dark:text-rose-300">
                            Retirer
                        </button>
                    </div>
                </div>

                <ul class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($question->options as $option)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-2">
                            <div class="min-w-0">
                                <p class="text-sm text-slate-800 dark:text-slate-200">
                                    {{ $option->label }}
                                    @unless($option->is_active)
                                        <span class="ml-1 text-xs text-amber-600">(inactive)</span>
                                    @endunless
                                </p>
                                <p class="mt-0.5 flex flex-wrap gap-2 text-xs text-slate-400">
                                    <span class="font-mono">{{ $option->code }}</span>
                                    @if($option->verification !== 'none')
                                        <span>vérifiée · {{ $option->verification }}</span>
                                    @endif
                                    @if($option->outcome !== 'cancel')
                                        <span class="font-medium text-indigo-600 dark:text-indigo-400">
                                            → {{ $option->outcome }}
                                        </span>
                                    @endif
                                    @if($option->exempt_reason_id)<span>exonère</span>@endif
                                    @if($option->collusion_signal)<span class="text-amber-600">signal d’entente</span>@endif
                                </p>
                            </div>

                            <button type="button" wire:click="basculerOption({{ $option->id }})"
                                    class="shrink-0 rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold
                                           text-slate-700 dark:border-slate-600 dark:text-slate-200">
                                {{ $option->is_active ? 'Désactiver' : 'Activer' }}
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500
                        dark:border-slate-700 dark:text-slate-400">
                Aucune question. Jouez <code class="font-mono">CancellationQuestionnaireSeeder</code> pour poser
                le questionnaire par défaut.
            </div>
        @endforelse
    </div>
</div>
