{{--
    LE TITRE DE L'ERREUR ÉTAIT EN ANGLAIS SUR UNE INTERFACE FRANÇAISE.

    « Whoops! Something went wrong. » s'affichait au-dessus de la liste — y compris sur la connexion
    et l'inscription, c'est-à-dire les deux premières pages du produit. La chaîne n'existait dans
    aucun fichier de traduction, donc `__()` la rendait telle quelle.

    Elle dit aussi la mauvaise chose : rien ne « s'est mal passé », le formulaire a été refusé et la
    liste juste en dessous explique pourquoi. Un titre qui annonce une panne fait douter de
    l'application au lieu de faire corriger le champ.
--}}
@if ($errors->any())
    <div {{ $attributes }}>
        <div class="font-medium text-red-600">
            {{ $errors->count() === 1 ? __('Un champ doit être corrigé :') : __('Quelques champs doivent être corrigés :') }}
        </div>

        <ul class="mt-3 list-disc list-inside text-sm text-red-600">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
