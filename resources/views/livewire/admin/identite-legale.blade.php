<div class="space-y-6">
    <x-page-shell
        title="Identité légale"
        subtitle="Ce que la page publique des mentions légales annonce de la plateforme."
        eyebrow="Plateforme"
    >
        <x-slot:actions>
            <a href="{{ route('legal.mentions') }}" target="_blank" rel="noopener" class="brio-btn-secondary">
                Voir la page publique
            </a>
        </x-slot:actions>
    </x-page-shell>

    @php $manquants = $this->manquants(); @endphp

    @if($manquants !== [])
        {{-- Une page legale publique qui annonce « a completer » se voit : on le dit ici d'abord. --}}
        <div role="alert" class="brio-alerte brio-alerte-warning">
            {{ trans_choice(
                '{1}Une mention reste vide : la page publique affiche « à compléter » à sa place.
                 |[2,*]:nombre mentions restent vides : la page publique affiche « à compléter » à leur place.',
                count($manquants),
                ['nombre' => count($manquants)],
            ) }}
        </div>
    @endif

    <x-app-card title="Les cinq mentions obligatoires"
                subtitle="Elles s'affichent telles quelles sur la page publique, sans mise en forme.">
        <form wire:submit="enregistrer" class="space-y-5">
            @foreach(\App\Livewire\Admin\IdentiteLegale::CHAMPS as $cle => $champ)
                <div>
                    <label for="{{ $cle }}" class="block text-sm font-semibold text-slate-700 dark:text-slate-200">
                        {{ $champ['libelle'] }}
                    </label>

                    @if($cle === 'legal_hebergeur')
                        <textarea id="{{ $cle }}" rows="2"
                                  wire:model="valeurs.{{ $cle }}"
                                  class="mt-1 w-full rounded-xl text-sm"></textarea>
                    @else
                        <input id="{{ $cle }}"
                               type="{{ $cle === 'legal_email_contact' ? 'email' : 'text' }}"
                               wire:model="valeurs.{{ $cle }}"
                               class="mt-1 w-full rounded-xl text-sm">
                    @endif

                    <p class="brio-section-subtitle mt-1">{{ $champ['aide'] }}</p>

                    @error('valeurs.'.$cle)
                        <p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach

            <div class="flex items-center gap-3 border-t border-slate-200/80 pt-4 dark:border-slate-700">
                <button type="submit" class="brio-btn-primary">Enregistrer</button>
                <span class="brio-section-subtitle" wire:loading wire:target="enregistrer">Enregistrement…</span>
            </div>
        </form>
    </x-app-card>
</div>
