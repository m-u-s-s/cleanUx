@php
    // CES CINQ VALEURS ETAIENT ECRITES EN DUR, sous la forme « (à compléter) » : la page annonçait
    // publiquement qu'elle n'était pas remplie, et personne ne pouvait la remplir sans le code.
    // Elles vivent maintenant dans `parametres`, éditables depuis /admin/identite-legale.
    $mention = fn (string $cle) => trim((string) \App\Models\Parametre::getValeur($cle, ''));

    $societe = $mention('legal_societe');
    $siege = $mention('legal_siege_social');
    $email = $mention('legal_email_contact');
    $directeur = $mention('legal_directeur_publication');
    $hebergeur = $mention('legal_hebergeur');

    $aCompleter = '(à compléter)';
@endphp

<x-app-layout>
<div class="max-w-4xl mx-auto py-12 px-6">
    <h1 class="text-3xl font-black text-slate-900 mb-8">Mentions légales</h1>

    <div class="prose dark:prose-invert prose-slate max-w-none space-y-6">
        <section>
            <h2 class="text-xl font-bold">Éditeur</h2>
            <p><strong>{{ config('app.name') }}</strong></p>
            <p>Société : {{ $societe !== '' ? $societe : $aCompleter }}</p>
            <p>Siège social : {{ $siege !== '' ? $siege : $aCompleter }}</p>
            <p>Email :
                @if($email !== '')
                    <a href="mailto:{{ $email }}" class="brio-lien">{{ $email }}</a>
                @else
                    {{ $aCompleter }}
                @endif
            </p>
        </section>

        <section>
            <h2 class="text-xl font-bold">Directeur de la publication</h2>
            <p>{{ $directeur !== '' ? $directeur : $aCompleter }}</p>
        </section>

        <section>
            <h2 class="text-xl font-bold">Hébergeur</h2>
            <p>{{ $hebergeur !== '' ? $hebergeur : $aCompleter }}</p>
        </section>

        <section>
            <h2 class="text-xl font-bold">Propriété intellectuelle</h2>
            <p>Tous les contenus de ce site (textes, images, logos, code) sont la propriété exclusive de {{ config('app.name') }} ou de ses partenaires. Toute reproduction non autorisée est interdite.</p>
        </section>

        <section>
            <h2 class="text-xl font-bold">Médiation</h2>
            <p>Pour les consommateurs : <a href="https://www.economie.gouv.fr/mediation-conso" class="brio-lien">Médiateur de la consommation</a> (FR) ou <a href="https://mediationconsommateur.be" class="brio-lien">Service de médiation pour le consommateur</a> (BE).</p>
        </section>
    </div>
</div>
</x-app-layout>
