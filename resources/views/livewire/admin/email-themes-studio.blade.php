{{-- L HABILLAGE, SEPARE DU CONTENU. Changer de saison ne touche aucun gabarit : c est le theme
     actif qui change, choisi par sa fenetre de dates. --}}
<div class="grid grid-cols-1 gap-4 xl:grid-cols-12">

    {{-- ── Les thèmes et leur calendrier ────────────────────────────────── --}}
    <div class="xl:col-span-3 space-y-4">
        <x-app-card title="Thèmes" subtitle="Le permanent, puis les saisons par priorité.">
            <div class="space-y-1">
                @foreach($this->themes as $theme)
                    <button type="button" wire:key="theme-{{ $theme->id }}" wire:click="ouvrir({{ $theme->id }})"
                            @class([
                                'w-full rounded-xl border px-3 py-2 text-left text-sm transition',
                                'border-transparent hover:opacity-80' => $theme->id !== $themeOuvert,
                                'border-current font-semibold' => $theme->id === $themeOuvert,
                            ])>
                        <span class="block">{{ $theme->name }}</span>
                        <span class="block text-xs opacity-70">
                            @if($theme->is_default) permanent
                            @elseif($theme->starts_on) {{ $theme->starts_on->format('d/m') }} → {{ $theme->ends_on?->format('d/m') }}
                            @else sans fenêtre @endif
                            @unless($theme->is_active) · inactif @endunless
                        </span>
                    </button>
                @endforeach
            </div>

            <button type="button" wire:click="nouveauTheme" class="brio-btn-ligne mt-3 w-full">
                Nouveau thème
            </button>
        </x-app-card>

        {{-- LE CALENDRIER REPOND A « QU EST-CE QUI PART AUJOURD HUI ? » — la question qu on se
             pose la veille d une operation commerciale. --}}
        <x-app-card title="Calendrier des saisons" subtitle="Ce qui habille les e-mails aujourd’hui.">
            @forelse($this->calendrier as $entree)
                <div class="flex items-center justify-between gap-2 border-b border-slate-200/60 py-2 text-sm last:border-0 dark:border-slate-700">
                    <span>
                        {{ $entree['theme']->name }}
                        <span class="block text-xs opacity-70">
                            {{ $entree['theme']->starts_on?->format('d/m') }} → {{ $entree['theme']->ends_on?->format('d/m') }}
                            @if($entree['theme']->recurs_yearly) · chaque année @endif
                        </span>
                    </span>

                    @if($entree['actif'])
                        <span class="brio-chip">en cours</span>
                    @endif
                </div>
            @empty
                <p class="text-sm opacity-70">Aucune saison définie. Le thème permanent s’applique toute l’année.</p>
            @endforelse
        </x-app-card>
    </div>

    {{-- ── L’éditeur de thème ───────────────────────────────────────────── --}}
    <div class="xl:col-span-5">
        @if($this->themeCourant)
            <x-app-card title="Habillage" subtitle="Couleurs, images et fenêtre d’application.">
                <form wire:submit="enregistrer" class="space-y-4">
                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <label for="th-nom" class="mb-1 block text-sm font-semibold">Nom</label>
                            <input id="th-nom" wire:model="nom" type="text" class="w-full">
                            @error('nom') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="th-priorite" class="mb-1 block text-sm font-semibold">
                                Priorité <span class="font-normal opacity-70">— tranche les fenêtres qui se chevauchent</span>
                            </label>
                            <input id="th-priorite" wire:model="priorite" type="number" min="0" max="999" class="w-full">
                            @error('priorite') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <label for="th-description" class="mb-1 block text-sm font-semibold">Description</label>
                            <input id="th-description" wire:model="descriptionDuTheme" type="text" class="w-full">
                        </div>
                    </div>

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-bold">Fenêtre d’application</legend>

                        <div class="grid gap-3 md:grid-cols-2">
                            <div>
                                <label for="th-debut" class="mb-1 block text-sm font-semibold">Du</label>
                                <input id="th-debut" wire:model.live="debut" type="date" class="w-full">
                            </div>
                            <div>
                                <label for="th-fin" class="mb-1 block text-sm font-semibold">Au</label>
                                <input id="th-fin" wire:model.live="fin" type="date" class="w-full">
                            </div>
                        </div>

                        <label class="flex items-start gap-2 text-sm">
                            <input wire:model="annuel" type="checkbox" class="mt-0.5">
                            <span>
                                Chaque année
                                <span class="block text-xs opacity-70">
                                    L’année est ignorée. À cocher pour Noël ou le Nouvel An ; à laisser vide pour
                                    Black Friday, Pâques et le Nouvel An chinois, qui se déplacent.
                                </span>
                            </span>
                        </label>

                        <div class="flex flex-wrap gap-4">
                            <label class="flex items-center gap-2 text-sm">
                                <input wire:model="actif" type="checkbox"> Thème actif
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input wire:model="parDefaut" type="checkbox"> Thème permanent (par défaut)
                            </label>
                        </div>
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-bold">Couleurs</legend>

                        <div class="grid gap-3 md:grid-cols-2">
                            @foreach(\App\Livewire\Admin\EmailThemesStudio::COULEURS as $champ => $libelle)
                                <div wire:key="couleur-{{ $champ }}">
                                    <label for="c-{{ $champ }}" class="mb-1 block text-sm font-semibold">{{ $libelle }}</label>
                                    <div class="flex gap-2">
                                        <input id="c-{{ $champ }}" type="color" class="h-10 w-14 rounded-lg border-0 p-0"
                                               wire:model.live="couleurs.{{ $champ }}">
                                        <input type="text" class="flex-1" aria-label="{{ $libelle }} en hexadécimal"
                                               wire:model.live.debounce.500ms="couleurs.{{ $champ }}">
                                    </div>
                                    @error('couleurs.'.$champ) <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                            @endforeach
                        </div>
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend class="text-sm font-bold">Images et matière</legend>

                        <div class="grid gap-3 md:grid-cols-2">
                            <div>
                                <label for="th-logo" class="mb-1 block text-sm font-semibold">Logo</label>
                                <input id="th-logo" wire:model.live.debounce.700ms="logo" type="url" class="w-full"
                                       placeholder="https://…">
                                @error('logo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="th-entete" class="mb-1 block text-sm font-semibold">Image d’en-tête</label>
                                <input id="th-entete" wire:model.live.debounce.700ms="imageEntete" type="url" class="w-full"
                                       placeholder="https://…">
                                @error('imageEntete') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="th-fond" class="mb-1 block text-sm font-semibold">
                                    Image de fond
                                    <span class="font-normal opacity-70">— posée sous la couleur, jamais à sa place</span>
                                </label>
                                <input id="th-fond" wire:model.live.debounce.700ms="imageFond" type="url" class="w-full"
                                       placeholder="https://…">
                                @error('imageFond') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="th-rayon" class="mb-1 block text-sm font-semibold">Arrondi des coins (px)</label>
                                <input id="th-rayon" wire:model.live="rayon" type="number" min="0" max="40" class="w-full">
                                @error('rayon') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="md:col-span-2">
                                <label for="th-police" class="mb-1 block text-sm font-semibold">
                                    Typographie
                                    <span class="font-normal opacity-70">— avec ses replis, les clients de messagerie ne chargent pas de police</span>
                                </label>
                                <input id="th-police" wire:model.live.debounce.700ms="police" type="text" class="w-full">
                                @error('police') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div class="md:col-span-2">
                                <label for="th-pied" class="mb-1 block text-sm font-semibold">Pied de page</label>
                                <input id="th-pied" wire:model.live.debounce.700ms="pied" type="text" class="w-full">
                                @error('pied') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </fieldset>

                    <div class="flex flex-wrap justify-end gap-2 border-t border-slate-200/80 pt-3 dark:border-slate-700">
                        <button type="button" wire:click="dupliquer" class="brio-btn-ligne">Dupliquer</button>

                        @unless($this->themeCourant->is_default)
                            <button type="button" wire:click="demanderLaSuppression({{ $this->themeCourant->id }})"
                                    class="brio-btn-ligne-danger">Supprimer</button>
                        @endunless

                        <button type="submit" class="brio-btn-primary">Enregistrer</button>
                    </div>
                </form>
            </x-app-card>
        @else
            <x-empty-state icon="🎨" title="Aucun thème"
                           message="Créez un thème pour habiller vos e-mails." />
        @endif
    </div>

    {{-- ── L’aperçu vivant ──────────────────────────────────────────────── --}}
    <div class="xl:col-span-4">
        <x-app-card title="Aperçu du thème" subtitle="Un vrai gabarit, sous le thème que vous êtes en train de régler.">
            <label class="sr-only" for="gabarit-apercu-theme">Gabarit d’aperçu</label>
            <select id="gabarit-apercu-theme" wire:model.live="gabaritDApercu" class="mb-3 w-full">
                @foreach($this->gabarits as $gabarit)
                    <option value="{{ $gabarit->code }}">{{ $gabarit->name }}</option>
                @endforeach
            </select>

            {{-- Meme regle que l atelier : le document vit dans un cadre, en attribut echappe,
                 et `sandbox` sans `allow-scripts` lui interdit d executer quoi que ce soit. --}}
            <iframe title="Aperçu du thème"
                    sandbox
                    class="h-[620px] w-full rounded-2xl border border-slate-200/80 bg-white dark:border-slate-700"
                    srcdoc="{{ $this->apercu }}"></iframe>
        </x-app-card>
    </div>

    {{-- ── Confirmation ─────────────────────────────────────────────────── --}}
    @if($themeASupprimer)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
             role="dialog" aria-modal="true" aria-labelledby="titre-suppression-theme">
            <div class="brio-card w-full max-w-md p-6">
                <h2 id="titre-suppression-theme" class="brio-section-title">Supprimer ce thème ?</h2>
                <p class="brio-section-subtitle mt-1">
                    Les gabarits qui l’imposaient repasseront au thème permanent. Les e-mails déjà
                    partis ne sont pas touchés.
                </p>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="annulerLaSuppression" class="brio-btn-ligne">Annuler</button>
                    <button type="button" wire:click="supprimer" class="brio-btn-danger">Supprimer</button>
                </div>
            </div>
        </div>
    @endif
</div>
