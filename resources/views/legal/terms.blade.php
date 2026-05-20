@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto py-12 px-6">
    <h1 class="text-3xl font-black text-slate-900 mb-2">Conditions Générales d'Utilisation</h1>
    <p class="text-sm text-slate-500 mb-8">Version 1.0 — Dernière mise à jour : 20/05/2026</p>

    <div class="prose prose-slate max-w-none space-y-6">
        <section>
            <h2 class="text-xl font-bold">1. Objet</h2>
            <p>CleanUx est une marketplace multi-métiers (nettoyage, peinture, babysitting, toiturier, etc.) mettant en relation des clients particuliers ou professionnels avec des prestataires indépendants ou en société, opérée par <strong>{{ config('app.name') }}</strong>.</p>
        </section>

        <section>
            <h2 class="text-xl font-bold">2. Inscription et compte</h2>
            <p>L'utilisateur s'engage à fournir des informations exactes et à jour. Le compte est strictement personnel. Toute usurpation est passible de poursuites.</p>
        </section>

        <section>
            <h2 class="text-xl font-bold">3. Tarification et paiement</h2>
            <p>Les tarifs sont indiqués TTC en euros. Paiement sécurisé via Stripe (PCI-DSS niveau 1). La carte est autorisée à la réservation et débitée au démarrage de la mission.</p>
            <p><strong>Pourboires</strong> : un pourboire optionnel post-mission est intégralement reversé au prestataire (0% commission plateforme).</p>
        </section>

        <section>
            <h2 class="text-xl font-bold">4. Annulation et remboursement</h2>
            <ul class="list-disc list-inside">
                <li>Plus de 24h avant : remboursement intégral</li>
                <li>Entre 24h et 2h : 50% remboursé</li>
                <li>Moins de 2h ou no-show : 100% facturé</li>
            </ul>
            <p>Exceptions force majeure : voir formulaire de litige.</p>
        </section>

        <section>
            <h2 class="text-xl font-bold">5. Engagements du prestataire</h2>
            <p>Prestation conforme à la description, respect des règles de l'art et de sécurité. Vérification KYC (identité) et KYB (entreprise) requise avant activation.</p>
        </section>

        <section>
            <h2 class="text-xl font-bold">6. Engagements du client</h2>
            <p>Accès aux lieux d'intervention, mise à disposition de l'environnement nécessaire, règlement selon le tarif convenu.</p>
        </section>

        <section>
            <h2 class="text-xl font-bold">7. Litiges et SAV</h2>
            <p>Médiation gratuite via le centre litiges. Recours possible auprès du médiateur de la consommation en cas d'échec.</p>
        </section>

        <section>
            <h2 class="text-xl font-bold">8. Responsabilité de la plateforme</h2>
            <p>CleanUx est éditeur d'une marketplace de mise en relation. Non employeur des prestataires. Assurance RC complémentaire optionnelle disponible.</p>
        </section>

        <section>
            <h2 class="text-xl font-bold">9. Données personnelles</h2>
            <p>Voir notre <a href="{{ route('policy.show') }}" class="text-indigo-600 underline">Politique de confidentialité</a>.</p>
        </section>

        <section>
            <h2 class="text-xl font-bold">10. Droit applicable</h2>
            <p>Droit belge. Tribunaux de Bruxelles compétents, sauf disposition contraire d'ordre public.</p>
        </section>

        <section class="border-t pt-6 mt-8 text-xs text-slate-500">
            <p>⚠ Document de base — à valider par avocat spécialisé droit du numérique avant production publique.</p>
        </section>
    </div>
</div>
@endsection
