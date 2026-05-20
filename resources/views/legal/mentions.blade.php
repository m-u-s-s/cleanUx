@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto py-12 px-6">
    <h1 class="text-3xl font-black text-slate-900 mb-8">Mentions légales</h1>

    <div class="prose prose-slate max-w-none space-y-6">
        <section>
            <h2 class="text-xl font-bold">Éditeur</h2>
            <p><strong>{{ config('app.name') }}</strong></p>
            <p>Société : (à compléter — SRL/SAS/SARL, numéro BCE/SIREN)</p>
            <p>Siège social : (à compléter)</p>
            <p>Email : <code>contact@cleanux.com</code></p>
        </section>

        <section>
            <h2 class="text-xl font-bold">Directeur de la publication</h2>
            <p>(Nom du représentant légal)</p>
        </section>

        <section>
            <h2 class="text-xl font-bold">Hébergeur</h2>
            <p>(à compléter — ex: OVH SAS, 2 rue Kellermann 59100 Roubaix, France)</p>
        </section>

        <section>
            <h2 class="text-xl font-bold">Propriété intellectuelle</h2>
            <p>Tous les contenus de ce site (textes, images, logos, code) sont la propriété exclusive de {{ config('app.name') }} ou de ses partenaires. Toute reproduction non autorisée est interdite.</p>
        </section>

        <section>
            <h2 class="text-xl font-bold">Médiation</h2>
            <p>Pour les consommateurs : <a href="https://www.economie.gouv.fr/mediation-conso" class="text-indigo-600 underline">Médiateur de la consommation</a> (FR) ou <a href="https://mediationconsommateur.be" class="text-indigo-600 underline">Service de médiation pour le consommateur</a> (BE).</p>
        </section>
    </div>
</div>
@endsection
