<nav x-data="{ open: false }" class="sticky top-0 z-40 border-b border-slate-100 bg-white/95 shadow-sm backdrop-blur dark:border-slate-700 dark:bg-slate-900/95">
    @php
    $user = auth()->user();

    $homeHref = auth()->check() && Route::has('dashboard')
    ? route('dashboard')
    : (Route::has('home') ? route('home') : url('/'));

    $unreadCount = auth()->check()
    ? min($user->unreadNotifications()->count(), 99)
    : 0;

    $filterLinks = function (array $groups) {
    return collect($groups)
    ->map(function ($links, $group) {
    return [
    'group' => $group,
    'links' => collect($links)
    ->filter(fn ($link) => Route::has($link['route']))
    ->values(),
    ];
    })
    ->filter(fn ($group) => $group['links']->isNotEmpty())
    ->values();
    };

    $clientGroups = [
    'Essentiel' => [
    ['label' => 'Accueil', 'route' => 'client.dashboard', 'active' => 'client.dashboard', 'icon' => '🏠'],
    ['label' => 'Nouveau RDV', 'route' => 'client.rendezvous.create', 'active' => 'client.rendezvous.create', 'icon' => '➕'],
    ['label' => 'Mes rendez-vous', 'route' => 'client.rendezvous.index', 'active' => 'client.rendezvous.*', 'icon' => '📅'],
    ['label' => 'Historique', 'route' => 'client.historique', 'active' => 'client.historique', 'icon' => '🕘'],
    ],
    'Marketplace' => [
    ['label' => 'Trouver un prestataire', 'route' => 'client.providers.browse', 'active' => 'client.providers.browse', 'icon' => '🔍'],
    ['label' => 'Devis IA depuis photo', 'route' => 'client.ai.quote.photo', 'active' => 'client.ai.quote.photo', 'icon' => '🤖'],
    ['label' => 'Chantiers groupés', 'route' => 'client.bundles.manage', 'active' => 'client.bundles.*', 'icon' => '🏗️'],
    ],
    'Engagement' => [
    ['label' => 'Programme fidélité', 'route' => 'client.loyalty', 'active' => 'client.loyalty', 'icon' => '🎖️'],
    ['label' => 'Récompenses', 'route' => 'client.loyalty.rewards', 'active' => 'client.loyalty.rewards', 'icon' => '🎁'],
    ['label' => 'Parrainage', 'route' => 'client.referrals', 'active' => 'client.referrals', 'icon' => '🤝'],
    ['label' => 'Mon avis (NPS)', 'route' => 'client.nps.survey', 'active' => 'client.nps.survey', 'icon' => '⭐'],
    ],
    'Finance & paiement' => [
    ['label' => 'Finance', 'route' => 'client.finance', 'active' => 'client.finance*', 'icon' => '💳'],
    ['label' => 'Portefeuille', 'route' => 'client.wallet', 'active' => 'client.wallet', 'icon' => '👛'],
    ['label' => 'Cartes bancaires', 'route' => 'client.payment.methods', 'active' => 'client.payment.methods', 'icon' => '💳'],
    ['label' => 'Abonnements', 'route' => 'client.subscriptions', 'active' => 'client.subscriptions*', 'icon' => '🔁'],
    ['label' => 'Abonnements v2', 'route' => 'client.subscriptions-v2', 'active' => 'client.subscriptions-v2', 'icon' => '🔄'],
    ],
    'Communication & SAV' => [
    ['label' => 'Messagerie', 'route' => 'client.chat.inbox', 'active' => 'client.chat.inbox', 'icon' => '💬'],
    ['label' => 'Litiges', 'route' => 'client.claims', 'active' => 'client.claims*', 'icon' => '⚠️'],
    ['label' => 'Prestataires favoris', 'route' => 'client.favorite-employes', 'active' => 'client.favorite-employes', 'icon' => '❤️'],
    ],
    'Compte client' => [
    ['label' => 'Profil client', 'route' => 'client.profile', 'active' => 'client.profile', 'icon' => '👤'],
    ['label' => 'Éditer mon profil', 'route' => 'client.profile.edit', 'active' => 'client.profile.edit', 'icon' => '✏️'],
    ['label' => 'Mes données RGPD', 'route' => 'client.gdpr.data', 'active' => 'client.gdpr.data', 'icon' => '🔐'],
    ['label' => 'API tokens', 'route' => 'client.api-tokens', 'active' => 'client.api-tokens', 'icon' => '🔑'],
    ],
    'Pro (compte entreprise)' => [
    ['label' => 'Vérification entreprise (KYB)', 'route' => 'client.kyb.onboarding', 'active' => 'client.kyb.onboarding', 'icon' => '🏢'],
    ['label' => 'Contrats', 'route' => 'client.contracts', 'active' => 'client.contracts', 'icon' => '📜'],
    ],
    ];

    $employeGroups = [
    'Mon travail' => [
    ['label' => 'Ma journée', 'route' => 'employe.dashboard', 'active' => 'employe.dashboard', 'icon' => '🏠'],
    ['label' => 'Mes missions', 'route' => 'employe.missions', 'active' => 'employe.missions*', 'icon' => '📋'],
    ['label' => 'Devis chantiers', 'route' => 'employe.bundle-quotes', 'active' => 'employe.bundle-quotes', 'icon' => '🏗️'],
    ['label' => 'Planning', 'route' => 'employe.planning', 'active' => 'employe.planning', 'icon' => '📅'],
    ['label' => 'Disponibilités', 'route' => 'employe.disponibilites', 'active' => 'employe.disponibilites', 'icon' => '🕒'],
    ['label' => 'Historique', 'route' => 'employe.historique', 'active' => 'employe.historique', 'icon' => '🕘'],
    ['label' => 'Google Agenda', 'route' => 'employe.google.calendar', 'active' => 'employe.google.calendar', 'icon' => '🗓️'],
    ],
    'Mes revenus' => [
    ['label' => 'Tableau de bord revenus', 'route' => 'employe.earnings', 'active' => 'employe.earnings', 'icon' => '💰'],
    ['label' => 'Mon portefeuille', 'route' => 'employe.wallet', 'active' => 'employe.wallet', 'icon' => '👛'],
    ['label' => 'Stripe Connect', 'route' => 'employe.stripe-connect.start', 'active' => 'employe.stripe-connect.*', 'icon' => '💳'],
    ],
    'Mes performances' => [
    ['label' => 'Mes badges', 'route' => 'employe.badges', 'active' => 'employe.badges', 'icon' => '🏆'],
    ['label' => 'Mes avis', 'route' => 'employe.ratings', 'active' => 'employe.ratings', 'icon' => '⭐'],
    ['label' => 'Feedbacks reçus', 'route' => 'employe.feedbacks', 'active' => 'employe.feedbacks', 'icon' => '💬'],
    ],
    'Vérifications & support' => [
    ['label' => 'KYC / Identité', 'route' => 'employe.kyc', 'active' => 'employe.kyc', 'icon' => '🛡️'],
    ['label' => 'Mes litiges', 'route' => 'employe.disputes', 'active' => 'employe.disputes', 'icon' => '⚠️'],
    ['label' => 'Validation multiple', 'route' => 'employe.validation.multiple', 'active' => 'employe.validation.multiple', 'icon' => '✅'],
    ],
    'Qualité & équipe' => [
    ['label' => 'Incident', 'route' => 'employe.incident', 'active' => 'employe.incident', 'icon' => '⚠️'],
    ['label' => 'Équipe terrain', 'route' => 'employe.team', 'active' => 'employe.team', 'icon' => '👥'],
    ['label' => 'Coordination', 'route' => 'employe.coordination', 'active' => 'employe.coordination', 'icon' => '🧭'],
    ['label' => 'Chef d’équipe', 'route' => 'employe.teamlead.operations', 'active' => 'employe.teamlead.operations', 'icon' => '🧑‍💼'],
    ],
    ];

    $adminGroups = [
    'Pilotage' => [
    ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'icon' => '📊'],
    ['label' => 'Planning', 'route' => 'admin.planning', 'active' => 'admin.planning*', 'icon' => '📅'],
    ['label' => 'Missions', 'route' => 'admin.missions', 'active' => 'admin.missions*', 'icon' => '📋'],
    ['label' => 'Alertes', 'route' => 'admin.alerts', 'active' => 'admin.alerts', 'icon' => '🚨'],
    ['label' => 'Analytics', 'route' => 'admin.analytics', 'active' => 'admin.analytics', 'icon' => '📈'],
    ['label' => 'Finance', 'route' => 'admin.finance', 'active' => 'admin.finance*', 'icon' => '💶'],
    ],
    'Opérations' => [
    ['label' => 'Équipes & partenaires', 'route' => 'admin.teams.partners', 'active' => 'admin.teams.partners', 'icon' => '👥'],
    ['label' => 'Orchestration', 'route' => 'admin.orchestration', 'active' => 'admin.orchestration', 'icon' => '🧭'],
    ['label' => 'Automation', 'route' => 'admin.automation', 'active' => 'admin.automation', 'icon' => '⚙️'],
    ['label' => 'B2B opérations', 'route' => 'admin.b2b.operations', 'active' => 'admin.b2b.operations', 'icon' => '🏢'],
    ['label' => 'International', 'route' => 'admin.international', 'active' => 'admin.international', 'icon' => '🌍'],
    ['label' => 'Pays', 'route' => 'admin.countries', 'active' => 'admin.countries', 'icon' => '🗺️'],
    ['label' => 'Sites', 'route' => 'admin.sites', 'active' => 'admin.sites', 'icon' => '📍'],
    ],
    'Gestion' => [
    ['label' => 'Utilisateurs', 'route' => 'admin.utilisateurs.manage', 'active' => 'admin.utilisateurs*', 'icon' => '👤'],
    ['label' => 'Services', 'route' => 'admin.services', 'active' => 'admin.services', 'icon' => '🧽'],
    ['label' => 'Modules', 'route' => 'admin.modules', 'active' => 'admin.modules', 'icon' => '🧩'],
    ['label' => 'Feature flags', 'route' => 'admin.feature-flags.manager', 'active' => 'admin.feature-flags*', 'icon' => '🚩'],
    ['label' => 'Feedbacks', 'route' => 'admin.feedbacks', 'active' => 'admin.feedbacks*', 'icon' => '💬'],
    ['label' => 'Outils admin', 'route' => 'admin.outils', 'active' => 'admin.outils', 'icon' => '🛠️'],
    ['label' => 'Clients premium', 'route' => 'admin.premium.clients', 'active' => 'admin.premium.clients', 'icon' => '⭐'],
    ['label' => 'Crédits clients', 'route' => 'admin.customer.credits', 'active' => 'admin.customer.credits', 'icon' => '💰'],
    ],
    'Business avancé' => [
    ['label' => 'IA Dispatch', 'route' => 'admin.ai.dispatch', 'active' => 'admin.ai.dispatch', 'icon' => '🤖'],
    ['label' => 'Business', 'route' => 'admin.business.dashboard', 'active' => 'admin.business.dashboard', 'icon' => '🏢'],
    ['label' => 'Readiness', 'route' => 'admin.platform.readiness', 'active' => 'admin.platform.readiness', 'icon' => '✅'],
    ['label' => 'Factures B2B', 'route' => 'admin.b2b.monthly-invoices', 'active' => 'admin.b2b.monthly-invoices', 'icon' => '🧾'],
    ['label' => 'Approbations', 'route' => 'admin.enterprise.approvals', 'active' => 'admin.enterprise.approvals', 'icon' => '📑'],
    ['label' => 'Stripe prestataires', 'route' => 'admin.stripe-connect.providers', 'active' => 'admin.stripe-connect.providers', 'icon' => '💳'],
    ['label' => 'Emails produit', 'route' => 'admin.emails', 'active' => 'admin.emails', 'icon' => '✉️'],
    ],
    'Qualité & confiance' => [
    ['label' => 'Modération avis', 'route' => 'admin.ratings.moderation', 'active' => 'admin.ratings.moderation', 'icon' => '⭐'],
    ['label' => 'Inspections qualité', 'route' => 'admin.quality.center', 'active' => 'admin.quality.center', 'icon' => '✔️'],
    ['label' => 'Litiges & SAV', 'route' => 'admin.disputes.center', 'active' => 'admin.disputes.center', 'icon' => '🛟'],
    ['label' => 'Signalements & blocks', 'route' => 'admin.safety.center', 'active' => 'admin.safety.center', 'icon' => '🚫'],
    ['label' => 'KYC providers', 'route' => 'admin.kyc.center', 'active' => 'admin.kyc.center', 'icon' => '🪪'],
    ['label' => 'Provider badges', 'route' => 'admin.badges.center', 'active' => 'admin.badges.center', 'icon' => '🏅'],
    ['label' => 'Trades catalogue', 'route' => 'admin.trades', 'active' => 'admin.trades', 'icon' => '🛠️'],
    ],
    'Croissance & marketing' => [
    ['label' => 'Marketing automation', 'route' => 'admin.marketing.center', 'active' => 'admin.marketing.center', 'icon' => '📣'],
    ['label' => 'Codes promo', 'route' => 'admin.promotions.codes', 'active' => 'admin.promotions.codes', 'icon' => '🎟️'],
    ['label' => 'Campagnes promo', 'route' => 'admin.promotions.campaigns', 'active' => 'admin.promotions.campaigns', 'icon' => '📢'],
    ['label' => 'Parrainage', 'route' => 'admin.promotions.referrals', 'active' => 'admin.promotions.referrals', 'icon' => '🤝'],
    ['label' => 'NPS scoring', 'route' => 'admin.nps.center', 'active' => 'admin.nps.center', 'icon' => '📊'],
    ['label' => 'Programme fidélité', 'route' => 'admin.loyalty.center', 'active' => 'admin.loyalty.center', 'icon' => '🎖️'],
    ['label' => 'Récompenses loyalty', 'route' => 'admin.loyalty.rewards.center', 'active' => 'admin.loyalty.rewards.center', 'icon' => '🎁'],
    ['label' => 'Raisons annulation', 'route' => 'admin.analytics.cancellations', 'active' => 'admin.analytics.cancellations', 'icon' => '❌'],
    ],
    'Plateforme & risk' => [
    ['label' => 'Risk scoring', 'route' => 'admin.risk.center', 'active' => 'admin.risk.center', 'icon' => '🚨'],
    ['label' => 'Audit v2', 'route' => 'admin.audit.center', 'active' => 'admin.audit.center', 'icon' => '🔍'],
    ['label' => 'GDPR / RGPD', 'route' => 'admin.gdpr.center', 'active' => 'admin.gdpr.center', 'icon' => '🔐'],
    ['label' => 'Notifications prefs', 'route' => 'admin.notification-preferences.center', 'active' => 'admin.notification-preferences.center', 'icon' => '🔔'],
    ['label' => 'SMS / WhatsApp', 'route' => 'admin.sms.center', 'active' => 'admin.sms.center', 'icon' => '💬'],
    ['label' => 'Push notifications', 'route' => 'admin.push.center', 'active' => 'admin.push.center', 'icon' => '📱'],
    ['label' => 'Realtime / Reverb', 'route' => 'admin.realtime.center', 'active' => 'admin.realtime.center', 'icon' => '⚡'],
    ['label' => 'Webhooks B2B', 'route' => 'admin.webhooks-v2.center', 'active' => 'admin.webhooks-v2.center', 'icon' => '🔗'],
    ['label' => 'API Tokens v2', 'route' => 'admin.api-tokens-v2.center', 'active' => 'admin.api-tokens-v2.center', 'icon' => '🔑'],
    ['label' => 'Géolocalisation', 'route' => 'admin.geolocation-v2.center', 'active' => 'admin.geolocation-v2.center', 'icon' => '🗺️'],
    ['label' => 'Traductions i18n', 'route' => 'admin.translations.center', 'active' => 'admin.translations.center', 'icon' => '🌐'],
    ['label' => 'FX (devises)', 'route' => 'admin.fx.center', 'active' => 'admin.fx.center', 'icon' => '💱'],
    ],
    'Finance avancée' => [
    ['label' => 'Stripe hardening', 'route' => 'admin.stripe.hardening', 'active' => 'admin.stripe.hardening', 'icon' => '💳'],
    ['label' => 'Comptabilité (FEC/Sage)', 'route' => 'admin.accounting-v2.center', 'active' => 'admin.accounting-v2.center', 'icon' => '📒'],
    ['label' => 'Abonnements v2', 'route' => 'admin.subscriptions-v2.center', 'active' => 'admin.subscriptions-v2.center', 'icon' => '🔁'],
    ['label' => 'Pourboires (Tips)', 'route' => 'admin.tips.center', 'active' => 'admin.tips.center', 'icon' => '💰'],
    ['label' => 'Annulations v2', 'route' => 'admin.cancellations-v2.center', 'active' => 'admin.cancellations-v2.center', 'icon' => '🚫'],
    ['label' => 'Assurance', 'route' => 'admin.insurance.center', 'active' => 'admin.insurance.center', 'icon' => '🛡️'],
    ],
    'Opérations terrain' => [
    ['label' => 'Trip Tracking GPS', 'route' => 'admin.trip-tracking.center', 'active' => 'admin.trip-tracking.center', 'icon' => '📍'],
    ['label' => 'Presence providers', 'route' => 'admin.presence.center', 'active' => 'admin.presence.center', 'icon' => '🟢'],
    ['label' => 'Disponibilités', 'route' => 'admin.availability.center', 'active' => 'admin.availability.center', 'icon' => '📆'],
    ['label' => 'Fleet véhicules', 'route' => 'admin.fleet-v2.center', 'active' => 'admin.fleet-v2.center', 'icon' => '🚐'],
    ['label' => 'Bundles chantiers', 'route' => 'admin.bundles.center', 'active' => 'admin.bundles.center', 'icon' => '🏗️'],
    ['label' => 'Matching insights', 'route' => 'admin.matching.insights', 'active' => 'admin.matching.insights', 'icon' => '🎯'],
    ['label' => 'Pricing v2 (DSL)', 'route' => 'admin.pricing-v2.center', 'active' => 'admin.pricing-v2.center', 'icon' => '💵'],
    ['label' => 'Onboarding v2', 'route' => 'admin.onboarding-v2.center', 'active' => 'admin.onboarding-v2.center', 'icon' => '🚪'],
    ['label' => 'Contrats v2', 'route' => 'admin.contracts-v2.center', 'active' => 'admin.contracts-v2.center', 'icon' => '📜'],
    ['label' => 'Analytics v2', 'route' => 'admin.analytics.center', 'active' => 'admin.analytics.center', 'icon' => '📊'],
    ],
    'B2B & multi-tenant' => [
    ['label' => 'KYB entreprises', 'route' => 'admin.kyb-v2.center', 'active' => 'admin.kyb-v2.center', 'icon' => '🏢'],
    ['label' => 'Chat & messagerie', 'route' => 'admin.chat-v2.center', 'active' => 'admin.chat-v2.center', 'icon' => '💬'],
    ],
    ];

    $groups = collect();

    if ($user?->isClient()) {
    $groups = $filterLinks($clientGroups);
    } elseif ($user?->isEmploye()) {
    $groups = $filterLinks($employeGroups);
    } elseif ($user?->isAdmin()) {
    $groups = $filterLinks($adminGroups);
    }

    $roleLinks = $groups
    ->flatMap(fn ($group) => $group['links'])
    ->values();

    $primaryLinks = $roleLinks->take(5);

    // Mapping emoji → Heroicons (pour migration progressive sans toucher les 60+ entrées des $groups).
    // Si emoji non mappé, fallback sur l'emoji affiché (compat-back).
    $iconMap = [
        '🏠' => 'home', '➕' => 'plus', '📅' => 'calendar', '🕘' => 'clock', '🗓️' => 'calendar',
        '💳' => 'credit-card', '👛' => 'wallet', '💰' => 'banknotes', '💶' => 'currency-euro', '💵' => 'currency-euro', '🪙' => 'currency-euro', '💱' => 'currency-euro', '🧾' => 'receipt',
        '🔍' => 'magnifying-glass', '🎯' => 'sparkles',
        '🤖' => 'sparkles', '✨' => 'sparkles', '🌟' => 'star', '⭐' => 'star', '🏆' => 'sparkles', '🏅' => 'badge-check', '🎖️' => 'badge-check', '🎁' => 'gift',
        '🏗️' => 'wrench', '🛠️' => 'wrench', '🧽' => 'sparkles', '🔧' => 'wrench',
        '🤝' => 'users', '👥' => 'users', '👤' => 'user', '🧑‍💼' => 'user-circle',
        '❤️' => 'heart', '💬' => 'chat-bubble', '📞' => 'phone', '📱' => 'phone', '📣' => 'speakerphone', '📢' => 'speakerphone', '✉️' => 'envelope',
        '🔐' => 'lock-closed', '🔑' => 'key', '🪪' => 'identification', '🛡️' => 'shield-check', '🛟' => 'shield-check', '🚫' => 'x-mark',
        '🏢' => 'building-office', '🏛️' => 'building-office',
        '📜' => 'document', '📒' => 'document', '📑' => 'document', '✏️' => 'document',
        '📋' => 'briefcase', '📂' => 'briefcase',
        '📊' => 'chart-bar', '📈' => 'chart-bar', '🔄' => 'arrow-trending-up', '🔁' => 'arrow-trending-up',
        '🚨' => 'exclamation-triangle', '⚠️' => 'exclamation-triangle', '🚪' => 'logout', '✅' => 'check', '✔️' => 'check',
        '🌍' => 'globe', '🌐' => 'globe', '🗺️' => 'map-pin', '📍' => 'map-pin', '🟢' => 'check',
        '🧩' => 'puzzle', '🔗' => 'puzzle', '🧭' => 'cube', '🚐' => 'truck', '🚗' => 'truck',
        '⚡' => 'bolt', '🔥' => 'fire', '🚀' => 'rocket', '📆' => 'calendar', '🕒' => 'clock',
        '📤' => 'arrow-up', '⚙️' => 'cog-6-tooth',
    ];

    $renderIcon = function (string $icon) use ($iconMap) {
        $name = $iconMap[$icon] ?? null;
        if ($name) {
            return view('components.ui.icon', ['name' => $name, 'class' => 'w-4 h-4 shrink-0'])->render();
        }
        return '<span class="text-base leading-none">' . e($icon) . '</span>';
    };
    @endphp

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 justify-between">
            <div class="flex min-w-0">
                <div class="flex shrink-0 items-center">
                    <a href="{{ $homeHref }}" class="flex items-center gap-2 text-xl font-black tracking-tight text-blue-700 dark:text-blue-400">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-blue-600 text-sm font-black text-white shadow-sm dark:bg-blue-500">
                            CU
                        </span>
                        <span>{{ config('app.name', 'CleanUx') }}</span>
                    </a>
                </div>

                <div class="hidden sm:-my-px sm:ms-8 sm:flex sm:items-center sm:gap-6">
                    @auth
                    @foreach($primaryLinks as $link)
                    <x-nav-link :href="route($link['route'])" :active="request()->routeIs($link['active'])">
                        <span class="me-1 inline-flex items-center">{!! $renderIcon($link['icon']) !!}</span>
                        {{ $link['label'] }}
                    </x-nav-link>
                    @endforeach

                    @if($groups->isNotEmpty())
                    <div class="relative">
                        <x-dropdown align="left" width="60">
                            <x-slot name="trigger">
                                <button type="button"
                                    class="inline-flex items-center border-b-2 border-transparent px-1 pt-1 text-sm font-semibold leading-5 text-slate-500 transition hover:border-blue-300 hover:text-blue-700 focus:border-blue-400 focus:text-blue-700 focus:outline-none dark:text-slate-300 dark:hover:border-blue-500 dark:hover:text-blue-400 dark:focus:text-blue-400">
                                    Toutes les pages
                                    <svg class="ms-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <div class="max-h-[70vh] overflow-y-auto py-2">
                                    @foreach($groups as $group)
                                    <div class="px-4 pb-1 pt-3 text-[11px] font-black uppercase tracking-[0.18em] text-slate-400 dark:text-slate-500">
                                        {{ $group['group'] }}
                                    </div>

                                    @foreach($group['links'] as $link)
                                    <x-dropdown-link :href="route($link['route'])">
                                        <span class="me-2 inline-flex items-center">{!! $renderIcon($link['icon']) !!}</span>
                                        {{ $link['label'] }}
                                    </x-dropdown-link>
                                    @endforeach
                                    @endforeach
                                </div>
                            </x-slot>
                        </x-dropdown>
                    </div>
                    @endif
                    @else
                    @if(Route::has('booking.create'))
                    <x-nav-link :href="route('booking.create')" :active="request()->routeIs('booking.create')">
                        Réserver
                    </x-nav-link>
                    @endif

                    @if(Route::has('premium.offer'))
                    <x-nav-link :href="route('premium.offer')" :active="request()->routeIs('premium.offer')">
                        Premium
                    </x-nav-link>
                    @endif
                    @endauth
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:gap-3">
                <button
                    x-data
                    x-on:click="
                        const isDark = document.documentElement.classList.toggle('dark');
                        localStorage.setItem('theme', isDark ? 'dark' : 'light');
                        fetch('/api/user/theme', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content},
                            body: JSON.stringify({theme: isDark ? 'dark' : 'light'})
                        }).catch(() => {});
                    "
                    class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                    aria-label="Changer le thème"
                >
                    <svg class="h-5 w-5 dark:hidden" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" /></svg>
                    <svg class="hidden h-5 w-5 dark:block" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" /></svg>
                </button>
                <x-language-switcher />

                @auth
                @if($user?->isClient() && Route::has('client.rendezvous.create'))
                <a href="{{ route('client.rendezvous.create') }}"
                    class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-400">
                    ➕ Réserver
                </a>
                @endif

                @if($user?->isClient() && Route::has('client.calendar.interactive'))
                <a href="{{ route('client.calendar.interactive') }}">📅 Calendrier interactif</a>
                @endif
                @if($user?->isClient() && Route::has('client.recurring.templates'))
                <a href="{{ route('client.recurring.templates') }}">⭐ Templates 1-clic</a>
                @endif
                @if(Route::has('notifications.index'))
                <a href="{{ route('notifications.index') }}"
                    class="relative inline-flex items-center rounded-xl bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white">
                    🔔 Notifications

                    @if($unreadCount > 0)
                    <span class="ms-2 inline-flex min-w-[1.5rem] justify-center rounded-full bg-red-500 px-2 py-0.5 text-xs font-black text-white">
                        {{ $unreadCount }}
                    </span>
                    @endif
                </a>
                @endif

                <div class="relative ms-2">
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center rounded-xl border border-transparent bg-slate-100 px-3 py-2 text-sm font-semibold leading-4 text-slate-700 transition hover:bg-slate-200 focus:outline-none dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white">
                                <span class="max-w-[150px] truncate">{{ $user->name }}</span>
                                <svg class="ms-2 h-4 w-4 fill-current" viewBox="0 0 20 20">
                                    <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <div class="block px-4 py-2 text-xs font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">
                                Compte
                            </div>

                            @if($user?->isClient() && Route::has('client.profile'))
                            <x-dropdown-link :href="route('client.profile')">
                                👤 Espace client
                            </x-dropdown-link>
                            @endif

                            @if(Route::has('notifications.index'))
                            <x-dropdown-link :href="route('notifications.index')">
                                🔔 Notifications
                            </x-dropdown-link>
                            @endif

                            @if(Route::has('profile.show'))
                            <x-dropdown-link :href="route('profile.show')">
                                🔐 Sécurité du compte
                            </x-dropdown-link>
                            @endif

                            @if(Route::has('logout'))
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf

                                <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault(); this.closest('form').submit();">
                                    Déconnexion
                                </x-dropdown-link>
                            </form>
                            @endif
                        </x-slot>
                    </x-dropdown>
                </div>
                @else
                @if(Route::has('booking.create'))
                <a href="{{ route('booking.create') }}"
                    class="text-sm font-semibold text-slate-700 hover:text-blue-700">
                    Réserver
                </a>
                @endif

                @if(Route::has('login'))
                <a href="{{ route('login') }}"
                    class="text-sm font-semibold text-slate-700 hover:text-blue-700 dark:text-slate-300 dark:hover:text-white">
                    Connexion
                </a>
                @endif

                @if(Route::has('register'))
                <a href="{{ route('register') }}"
                    class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-400">
                    Inscription
                </a>
                @endif
                @endauth
            </div>

            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open"
                    class="inline-flex items-center justify-center rounded-xl p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{ 'hidden': open, 'inline-flex': !open }"
                            class="inline-flex"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />

                        <path :class="{ 'hidden': !open, 'inline-flex': open }"
                            class="hidden"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{ 'block': open, 'hidden': !open }" class="hidden border-t border-slate-100 bg-white sm:hidden dark:border-slate-700 dark:bg-slate-900">
        <div class="space-y-1 pb-3 pt-2">
            @auth
            @foreach($groups as $group)
            <div class="px-4 pb-1 pt-4 text-[11px] font-black uppercase tracking-[0.18em] text-slate-400 dark:text-slate-500">
                {{ $group['group'] }}
            </div>

            @foreach($group['links'] as $link)
            <x-responsive-nav-link :href="route($link['route'])" :active="request()->routeIs($link['active'])">
                <span class="me-2 inline-flex items-center">{!! $renderIcon($link['icon']) !!}</span>
                {{ $link['label'] }}
            </x-responsive-nav-link>
            @endforeach
            @endforeach

            @if(Route::has('notifications.index'))
            <x-responsive-nav-link :href="route('notifications.index')" :active="request()->routeIs('notifications.index')">
                🔔 Notifications
                @if($unreadCount > 0)
                <span class="ms-2 rounded-full bg-red-500 px-2 py-0.5 text-xs font-black text-white">
                    {{ $unreadCount }}
                </span>
                @endif
            </x-responsive-nav-link>
            @endif
            @else
            @if(Route::has('booking.create'))
            <x-responsive-nav-link :href="route('booking.create')" :active="request()->routeIs('booking.create')">
                Réserver
            </x-responsive-nav-link>
            @endif

            @if(Route::has('premium.offer'))
            <x-responsive-nav-link :href="route('premium.offer')" :active="request()->routeIs('premium.offer')">
                Premium
            </x-responsive-nav-link>
            @endif
            <x-mobile-bottom-nav />
            <x-pwa-install-prompt />
            @endauth
        </div>

        @auth
        <div class="border-t border-slate-200 pb-1 pt-4 dark:border-slate-700">
            <div class="px-4">
                <div class="text-base font-bold text-slate-800 dark:text-slate-100">{{ $user->name }}</div>
                <div class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $user->email }}</div>
                <div class="mt-3">
                    <x-language-switcher />
                </div>
            </div>

            <div class="mt-3 space-y-1">
                @if($user?->isClient() && Route::has('client.profile'))
                <x-responsive-nav-link :href="route('client.profile')" :active="request()->routeIs('client.profile')">
                    👤 Espace client
                </x-responsive-nav-link>
                @endif

                @if(Route::has('profile.show'))
                <x-responsive-nav-link :href="route('profile.show')" :active="request()->routeIs('profile.show')">
                    🔐 Sécurité du compte
                </x-responsive-nav-link>
                @endif

                @if(Route::has('logout'))
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                        onclick="event.preventDefault(); this.closest('form').submit();">
                        Déconnexion
                    </x-responsive-nav-link>
                </form>
                @endif
            </div>
        </div>
        @else
        <div class="space-y-3 border-t border-slate-200 pb-4 pt-4 dark:border-slate-700">
            <div class="px-4">
                <x-language-switcher />
            </div>

            @if(Route::has('login'))
            <x-responsive-nav-link :href="route('login')" :active="request()->routeIs('login')">
                Connexion
            </x-responsive-nav-link>
            @endif

            @if(Route::has('register'))
            <x-responsive-nav-link :href="route('register')" :active="request()->routeIs('register')">
                Inscription
            </x-responsive-nav-link>
            @endif
        </div>
        @endauth
    </div>
</nav>