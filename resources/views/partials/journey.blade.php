{{-- ============================================================
     CleanUx — "Le parcours d'une mission" (scrollytelling)
     7 scènes SVG animées pilotées par le scroll (voir journey.css + app.js).
     Fallback accessible : <ol class="cx-journey__steps"> visible sans JS /
     en prefers-reduced-motion.
     ============================================================ --}}
{{-- data-cx-journey-gsap : neutralise l'IIFE journey de app.js au profit de
     la version cinématique (GSAP + globe 3D) de resources/js/home-journey.js. --}}
<section class="cx-journey" data-cx-journey data-cx-journey-gsap aria-label="Le parcours d'une mission CleanUx, étape par étape">

    {{-- Stepper statique (no-JS / reduced-motion) --}}
    <div class="mx-auto max-w-2xl px-6 pt-20 text-center">
        <p class="cx-journey__eyebrow">De la réservation à la poignée de main</p>
        <h2 class="cx-journey__title mx-auto mt-2">Une mission, du globe à votre porte.</h2>
    </div>
    <ol class="cx-journey__steps mt-10">
        <li><div><b>Où que vous soyez</b><span>30+ métiers couverts dans 9 pays.</span></div></li>
        <li><div><b>Réservez en quelques secondes</b><span>Quelques clics depuis votre téléphone.</span></div></li>
        <li><div><b>Devis IA depuis une photo</b><span>Une fourchette de prix en 10 secondes.</span></div></li>
        <li><div><b>Chantier groupé multi-métiers</b><span>Plusieurs pros orchestrés, 1 facture, prix groupé.</span></div></li>
        <li><div><b>Suivi en temps réel</b><span>La position et l'ETA du prestataire, en direct.</span></div></li>
        <li><div><b>Arrivée & QR de départ</b><span>Il scanne le QR : la mission démarre, horodatée.</span></div></li>
        <li><div><b>Le travail est fait</b><span>Avec soin, par un pro vérifié et assuré.</span></div></li>
        <li><div><b>QR de fin</b><span>Rescan sur votre téléphone : mission validée.</span></div></li>
        <li><div><b>Satisfaction & paiement</b><span>Tout le monde repart gagnant. Paiement libéré.</span></div></li>
    </ol>

    {{-- Scène animée (sticky) --}}
    <div class="cx-journey__sticky">
        {{-- Canvas du monde 3D (rempli par home-journey.js). En .is-3d, les
             visuels SVG ci-dessous sont masqués mais les légendes restent. --}}
        <canvas class="cx-journey__canvas" data-cx-journey-canvas aria-hidden="true"></canvas>
        <p class="cx-journey__eyebrow">Le parcours d'une mission</p>
        <h2 class="cx-journey__title">Du globe à votre porte, sans friction.</h2>

        <div class="cx-stage" aria-hidden="true">

            {{-- ===== Scène 0 — La Terre qui tourne ===== --}}
            {{-- .has-3d (ajouté par home-journey.js) masque le SVG et révèle le
                 globe Three.js. Sans WebGL / sans JS, le SVG reste le visuel. --}}
            <div class="cx-scene is-active">
                <div class="cx-globe3d" data-cx-globe-3d aria-hidden="true"></div>
                <svg class="cx-art" viewBox="0 0 240 240" role="img">
                    <defs>
                        <radialGradient id="jOcean" cx="38%" cy="32%" r="75%">
                            <stop offset="0%" stop-color="#93c5fd"/>
                            <stop offset="55%" stop-color="#3b82f6"/>
                            <stop offset="100%" stop-color="#1e3a8a"/>
                        </radialGradient>
                        <clipPath id="jGlobe"><circle cx="120" cy="120" r="78"/></clipPath>
                    </defs>
                    <circle class="cx-earth-orbit" cx="120" cy="120" r="104" fill="none" stroke="#c7d2fe" stroke-width="1.5" stroke-dasharray="3 9"/>
                    <circle cx="120" cy="120" r="80" fill="url(#jOcean)"/>
                    <g clip-path="url(#jGlobe)" class="cx-earth-spin" fill="#34d399">
                        <path d="M74 66 q20 -12 36 4 q14 14 -4 26 q-22 8 -36 -8 q-8 -12 4 -22 Z"/>
                        <path d="M122 116 q24 -8 32 12 q4 20 -18 24 q-24 2 -26 -18 q0 -14 12 -18 Z"/>
                        <path d="M92 168 q18 -6 24 8 q2 14 -14 18 q-18 0 -18 -14 q0 -8 8 -12 Z"/>
                        <path d="M150 80 q14 -2 16 12 q0 12 -14 12 q-12 -2 -10 -14 q2 -8 8 -10 Z"/>
                    </g>
                    <ellipse cx="92" cy="90" rx="28" ry="16" fill="#ffffff" opacity=".18"/>
                    <g class="cx-earth-sat"><circle cx="224" cy="120" r="5" fill="#ffb648" stroke="#fff" stroke-width="2"/></g>
                </svg>
                <p class="cx-scene__cap"><b>Où que vous soyez</b><span>30+ métiers couverts, partout en Europe.</span></p>
            </div>

            {{-- ===== Scène 1 — Téléphone dans les mains ===== --}}
            <div class="cx-scene">
                <svg class="cx-art" viewBox="0 0 240 240" role="img">
                    <g fill="#f3c6a0">
                        <rect x="34" y="172" width="50" height="74" rx="24"/>
                        <rect x="156" y="172" width="50" height="74" rx="24"/>
                        <rect x="74" y="150" width="22" height="48" rx="11" transform="rotate(20 85 174)"/>
                        <rect x="144" y="150" width="22" height="48" rx="11" transform="rotate(-20 155 174)"/>
                    </g>
                    <rect x="78" y="34" width="84" height="158" rx="16" fill="#0f172a"/>
                    <rect x="85" y="47" width="70" height="132" rx="9" fill="#eef2ff"/>
                    <circle cx="120" cy="41" r="2.4" fill="#475569"/>
                    <rect x="94" y="58" width="52" height="11" rx="5.5" fill="#6366f1"/>
                    <rect x="94" y="80" width="52" height="8" rx="4" fill="#cbd5e1"/>
                    <rect x="94" y="94" width="36" height="8" rx="4" fill="#cbd5e1"/>
                    <rect x="94" y="112" width="52" height="8" rx="4" fill="#cbd5e1"/>
                    <rect x="94" y="150" width="52" height="20" rx="10" fill="#ffb648"/>
                </svg>
                <p class="cx-scene__cap"><b>Réservez en quelques secondes</b><span>Un service, une adresse, c'est parti.</span></p>
            </div>

            {{-- ===== Scène — Devis IA depuis une photo ===== --}}
            <div class="cx-scene">
                <svg class="cx-art" viewBox="0 0 240 240" role="img">
                    <defs><clipPath id="jShot"><rect x="72" y="42" width="96" height="116" rx="10"/></clipPath></defs>
                    {{-- téléphone --}}
                    <rect x="62" y="26" width="116" height="188" rx="20" fill="#0f172a"/>
                    <rect x="70" y="40" width="100" height="160" rx="12" fill="#eef2ff"/>
                    {{-- viseur : une pièce à estimer --}}
                    <g clip-path="url(#jShot)">
                        <rect x="72" y="42" width="96" height="116" fill="#dbe4f0"/>
                        <rect x="72" y="120" width="96" height="38" fill="#c7d2e0"/>
                        <rect x="96" y="64" width="30" height="30" rx="2" fill="#aebfd4"/>
                        <path d="M72 120 L168 120" stroke="#94a3b8" stroke-width="1.5"/>
                        {{-- ligne de scan IA --}}
                        <rect class="cx-scanline" x="72" y="46" width="96" height="4" rx="2" fill="#6366f1" opacity=".9"/>
                    </g>
                    {{-- étincelles IA --}}
                    <g fill="#ffb648">
                        <path class="cx-spark" d="M150 56 l3 8 l8 3 l-8 3 l-3 8 l-3 -8 l-8 -3 l8 -3 Z"/>
                        <path class="cx-spark cx-spark--2" d="M86 62 l2.5 6 l6 2.5 l-6 2.5 l-2.5 6 l-2.5 -6 l-6 -2.5 l6 -2.5 Z"/>
                    </g>
                    {{-- badge prix estimé (pop) --}}
                    <g class="cx-price-pop" transform="translate(58,150)">
                        <rect width="124" height="30" rx="15" fill="#0f172a"/>
                        <circle cx="18" cy="15" r="7" fill="#6366f1"/>
                        <path d="M14.5 15 l3 3 l5 -6" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <text x="74" y="19.5" text-anchor="middle" fill="#fff" font-size="12" font-family="Space Grotesk, sans-serif" font-weight="700">≈ 180–240 €</text>
                    </g>
                    {{-- bouton déclencheur --}}
                    <circle cx="120" cy="187" r="9" fill="#fff" stroke="#6366f1" stroke-width="3"/>
                </svg>
                <p class="cx-scene__cap"><b>Un devis IA depuis une photo</b><span>Une fourchette de prix en 10 secondes, sans appel commercial.</span></p>
            </div>

            {{-- ===== Scène — Chantier groupé multi-métiers ===== --}}
            <div class="cx-scene">
                <svg class="cx-art" viewBox="0 0 240 240" role="img">
                    {{-- fil d'orchestration --}}
                    <path d="M40 34 V150" stroke="#cbd5e1" stroke-width="2" stroke-dasharray="3 6"/>
                    @php
                        $bundle = [
                            ['1', 'Plombier', '#6366f1'],
                            ['2', 'Carreleur', '#f59e0b'],
                            ['3', 'Peintre', '#9333ea'],
                            ['4', 'Électricien', '#eab308'],
                        ];
                    @endphp
                    @foreach ($bundle as $i => $row)
                        <g class="cx-step-row cx-step-row--{{ $i }}" transform="translate(28,{{ 24 + $i * 32 }})">
                            <circle cx="12" cy="12" r="12" fill="{{ $row[2] }}"/>
                            <text x="12" y="16" text-anchor="middle" fill="#fff" font-size="12" font-family="Space Grotesk, sans-serif" font-weight="700">{{ $row[0] }}</text>
                            <rect x="34" y="2" width="150" height="20" rx="10" fill="#f1f5f9" stroke="#e2e8f0"/>
                            <text x="46" y="16" fill="#0f172a" font-size="11" font-family="Space Grotesk, sans-serif" font-weight="600">{{ $row[1] }}</text>
                        </g>
                    @endforeach
                    {{-- badge total groupé (pop) --}}
                    <g class="cx-price-pop" transform="translate(40,168)">
                        <rect width="160" height="30" rx="15" fill="#ecfdf5" stroke="#10b981" stroke-width="1.5"/>
                        <text x="14" y="19.5" fill="#047857" font-size="11" font-family="Space Grotesk, sans-serif" font-weight="600">1 facture · groupage</text>
                        <text x="148" y="19.5" text-anchor="end" fill="#047857" font-size="13" font-family="Space Grotesk, sans-serif" font-weight="700">−8 %</text>
                    </g>
                </svg>
                <p class="cx-scene__cap"><b>Plusieurs métiers, un seul chantier</b><span>Orchestrés dans le bon ordre, prix groupé, facture consolidée.</span></p>
            </div>

            {{-- ===== Scène 2 — Tracking en temps réel ===== --}}
            <div class="cx-scene">
                <svg class="cx-art" viewBox="0 0 240 240" role="img">
                    <defs><clipPath id="jScreen"><rect x="78" y="40" width="84" height="160" rx="9"/></clipPath></defs>
                    <rect x="70" y="24" width="100" height="192" rx="18" fill="#0f172a"/>
                    <g clip-path="url(#jScreen)">
                        <rect x="78" y="40" width="84" height="160" fill="#e8edf3"/>
                        <path d="M70 96 H170 M70 158 H170 M104 40 V200 M140 40 V200" stroke="#cdd7e3" stroke-width="7" fill="none"/>
                        <path d="M96 186 V150 H140 V92" fill="none" stroke="#6366f1" stroke-width="4" stroke-linecap="round" stroke-dasharray="2 7"/>
                        <g transform="translate(140,86)">
                            <path d="M0 0 c-9 -11 -9 -18 0 -18 c9 0 9 7 0 18 Z" fill="#10b981"/>
                            <circle cx="0" cy="-11" r="3.4" fill="#fff"/>
                        </g>
                        <g class="cx-track-marker">
                            <circle class="cx-track-pulse" r="9" fill="#ffb648"/>
                            <circle r="8" fill="#ffb648" stroke="#fff" stroke-width="3"/>
                        </g>
                    </g>
                    <g transform="translate(92,48)">
                        <rect width="60" height="19" rx="9.5" fill="#0f172a" opacity=".85"/>
                        <text x="30" y="13.5" text-anchor="middle" fill="#fff" font-size="10" font-family="Space Grotesk, sans-serif" font-weight="600">8 min</text>
                    </g>
                </svg>
                <p class="cx-scene__cap"><b>Suivez-le en temps réel</b><span>Sa position et son ETA, en direct sur la carte.</span></p>
            </div>

            {{-- ===== Scène 3 — Arrivée + scan QR (départ) ===== --}}
            <div class="cx-scene">
                <svg class="cx-art" viewBox="0 0 240 240" role="img">
                    <path d="M150 118 l36 -28 l36 28 v58 h-72 Z" fill="#e2e8f0"/>
                    <rect x="172" y="150" width="16" height="26" rx="2" fill="#94a3b8"/>
                    <g transform="translate(34,92)">
                        <circle cx="22" cy="0" r="15" fill="#f3c6a0"/>
                        <rect x="2" y="17" width="40" height="50" rx="15" fill="#6366f1"/>
                        <rect x="6" y="64" width="13" height="36" rx="6.5" fill="#334155"/>
                        <rect x="25" y="64" width="13" height="36" rx="6.5" fill="#334155"/>
                    </g>
                    <g transform="translate(96,118)">
                        <rect x="-4" y="-4" width="56" height="56" rx="10" fill="#fff" stroke="#0f172a" stroke-width="2.5"/>
                        <g fill="#0f172a">
                            <rect x="2" y="2" width="14" height="14" rx="2"/><rect x="5" y="5" width="8" height="8" fill="#fff"/><rect x="7" y="7" width="4" height="4" fill="#0f172a"/>
                            <rect x="32" y="2" width="14" height="14" rx="2"/><rect x="35" y="5" width="8" height="8" fill="#fff"/><rect x="37" y="7" width="4" height="4" fill="#0f172a"/>
                            <rect x="2" y="32" width="14" height="14" rx="2"/><rect x="5" y="35" width="8" height="8" fill="#fff"/><rect x="7" y="37" width="4" height="4" fill="#0f172a"/>
                            <rect x="22" y="4" width="5" height="5"/><rect x="22" y="14" width="5" height="5"/><rect x="32" y="24" width="5" height="5"/><rect x="42" y="24" width="4" height="5"/><rect x="22" y="32" width="5" height="5"/><rect x="22" y="42" width="5" height="5"/><rect x="32" y="42" width="5" height="5"/><rect x="42" y="42" width="4" height="5"/>
                        </g>
                        <rect class="cx-scanline" x="-2" y="0" width="52" height="4" rx="2" fill="#10b981" opacity=".9"/>
                    </g>
                    <g transform="translate(150,40)">
                        <rect width="74" height="22" rx="11" fill="#ecfdf5" stroke="#10b981" stroke-width="1.5"/>
                        <path class="cx-check-draw" d="M10 11 l5 5 l9 -10" fill="none" stroke="#10b981" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                        <text x="46" y="15" text-anchor="middle" fill="#047857" font-size="9" font-family="Space Grotesk, sans-serif" font-weight="600">Démarrée</text>
                    </g>
                </svg>
                <p class="cx-scene__cap"><b>Il arrive et scanne le QR</b><span>La mission démarre, horodatée et sécurisée.</span></p>
            </div>

            {{-- ===== Scène 4 — Le travail ===== --}}
            <div class="cx-scene">
                <svg class="cx-art" viewBox="0 0 240 240" role="img">
                    <rect x="48" y="176" width="144" height="10" rx="5" fill="#e2e8f0"/>
                    <g transform="translate(86,70)">
                        <circle cx="34" cy="0" r="16" fill="#f3c6a0"/>
                        <rect x="12" y="18" width="44" height="56" rx="16" fill="#10b981"/>
                        <rect x="16" y="70" width="14" height="40" rx="7" fill="#334155"/>
                        <rect x="38" y="70" width="14" height="40" rx="7" fill="#334155"/>
                        {{-- bras + outil --}}
                        <g class="cx-tool">
                            <rect x="50" y="26" width="10" height="34" rx="5" fill="#f3c6a0"/>
                            <rect x="50" y="-2" width="6" height="34" rx="3" fill="#64748b" transform="rotate(18 53 30)"/>
                            <rect x="42" y="-10" width="26" height="12" rx="3" fill="#ffb648" transform="rotate(18 55 -4)"/>
                        </g>
                    </g>
                    <g fill="#ffb648">
                        <path class="cx-spark"     d="M168 60 l3 8 l8 3 l-8 3 l-3 8 l-3 -8 l-8 -3 l8 -3 Z"/>
                        <path class="cx-spark cx-spark--2" d="M58 92 l2.5 6 l6 2.5 l-6 2.5 l-2.5 6 l-2.5 -6 l-6 -2.5 l6 -2.5 Z"/>
                        <path class="cx-spark cx-spark--3" d="M176 120 l2 5 l5 2 l-5 2 l-2 5 l-2 -5 l-5 -2 l5 -2 Z"/>
                    </g>
                </svg>
                <p class="cx-scene__cap"><b>Le travail est fait, avec soin</b><span>Par un pro vérifié KYC et assuré RC pro.</span></p>
            </div>

            {{-- ===== Scène 5 — Rescan QR (fin) ===== --}}
            <div class="cx-scene">
                <svg class="cx-art" viewBox="0 0 240 240" role="img">
                    {{-- téléphone client --}}
                    <rect x="40" y="60" width="78" height="120" rx="14" fill="#0f172a"/>
                    <rect x="47" y="72" width="64" height="96" rx="8" fill="#fff"/>
                    <g transform="translate(58,86)" fill="#0f172a">
                        <rect x="0" y="0" width="13" height="13" rx="2"/><rect x="3" y="3" width="7" height="7" fill="#fff"/>
                        <rect x="29" y="0" width="13" height="13" rx="2"/><rect x="32" y="3" width="7" height="7" fill="#fff"/>
                        <rect x="0" y="29" width="13" height="13" rx="2"/><rect x="3" y="32" width="7" height="7" fill="#fff"/>
                        <rect x="19" y="3" width="5" height="5"/><rect x="19" y="13" width="5" height="5"/><rect x="29" y="22" width="5" height="5"/><rect x="19" y="29" width="5" height="5"/><rect x="29" y="38" width="5" height="5"/><rect x="38" y="29" width="4" height="5"/>
                    </g>
                    {{-- téléphone provider qui scanne --}}
                    <g transform="translate(150,84)">
                        <rect x="0" y="0" width="56" height="100" rx="12" fill="#6366f1"/>
                        <rect x="6" y="10" width="44" height="80" rx="7" fill="#312e81"/>
                        <rect class="cx-scanline" x="6" y="14" width="44" height="4" rx="2" fill="#34d399"/>
                    </g>
                    {{-- check final --}}
                    <g transform="translate(96,30)">
                        <circle cx="22" cy="22" r="22" fill="#ecfdf5" stroke="#10b981" stroke-width="2"/>
                        <path class="cx-check-draw" d="M12 23 l7 7 l13 -15" fill="none" stroke="#10b981" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                    </g>
                </svg>
                <p class="cx-scene__cap"><b>Il rescanne votre QR</b><span>Mission terminée et validée — preuve à l'appui.</span></p>
            </div>

            {{-- ===== Scène 6 — Poignée de main + paiement ===== --}}
            <div class="cx-scene">
                <svg class="cx-art" viewBox="0 0 240 240" role="img">
                    <g class="cx-shake">
                        {{-- manche gauche (provider, indigo) --}}
                        <rect x="20" y="118" width="74" height="26" rx="13" fill="#6366f1"/>
                        {{-- manche droite (client, amber) --}}
                        <rect x="146" y="118" width="74" height="26" rx="13" fill="#ffb648"/>
                        {{-- mains qui se serrent --}}
                        <path d="M92 110 q20 -10 38 2 q14 9 6 24 q-8 13 -26 12 q-22 -1 -28 -16 q-4 -14 10 -22 Z" fill="#f3c6a0"/>
                        <path d="M118 132 q-18 8 -34 -2" fill="none" stroke="#e0a87f" stroke-width="3" stroke-linecap="round"/>
                    </g>
                    {{-- confettis --}}
                    <g>
                        <rect class="cx-confetti" x="64" y="74" width="7" height="7" rx="1.5" fill="#6366f1"/>
                        <rect class="cx-confetti cx-confetti--2" x="120" y="62" width="7" height="7" rx="1.5" fill="#ffb648"/>
                        <rect class="cx-confetti cx-confetti--3" x="170" y="78" width="7" height="7" rx="1.5" fill="#10b981"/>
                        <circle class="cx-confetti cx-confetti--2" cx="96" cy="66" r="4" fill="#9333ea"/>
                        <circle class="cx-confetti cx-confetti--3" cx="150" cy="70" r="4" fill="#3b82f6"/>
                    </g>
                    {{-- badge paiement libéré --}}
                    <g transform="translate(70,176)">
                        <rect width="100" height="26" rx="13" fill="#ecfdf5" stroke="#10b981" stroke-width="1.5"/>
                        <path d="M16 13 l5 5 l9 -10" fill="none" stroke="#10b981" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                        <text x="58" y="17" text-anchor="middle" fill="#047857" font-size="10" font-family="Space Grotesk, sans-serif" font-weight="600">Paiement libéré</text>
                    </g>
                </svg>
                <p class="cx-scene__cap"><b>Tout le monde repart gagnant</b><span>Satisfaction confirmée, paiement sécurisé libéré.</span></p>
            </div>

        </div>

        <div class="cx-journey__bar" aria-hidden="true"><i></i></div>
        <div class="cx-journey__dots" aria-hidden="true">
            <span class="cx-dot is-active"></span><span class="cx-dot"></span><span class="cx-dot"></span><span class="cx-dot"></span><span class="cx-dot"></span><span class="cx-dot"></span><span class="cx-dot"></span><span class="cx-dot"></span><span class="cx-dot"></span>
        </div>
    </div>
</section>
