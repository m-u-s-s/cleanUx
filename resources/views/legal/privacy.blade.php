@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto py-12 px-6">
    <h1 class="text-3xl font-black text-slate-900 mb-2">Politique de confidentialité</h1>
    <p class="text-sm text-slate-500 mb-8">Conforme RGPD — Version 1.0 — 20/05/2026</p>

    <div class="prose prose-slate max-w-none space-y-6">
        <section>
            <h2 class="text-xl font-bold">1. Responsable du traitement</h2>
            <p><strong>{{ config('app.name') }}</strong> est responsable du traitement des données personnelles collectées sur la plateforme.</p>
            <p>Contact DPO : <code>dpo@cleanux.com</code> (à configurer).</p>
        </section>

        <section>
            <h2 class="text-xl font-bold">2. Données collectées</h2>
            <ul class="list-disc list-inside">
                <li><strong>Identité</strong> : nom, prénom, email, téléphone, photo de profil</li>
                <li><strong>Compte</strong> : mot de passe (haché), locale, fuseau horaire</li>
                <li><strong>Paiement</strong> : tokens Stripe (jamais le numéro de carte complet)</li>
                <li><strong>Localisation</strong> : adresses de mission, position GPS provider en mission</li>
                <li><strong>KYC/KYB</strong> : documents d'identité (providers uniquement)</li>
                <li><strong>Utilisation</strong> : missions, ratings, chat, logs techniques</li>
            </ul>
        </section>

        <section>
            <h2 class="text-xl font-bold">3. Bases légales (Art. 6 RGPD)</h2>
            <ul class="list-disc list-inside">
                <li><strong>Exécution du contrat</strong> : missions, paiements, notifications transactionnelles</li>
                <li><strong>Obligation légale</strong> : KYC/KYB, fiscalité, lutte anti-fraude</li>
                <li><strong>Intérêt légitime</strong> : analytics anonymisés, sécurité plateforme</li>
                <li><strong>Consentement</strong> : marketing emails/SMS/push (opt-in), cookies non-essentiels</li>
            </ul>
        </section>

        <section>
            <h2 class="text-xl font-bold">4. Durées de conservation</h2>
            <ul class="list-disc list-inside">
                <li>Données compte actif : durée de la relation contractuelle</li>
                <li>Données financières : 10 ans (Code de commerce)</li>
                <li>Logs analytics : 13 mois max</li>
                <li>Données KYC : 5 ans après fin de relation (réglementation AML)</li>
                <li>Logs techniques : 1 an</li>
            </ul>
        </section>

        <section>
            <h2 class="text-xl font-bold">5. Vos droits (Art. 15-22 RGPD)</h2>
            <ul class="list-disc list-inside">
                <li><strong>Accès</strong> : obtenir une copie de vos données → Export self-service via votre espace</li>
                <li><strong>Rectification</strong> : modifier les données inexactes</li>
                <li><strong>Effacement</strong> : "droit à l'oubli" — délai grâce 30 jours</li>
                <li><strong>Portabilité</strong> : export structuré (JSON/ZIP) téléchargeable</li>
                <li><strong>Opposition</strong> : marketing — opt-out immédiat</li>
                <li><strong>Limitation</strong> : geler le traitement pendant un litige</li>
                <li><strong>Plainte CNIL/APD</strong> : <a href="https://www.autoriteprotectiondonnees.be" class="text-indigo-600 underline">APD Belgique</a> ou <a href="https://www.cnil.fr" class="text-indigo-600 underline">CNIL France</a></li>
            </ul>
        </section>

        <section>
            <h2 class="text-xl font-bold">6. Sous-traitants principaux</h2>
            <ul class="list-disc list-inside">
                <li><strong>Stripe</strong> (paiements) — Irlande, conformité PCI-DSS</li>
                <li><strong>Twilio</strong> (SMS) — USA, SCC adopté</li>
                <li><strong>FCM / APNs</strong> (push) — Google / Apple</li>
                <li><strong>Onfido / Veriff</strong> (KYC) — UK / EU</li>
                <li><strong>Hébergeur</strong> : (à préciser, ex. OVH EU)</li>
            </ul>
        </section>

        <section>
            <h2 class="text-xl font-bold">7. Cookies</h2>
            <p>Voir <a href="{{ route('legal.cookies') }}" class="text-indigo-600 underline">notre politique cookies</a>.</p>
        </section>

        <section>
            <h2 class="text-xl font-bold">8. Sécurité</h2>
            <p>Chiffrement TLS 1.3 (transport), mots de passe bcrypt, tokens API rotation. Audit interne semestriel. Sentry pour monitoring. Backups quotidiens chiffrés.</p>
        </section>

        <section class="border-t pt-6 mt-8 text-xs text-slate-500">
            <p>⚠ Document de base — à valider par DPO et avocat avant production publique.</p>
        </section>
    </div>
</div>
@endsection
