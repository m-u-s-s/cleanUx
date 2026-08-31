{{--
    UN NOEUD DE L'ARBRE — feuille {field, op, value} ou composite {and|or: [...]} / {not: {...}}.
    Recursif via @include : chaque enfant se rend avec son propre chemin (notation pointee).
    La vue APPELANTE garantit deja `$arbreAffichable` (profondeur ET nombre de noeuds) avant le
    premier @include : ce partiel n'a pas besoin de sa propre borne, une seule marche suffit.
--}}
@php($prop = 'conditions'.($chemin === '' ? '' : ".{$chemin}"))

@if($noeud === [])
    <div class="flex flex-wrap items-center gap-2">
        <span class="brio-field-label" style="margin:0;">Ajouter :</span>
        <button type="button" class="brio-btn brio-btn-secondary" wire:click="definirNoeud('{{ $chemin }}', 'feuille')">Condition</button>
        <button type="button" class="brio-btn brio-btn-secondary" wire:click="definirNoeud('{{ $chemin }}', 'and')">Groupe ET</button>
        <button type="button" class="brio-btn brio-btn-secondary" wire:click="definirNoeud('{{ $chemin }}', 'or')">Groupe OU</button>
        <button type="button" class="brio-btn brio-btn-secondary" wire:click="definirNoeud('{{ $chemin }}', 'not')">Groupe NON</button>
    </div>
@elseif(isset($noeud['and']) && is_array($noeud['and']))
    @php($cheminListe = $chemin === '' ? 'and' : "{$chemin}.and")
    <div class="brio-choice-card !flex-col !items-stretch !cursor-auto">
        <div class="flex items-center justify-between gap-2">
            <span class="brio-chip">ET — toutes les conditions</span>
            <button type="button" class="brio-btn brio-btn-ligne-danger" wire:click="retirerNoeud('{{ $chemin }}')">Retirer</button>
        </div>
        <div class="mt-3 ml-4 space-y-3">
            @foreach($noeud['and'] as $i => $enfant)
                @include('livewire.admin.automation.partials.noeud-condition', [
                    'noeud' => (array) $enfant,
                    'chemin' => "{$cheminListe}.{$i}",
                    'champs' => $champs,
                    'operateurs' => $operateurs,
                ])
            @endforeach
        </div>
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="button" class="brio-btn brio-btn-secondary" wire:click="ajouterEnfant('{{ $cheminListe }}', 'feuille')">+ Condition</button>
            <button type="button" class="brio-btn brio-btn-secondary" wire:click="ajouterEnfant('{{ $cheminListe }}', 'and')">+ Groupe ET</button>
            <button type="button" class="brio-btn brio-btn-secondary" wire:click="ajouterEnfant('{{ $cheminListe }}', 'or')">+ Groupe OU</button>
            <button type="button" class="brio-btn brio-btn-secondary" wire:click="ajouterEnfant('{{ $cheminListe }}', 'not')">+ Groupe NON</button>
        </div>
    </div>
@elseif(isset($noeud['or']) && is_array($noeud['or']))
    @php($cheminListe = $chemin === '' ? 'or' : "{$chemin}.or")
    <div class="brio-choice-card !flex-col !items-stretch !cursor-auto">
        <div class="flex items-center justify-between gap-2">
            <span class="brio-chip">OU — au moins une condition</span>
            <button type="button" class="brio-btn brio-btn-ligne-danger" wire:click="retirerNoeud('{{ $chemin }}')">Retirer</button>
        </div>
        <div class="mt-3 ml-4 space-y-3">
            @foreach($noeud['or'] as $i => $enfant)
                @include('livewire.admin.automation.partials.noeud-condition', [
                    'noeud' => (array) $enfant,
                    'chemin' => "{$cheminListe}.{$i}",
                    'champs' => $champs,
                    'operateurs' => $operateurs,
                ])
            @endforeach
        </div>
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="button" class="brio-btn brio-btn-secondary" wire:click="ajouterEnfant('{{ $cheminListe }}', 'feuille')">+ Condition</button>
            <button type="button" class="brio-btn brio-btn-secondary" wire:click="ajouterEnfant('{{ $cheminListe }}', 'and')">+ Groupe ET</button>
            <button type="button" class="brio-btn brio-btn-secondary" wire:click="ajouterEnfant('{{ $cheminListe }}', 'or')">+ Groupe OU</button>
            <button type="button" class="brio-btn brio-btn-secondary" wire:click="ajouterEnfant('{{ $cheminListe }}', 'not')">+ Groupe NON</button>
        </div>
    </div>
@elseif(isset($noeud['not']))
    @php($cheminEnfant = $chemin === '' ? 'not' : "{$chemin}.not")
    <div class="brio-choice-card !flex-col !items-stretch !cursor-auto">
        <div class="flex items-center justify-between gap-2">
            <span class="brio-chip">NON — exclut</span>
            <button type="button" class="brio-btn brio-btn-ligne-danger" wire:click="retirerNoeud('{{ $chemin }}')">Retirer</button>
        </div>
        <div class="mt-3 ml-4">
            @include('livewire.admin.automation.partials.noeud-condition', [
                'noeud' => (array) $noeud['not'],
                'chemin' => $cheminEnfant,
                'champs' => $champs,
                'operateurs' => $operateurs,
            ])
        </div>
    </div>
@else
    {{-- FEUILLE : {field, op, value} --}}
    <div class="brio-choice-card !flex-col !items-stretch !cursor-auto">
        <div class="flex flex-wrap items-end gap-3">
            <div class="brio-form-grid flex-1 md:grid-cols-3">
                <div>
                    <label class="brio-field-label">Champ</label>
                    <select wire:model="{{ $prop }}.field">
                        <option value="">— Choisir —</option>
                        @foreach($champs as $champ)
                            <option value="{{ $champ }}">{{ $champ }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="brio-field-label">Opérateur</label>
                    <select wire:model="{{ $prop }}.op">
                        <option value="">— Choisir —</option>
                        @foreach($operateurs as $operateur)
                            <option value="{{ $operateur }}">{{ $operateur }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="brio-field-label">Valeur</label>
                    <input type="text" wire:model="{{ $prop }}.value">
                </div>
            </div>
            <button type="button" class="brio-btn brio-btn-ligne-danger" wire:click="retirerNoeud('{{ $chemin }}')">Retirer</button>
        </div>
    </div>
@endif
