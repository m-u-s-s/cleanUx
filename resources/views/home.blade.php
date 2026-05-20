<x-guest-layout>

    {{--
      ============================================================
      CleanUx — Home + SCÈNE CINÉMATIQUE MANGA (scroll-scrubbée)
      Personnages 100% originaux (style manga = genre, pas une IP).
      Scroll natif. 6 temps narratifs. Sans JS / reduced-motion =>
      planche de BD en 6 cases (fallback thématique).
      ============================================================
    --}}

    <style>
        .cx-scene { position:relative; z-index:1; }
        .cx-scene__track { position:relative; }
        .cx-scene__pin { position:sticky; top:0; height:100svh; display:flex; align-items:center; overflow:hidden; }
        .cx-track--hero  { height:200vh; }
        .cx-track--steps { height:460vh; }
        .cx-track--story { height:680vh; }   /* 6 temps ≈ 113vh chacun */
        .cx-track--outro { height:170vh; }
        @media (max-width:900px){
            .cx-track--hero{height:150vh;} .cx-track--steps{height:360vh;}
            .cx-track--story{height:560vh;} .cx-track--outro{height:130vh;}
        }

        /* Fallback : on dépile en planche BD */
        .cx-static .cx-scene__track{ height:auto !important; }
        .cx-static .cx-scene__pin{ position:static; height:auto; display:block; padding:4rem 0; }
        .cx-static [data-cx-layer], .cx-static [data-beat]{
            position:relative !important; opacity:1 !important; transform:none !important;
            margin:0 auto 2rem; max-width:680px;
        }
        .cx-static .cx-stage-cam{ transform:none !important; }
        @media (prefers-reduced-motion: reduce){
            .cx-scene__pin{ position:static; height:auto; display:block; padding:4rem 0; }
            .cx-scene__track{ height:auto !important; }
            [data-cx-layer],[data-beat]{ position:relative !important; opacity:1 !important; transform:none !important; margin:0 auto 2rem; max-width:680px; }
            .cx-stage-cam{ transform:none !important; }
            .cx-anim{ animation:none !important; }
        }

        .cx-kicker{ font-family:var(--cx-display); font-size:12px; font-weight:700; letter-spacing:.34em; text-transform:uppercase; color:var(--cx-amber); }
        .cx-h{ font-family:var(--cx-display); font-weight:700; line-height:1.02; letter-spacing:-.025em; color:var(--cx-text); }
        .cx-lede{ color:var(--cx-muted); line-height:1.7; }
        .cx-gradient-text{ background:linear-gradient(115deg,var(--cx-cyan),var(--cx-amber) 55%,var(--cx-violet)); -webkit-background-clip:text; background-clip:text; color:transparent; }
        .cx-chip{ display:inline-flex; align-items:center; gap:.55rem; padding:.5rem 1.05rem; border-radius:999px; border:1px solid var(--cx-line); background:rgba(255,255,255,.04); font-size:11px; font-weight:700; letter-spacing:.2em; text-transform:uppercase; color:var(--cx-muted); }
        .cx-chip .pip{ height:7px;width:7px;border-radius:50%;background:var(--cx-cyan);box-shadow:0 0 10px var(--cx-glow-cyan);}
        .cx-card{ position:relative; border:1px solid var(--cx-line); background:linear-gradient(160deg,rgba(255,255,255,.05),rgba(255,255,255,.015)); border-radius:26px; backdrop-filter:blur(10px); transition:transform .4s cubic-bezier(.16,1,.3,1),border-color .3s,box-shadow .4s; }
        .cx-card:hover{ transform:translateY(-6px); border-color:rgba(255,182,72,.4); box-shadow:0 24px 60px -22px var(--cx-glow-amber); }
        [data-cx-layer]{ position:absolute; inset:0; display:flex; align-items:center; will-change:opacity,transform; }
        .cx-aura{ position:absolute; left:50%; top:50%; height:62vmin; width:62vmin; border-radius:50%; transform:translate(-50%,-50%) scale(var(--cx-aura,1)); background:radial-gradient(circle,var(--cx-glow-amber),transparent 62%); filter:blur(38px); opacity:.5; pointer-events:none; }
        .cx-stage{ position:relative; height:60vmin; max-height:520px; width:100%; display:grid; place-items:center; }
        .cx-stage__ring{ position:absolute; inset:0; margin:auto; height:46vmin; width:46vmin; max-height:420px; max-width:420px; border-radius:50%; border:1px solid var(--cx-line); }
        .cx-stage__ring::after{ content:""; position:absolute; inset:-1px; border-radius:50%; border:1px solid transparent; border-top-color:var(--trade,var(--cx-amber)); transform:rotate(var(--cx-ring-rot,0deg)); transition:border-top-color .5s; }
        .cx-glyph{ font-size:clamp(4rem,14vmin,9rem); filter:drop-shadow(0 18px 40px rgba(0,0,0,.55)); }
        .cx-trade-tag{ position:absolute; bottom:8%; left:50%; transform:translateX(-50%); font-family:var(--cx-display); font-weight:700; letter-spacing:.3em; text-transform:uppercase; font-size:13px; color:var(--cx-muted); }
        .cx-steprail{ display:flex; flex-direction:column; gap:1.1rem; }
        .cx-steprail span.cx-step{ display:flex; align-items:center; gap:.9rem; color:var(--cx-muted); font-family:var(--cx-display); font-weight:600; font-size:14px; }
        .cx-step__bullet{ position:relative; height:10px; width:10px; border-radius:50%; background:rgba(255,255,255,.16); flex:none; }
        .cx-step__bullet::after{ content:""; position:absolute; inset:-5px; border-radius:50%; border:1px solid transparent; }
        .cx-step.is-on{ color:var(--cx-text); }
        .cx-step.is-on .cx-step__bullet{ background:var(--cx-amber); box-shadow:0 0 14px var(--cx-glow-amber); }
        .cx-step.is-on .cx-step__bullet::after{ border-color:rgba(255,182,72,.45); }
        .cx-marquee{ display:flex; gap:3rem; white-space:nowrap; animation:cx-march 26s linear infinite; font-family:var(--cx-display); font-weight:700; font-size:clamp(2rem,6vw,4.5rem); letter-spacing:-.02em; color:transparent; -webkit-text-stroke:1px rgba(148,178,230,.22); }
        @keyframes cx-march{ to{ transform:translateX(-50%);} }
        .cx-scrollhint{ display:flex; flex-direction:column; align-items:center; gap:.6rem; color:var(--cx-muted); }
        .cx-scrollhint i{ display:block; height:34px; width:20px; border-radius:12px; border:1px solid var(--cx-line); position:relative; }
        .cx-scrollhint i::after{ content:""; position:absolute; left:50%; top:7px; height:6px; width:2px; background:var(--cx-amber); border-radius:2px; transform:translateX(-50%); animation:cx-wheel 1.8s ease-in-out infinite; }
        @keyframes cx-wheel{ 50%{ transform:translate(-50%,10px); opacity:.3;} }

        /* ---------- SCÈNE MANGA ---------- */
        .cx-cinema{ position:relative; width:100%; max-width:1100px; margin:0 auto; aspect-ratio:16/10; }
        .cx-stage-cam{ position:absolute; inset:0; transform-origin:50% 46%; will-change:transform; }
        .cx-beat{ position:absolute; inset:0; display:grid; place-items:center; will-change:opacity,transform; }
        .cx-panel{ position:absolute; inset:0; border-radius:26px; overflow:hidden; border:1px solid var(--cx-line); background:radial-gradient(120% 120% at 50% 0%,#16213b,#0a1120 70%); box-shadow:0 50px 120px -40px rgba(0,0,0,.85); }
        .cx-svg{ width:100%; height:100%; display:block; }
        .cx-beat-cap{ position:absolute; left:0; right:0; bottom:5%; text-align:center; font-family:var(--cx-display); font-weight:700; }
        .cx-beat-cap b{ display:inline-block; padding:.5rem 1.1rem; border-radius:999px; background:rgba(7,11,20,.7); border:1px solid var(--cx-line); color:var(--cx-text); font-size:14px; }
        .cx-beat-counter{ position:absolute; top:5%; left:6%; font-family:var(--cx-display); font-weight:700; font-size:13px; letter-spacing:.3em; color:var(--cx-amber); }

        /* boucles d'inactivité (idle) — coupées en reduced-motion via .cx-anim */
        @keyframes cx-blink{ 0%,92%,100%{ transform:scaleY(1);} 96%{ transform:scaleY(.1);} }
        @keyframes cx-bob{ 50%{ transform:translateY(-6px);} }
        @keyframes cx-speed{ to{ transform:translateX(-40px); opacity:0;} }
        @keyframes cx-spark{ 0%,100%{ transform:scale(.6); opacity:.3;} 50%{ transform:scale(1.1); opacity:1;} }
        @keyframes cx-scan{ 0%,100%{ transform:translateY(-46%);} 50%{ transform:translateY(46%);} }
        .cx-eye-l,.cx-eye-r{ transform-box:fill-box; transform-origin:center; animation:cx-blink 4.6s infinite; }
        .cx-bobber{ animation:cx-bob 3.2s ease-in-out infinite; }
        .cx-sparker{ transform-box:fill-box; transform-origin:center; animation:cx-spark 1.6s ease-in-out infinite; }
        .cx-scanline{ animation:cx-scan 2.4s ease-in-out infinite; }
        @media (prefers-reduced-motion: reduce){
            .cx-eye-l,.cx-eye-r,.cx-bobber,.cx-sparker,.cx-scanline,.cx-marquee,.cx-scrollhint i::after{ animation:none !important; }
        }
    </style>

    {{-- ===== Bibliothèque de personnages manga (symbols réutilisés) ===== --}}
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
        <defs>
            <linearGradient id="cxJacket" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#ffb648"/><stop offset="1" stop-color="#ff8a3d"/>
            </linearGradient>
            <linearGradient id="cxTop" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#4fe3d6"/><stop offset="1" stop-color="#3bb8c9"/>
            </linearGradient>
            <pattern id="cxTone" width="10" height="10" patternUnits="userSpaceOnUse">
                <circle cx="2" cy="2" r="1.4" fill="rgba(255,255,255,.08)"/>
            </pattern>

            {{-- CLIENTE : carré bob, gros yeux manga, blush --}}
            <symbol id="cxClient" viewBox="0 0 220 360">
                <ellipse cx="110" cy="346" rx="78" ry="14" fill="#000" opacity=".32"/>
                <rect x="74" y="214" width="72" height="96" rx="20" fill="#2c3f60"/>
                <rect x="70" y="150" width="80" height="86" rx="26" fill="url(#cxTop)"/>
                <rect x="70" y="150" width="80" height="86" rx="26" fill="url(#cxTone)"/>
                <rect x="58" y="158" width="22" height="74" rx="11" fill="#ffe0c0"/>
                <rect x="140" y="158" width="22" height="74" rx="11" fill="#ffe0c0"/>
                <circle cx="110" cy="104" r="50" fill="#ffe6cf"/>
                {{-- cheveux : mèches pointues --}}
                <path d="M58 104c0-40 28-66 52-66s52 26 52 66c0 8-3 16-3 16l-10-26-8 24-9-26-10 26-9-24-9 26-10-24-8 22s-11-12-11-34z" fill="#3a2c4f"/>
                <path d="M150 78l12 38-8 4z" fill="#5a4780"/>
                {{-- yeux manga --}}
                <g class="cx-eye-l"><ellipse cx="92" cy="108" rx="11" ry="14" fill="#fff"/><circle cx="93" cy="110" r="7.5" fill="#6a4bd6"/><circle cx="90" cy="106" r="3" fill="#fff"/><path d="M81 96q11-7 22 0" stroke="#241a33" stroke-width="3" fill="none" stroke-linecap="round"/></g>
                <g class="cx-eye-r"><ellipse cx="128" cy="108" rx="11" ry="14" fill="#fff"/><circle cx="129" cy="110" r="7.5" fill="#6a4bd6"/><circle cx="126" cy="106" r="3" fill="#fff"/><path d="M117 96q11-7 22 0" stroke="#241a33" stroke-width="3" fill="none" stroke-linecap="round"/></g>
                <ellipse cx="80" cy="124" rx="8" ry="4" fill="#ff9a9a" opacity=".6"/>
                <ellipse cx="140" cy="124" rx="8" ry="4" fill="#ff9a9a" opacity=".6"/>
                <path d="M101 126q9 8 18 0" stroke="#241a33" stroke-width="3" fill="none" stroke-linecap="round"/>
            </symbol>

            {{-- INTERVENANT : casquette CleanUx, veste ambre, sourire confiant --}}
            <symbol id="cxWorker" viewBox="0 0 220 372">
                <ellipse cx="110" cy="358" rx="80" ry="14" fill="#000" opacity=".32"/>
                <rect x="76" y="226" width="68" height="104" rx="18" fill="#1d2c47"/>
                <rect x="66" y="156" width="88" height="92" rx="24" fill="url(#cxJacket)"/>
                <rect x="66" y="156" width="88" height="92" rx="24" fill="url(#cxTone)"/>
                <path d="M110 156v92" stroke="#cf6a1f" stroke-width="3" opacity=".5"/>
                <rect x="52" y="164" width="22" height="78" rx="11" fill="#ffd9a8"/>
                <rect x="146" y="164" width="22" height="78" rx="11" fill="#ffd9a8"/>
                <circle cx="110" cy="106" r="48" fill="#ffdcb6"/>
                <path d="M70 96l8-20 14 14 16-18 16 18 14-14 8 20z" fill="#2a2036"/>
                {{-- casquette --}}
                <path d="M60 92a50 50 0 0 1 100 0z" fill="#0f1c34"/>
                <path d="M60 92h54l-4 18H56z" fill="#0b1426"/>
                <text x="110" y="80" font-family="Space Grotesk, sans-serif" font-size="20" font-weight="800" fill="#ffb648" text-anchor="middle">Cx</text>
                <g class="cx-eye-l"><ellipse cx="94" cy="110" rx="9" ry="12" fill="#fff"/><circle cx="95" cy="112" r="6" fill="#2b3a5e"/><circle cx="92" cy="108" r="2.4" fill="#fff"/><path d="M84 100q10-5 20 0" stroke="#241a33" stroke-width="3" fill="none" stroke-linecap="round"/></g>
                <g class="cx-eye-r"><ellipse cx="126" cy="110" rx="9" ry="12" fill="#fff"/><circle cx="127" cy="112" r="6" fill="#2b3a5e"/><circle cx="124" cy="108" r="2.4" fill="#fff"/><path d="M116 100q10-5 20 0" stroke="#241a33" stroke-width="3" fill="none" stroke-linecap="round"/></g>
                <path d="M98 128q12 12 26 0" stroke="#241a33" stroke-width="3" fill="none" stroke-linecap="round"/>
            </symbol>

            {{-- Téléphone --}}
            <symbol id="cxPhone" viewBox="0 0 240 460">
                <rect x="14" y="6" width="212" height="448" rx="40" fill="#0b1224" stroke="#2b3a5e" stroke-width="3"/>
                <rect x="30" y="40" width="180" height="380" rx="14" fill="#0a1322"/>
                <rect x="92" y="20" width="56" height="10" rx="5" fill="#2b3a5e"/>
            </symbol>

            {{-- QR stylisé --}}
            <symbol id="cxQR" viewBox="0 0 120 120">
                <rect width="120" height="120" rx="10" fill="#fff"/>
                <rect x="12" y="12" width="30" height="30" fill="#0b1224"/><rect x="20" y="20" width="14" height="14" fill="#fff"/>
                <rect x="78" y="12" width="30" height="30" fill="#0b1224"/><rect x="86" y="20" width="14" height="14" fill="#fff"/>
                <rect x="12" y="78" width="30" height="30" fill="#0b1224"/><rect x="20" y="86" width="14" height="14" fill="#fff"/>
                <g fill="#0b1224">
                    <rect x="54" y="12" width="8" height="8"/><rect x="66" y="24" width="8" height="8"/><rect x="54" y="36" width="8" height="8"/>
                    <rect x="78" y="54" width="8" height="8"/><rect x="96" y="54" width="8" height="8"/><rect x="66" y="66" width="8" height="8"/>
                    <rect x="54" y="78" width="8" height="8"/><rect x="78" y="84" width="8" height="8"/><rect x="96" y="96" width="8" height="8"/>
                    <rect x="54" y="96" width="8" height="8"/><rect x="66" y="54" width="8" height="8"/>
                </g>
            </symbol>

            <symbol id="cxSpark" viewBox="0 0 40 40">
                <path d="M20 2l4 14 14 4-14 4-4 14-4-14-14-4 14-4z" fill="#4fe3d6"/>
            </symbol>
        </defs>
    </svg>

    <main>

        {{-- ═══════════ HERO ═══════════ --}}
        <section class="cx-scene" data-cx-scene="hero">
            <div class="cx-scene__track cx-track--hero">
                <div class="cx-scene__pin">
                    <div class="cx-aura" data-cx-aura aria-hidden="true"></div>
                    <div data-cx-layer data-cx-role="in">
                        <div class="mx-auto max-w-5xl px-4 text-center sm:px-6 lg:px-8">
                            <span class="cx-chip"><span class="pip"></span> Plateforme de services à domicile</span>
                            <h1 class="cx-h mt-8 text-5xl sm:text-7xl lg:text-8xl">Un service.<br><span class="cx-gradient-text">Une histoire.</span></h1>
                            <p class="cx-lede mx-auto mt-7 max-w-xl text-lg">Nettoyage, peinture, bâtiment, jardinage. Faites défiler — suivez la mission de bout en bout.</p>
                            <div class="mt-10 cx-scrollhint"><i aria-hidden="true"></i><span class="text-xs uppercase tracking-[0.25em]">Défilez</span></div>
                        </div>
                    </div>
                    <div data-cx-layer data-cx-role="out" style="opacity:0">
                        <div class="mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
                            <h2 class="cx-h text-4xl sm:text-6xl">Du choix du service<br><span class="cx-gradient-text">à la poignée de main.</span></h2>
                            <a href="{{ route('booking.create') }}" class="cx-btn cx-btn--primary mt-9 px-8 py-4 text-base">Réserver une prestation →</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="relative z-[1] overflow-hidden border-y py-7" style="border-color:var(--cx-line)" aria-hidden="true">
            <div class="cx-marquee">
                <span>NETTOYAGE • PEINTURE • BÂTIMENT • JARDINAGE • NETTOYAGE • PEINTURE • BÂTIMENT • JARDINAGE •&nbsp;</span>
                <span>NETTOYAGE • PEINTURE • BÂTIMENT • JARDINAGE • NETTOYAGE • PEINTURE • BÂTIMENT • JARDINAGE •&nbsp;</span>
            </div>
        </div>

        {{-- ═══════════ MÉTIERS (morphing) ═══════════ --}}
        @php
            $metiers = [
                ['emoji'=>'🧼','name'=>'Nettoyage','color'=>'#4fe3d6','title'=>'Des espaces impeccables, sur preuve.','desc'=>'Standard, profond, fin de chantier ou bureaux. Ponctuel ou récurrent.'],
                ['emoji'=>'🎨','name'=>'Peinture','color'=>'#ffb648','title'=>'Des finitions nettes, sans surprise.','desc'=>'Intérieure, façade, plafonds, retouches. Devis estimé en amont.'],
                ['emoji'=>'🏗️','name'=>'Bâtiment','color'=>'#8b7bff','title'=>'Petits travaux, grand suivi.','desc'=>'Rénovation, dépannage, second œuvre. Chaque étape tracée.'],
                ['emoji'=>'🌱','name'=>'Jardinage','color'=>'#5fd38a','title'=>'Extérieurs entretenus, à la séance.','desc'=>'Tonte, taille, entretien, aménagement. Suivi en temps réel.'],
            ];
        @endphp
        <section class="cx-scene" data-cx-scene="steps" data-cx-count="{{ count($metiers) }}">
            <div class="cx-scene__track cx-track--steps">
                <div class="cx-scene__pin">
                    <div class="mx-auto grid w-full max-w-7xl items-center gap-10 px-4 sm:px-6 lg:grid-cols-12 lg:px-8">
                        <div class="lg:col-span-5">
                            <p class="cx-kicker">Les métiers</p>
                            <div class="cx-steprail mt-6">
                                @foreach ($metiers as $i => $m)
                                    <span class="cx-step {{ $i === 0 ? 'is-on' : '' }}" data-cx-step="{{ $i }}"><span class="cx-step__bullet"></span>{{ $m['name'] }}</span>
                                @endforeach
                            </div>
                            <div class="relative mt-9 h-[200px]">
                                @foreach ($metiers as $i => $m)
                                    <div class="absolute inset-0" data-cx-copy="{{ $i }}" style="opacity:{{ $i === 0 ? 1 : 0 }};transition:opacity .35s ease">
                                        <h2 class="cx-h text-3xl sm:text-4xl">{{ $m['title'] }}</h2>
                                        <p class="cx-lede mt-4 max-w-md text-base">{{ $m['desc'] }}</p>
                                    </div>
                                @endforeach
                            </div>
                            <a href="{{ route('booking.create') }}" class="cx-btn cx-btn--primary mt-8 px-7 py-3.5 text-base">Réserver ce service →</a>
                        </div>
                        <div class="lg:col-span-7">
                            <div class="cx-stage">
                                <div class="cx-stage__ring" style="--trade:{{ $metiers[0]['color'] }}"></div>
                                @foreach ($metiers as $i => $m)
                                    <div class="absolute inset-0 grid place-items-center" data-cx-art="{{ $i }}" style="opacity:{{ $i === 0 ? 1 : 0 }};transform:scale({{ $i === 0 ? 1 : 0.86 }});transition:opacity .4s ease, transform .4s ease">
                                        <div class="cx-glyph">{{ $m['emoji'] }}</div>
                                    </div>
                                @endforeach
                                <span class="cx-trade-tag" data-cx-tradetag>{{ $metiers[0]['name'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ═══════════ SCÈNE CINÉMATIQUE MANGA (6 temps) ═══════════ --}}
        <section class="cx-scene" data-cx-scene="story" id="fonctionnement">
            <div class="cx-scene__track cx-track--story">
                <div class="cx-scene__pin">
                    <div class="w-full px-4 sm:px-6 lg:px-8">
                        <div class="mx-auto mb-7 max-w-5xl text-center">
                            <p class="cx-kicker">L’histoire d’une mission</p>
                            <h2 class="cx-h mt-3 text-3xl sm:text-4xl">Du tap à la <span class="cx-gradient-text">poignée de main.</span></h2>
                        </div>

                        <div class="cx-cinema cx-anim">
                            <div class="cx-stage-cam" data-cx-cam>
                                <div class="cx-panel"></div>
                                <div class="cx-beat-counter" data-cx-counter>01 / 06</div>

                                {{-- ── TEMPS 1 : la cliente choisit un service ── --}}
                                <div class="cx-beat" data-beat="0" style="opacity:1">
                                    <svg class="cx-svg" viewBox="0 0 1000 620" xmlns="http://www.w3.org/2000/svg">
                                        <use href="#cxClient" x="80" y="180" width="280" height="440" class="cx-bobber"/>
                                        {{-- panneau de choix --}}
                                        <g transform="translate(440 120)">
                                            <rect width="430" height="380" rx="22" fill="#0e1830" stroke="#2b3a5e"/>
                                            <text x="30" y="56" font-family="Space Grotesk" font-size="26" font-weight="800" fill="#e8eefc">Choisir un service</text>
                                            <g>
                                                <rect x="28" y="86" width="170" height="120" rx="16" fill="#13203b" stroke="#2b3a5e"/>
                                                <text x="48" y="150" font-size="44">🧼</text>
                                                <text x="48" y="186" font-size="16" fill="#93a4c6" font-family="Figtree">Nettoyage</text>
                                                <rect x="232" y="86" width="170" height="120" rx="16" fill="#13203b" stroke="#2b3a5e"/>
                                                <text x="252" y="150" font-size="44">🎨</text>
                                                <text x="252" y="186" font-size="16" fill="#93a4c6" font-family="Figtree">Peinture</text>
                                                <rect x="28" y="222" width="170" height="120" rx="16" fill="#13203b" stroke="#2b3a5e"/>
                                                <text x="48" y="286" font-size="44">🏗️</text>
                                                <text x="48" y="322" font-size="16" fill="#93a4c6" font-family="Figtree">Bâtiment</text>
                                                {{-- carte choisie : surbrillance pilotée par le scroll --}}
                                                <rect data-cx-pick x="232" y="222" width="170" height="120" rx="16" fill="#13203b" stroke="#ffb648" stroke-width="1"/>
                                                <text x="252" y="286" font-size="44">🌱</text>
                                                <text x="252" y="322" font-size="16" fill="#93a4c6" font-family="Figtree">Jardinage</text>
                                            </g>
                                        </g>
                                        {{-- doigt qui vient taper --}}
                                        <g data-cx-finger transform="translate(720 470)">
                                            <circle r="22" fill="none" stroke="#4fe3d6" stroke-width="3" opacity=".7"/>
                                            <circle r="9" fill="#4fe3d6"/>
                                        </g>
                                    </svg>
                                </div>

                                {{-- ── TEMPS 2 : zoom sur le téléphone ── --}}
                                <div class="cx-beat" data-beat="1" style="opacity:0">
                                    <svg class="cx-svg" viewBox="0 0 1000 620" xmlns="http://www.w3.org/2000/svg">
                                        <use href="#cxClient" x="70" y="210" width="250" height="400"/>
                                        <g data-cx-phonezoom transform="translate(560 110)">
                                            <use href="#cxPhone" width="240" height="460"/>
                                            <g transform="translate(30 40)">
                                                <rect width="180" height="380" rx="14" fill="#0a1322"/>
                                                <text x="90" y="60" text-anchor="middle" font-family="Space Grotesk" font-size="16" font-weight="700" fill="#ffb648">Recherche…</text>
                                                <circle cx="90" cy="180" r="34" fill="none" stroke="#4fe3d6" stroke-width="5" stroke-dasharray="160 60" class="cx-sparker"/>
                                                <text x="90" y="320" text-anchor="middle" font-family="Figtree" font-size="13" fill="#93a4c6">Un intervenant arrive</text>
                                            </g>
                                        </g>
                                    </svg>
                                </div>

                                {{-- ── TEMPS 3 : trajectoire suivie sur l'écran ── --}}
                                <div class="cx-beat" data-beat="2" style="opacity:0">
                                    <svg class="cx-svg" viewBox="0 0 1000 620" xmlns="http://www.w3.org/2000/svg">
                                        <rect x="120" y="50" width="760" height="520" rx="26" fill="#0c1730"/>
                                        {{-- carte stylisée --}}
                                        <g stroke="#22324f" stroke-width="2" fill="none" opacity=".7">
                                            <path d="M120 200h760M120 360h760M340 50v520M620 50v520"/>
                                        </g>
                                        <rect x="150" y="90" width="120" height="90" rx="8" fill="#13203b"/>
                                        <rect x="700" y="400" width="140" height="120" rx="8" fill="#13203b"/>
                                        <path id="cxRoute" d="M210 470 C 320 460, 360 300, 480 300 S 660 180, 770 150"
                                              fill="none" stroke="#4fe3d6" stroke-width="6" stroke-linecap="round"
                                              data-cx-route stroke-dasharray="1" stroke-dashoffset="1"/>
                                        <g data-cx-home transform="translate(770 150)">
                                            <circle r="16" fill="#ffb648"/><circle r="7" fill="#1a1206"/>
                                        </g>
                                        <g data-cx-mover transform="translate(210 470)">
                                            <circle r="20" fill="#4fe3d6"/>
                                            <use href="#cxWorker" x="-22" y="-58" width="44" height="74"/>
                                        </g>
                                        <g transform="translate(150 510)">
                                            <rect width="220" height="56" rx="14" fill="rgba(7,11,20,.8)" stroke="#2b3a5e"/>
                                            <text x="20" y="26" font-family="Figtree" font-size="13" fill="#93a4c6">Arrivée estimée</text>
                                            <text data-cx-eta x="20" y="46" font-family="Space Grotesk" font-size="20" font-weight="800" fill="#ffb648">12 min</text>
                                        </g>
                                    </svg>
                                </div>

                                {{-- ── TEMPS 4 : il arrive, scanne le QR, commence ── --}}
                                <div class="cx-beat" data-beat="3" style="opacity:0">
                                    <svg class="cx-svg" viewBox="0 0 1000 620" xmlns="http://www.w3.org/2000/svg">
                                        <rect x="120" y="120" width="220" height="420" rx="10" fill="#13203b" stroke="#2b3a5e"/>
                                        <rect x="150" y="160" width="160" height="340" rx="6" fill="#0e1830"/>
                                        <use href="#cxClient" x="150" y="220" width="190" height="320"/>
                                        <use href="#cxWorker" x="470" y="190" width="220" height="360" class="cx-bobber"/>
                                        {{-- téléphone qui scanne --}}
                                        <g transform="translate(700 300)">
                                            <use href="#cxPhone" width="120" height="230"/>
                                            <g transform="translate(15 20)">
                                                <use href="#cxQR" x="22" y="40" width="46" height="46"/>
                                                <rect data-cx-scanbox x="10" y="20" width="70" height="3" fill="#4fe3d6" class="cx-scanline"/>
                                            </g>
                                        </g>
                                        <g data-cx-startfx opacity="0">
                                            <use href="#cxSpark" x="430" y="170" width="40" height="40" class="cx-sparker"/>
                                            <use href="#cxSpark" x="690" y="220" width="28" height="28" class="cx-sparker"/>
                                            <g stroke="#ffb648" stroke-width="4" stroke-linecap="round" opacity=".7">
                                                <path d="M420 470h120M430 500h100"/>
                                            </g>
                                        </g>
                                        <g transform="translate(420 90)">
                                            <rect width="240" height="50" rx="14" fill="rgba(255,182,72,.16)" stroke="#ffb648"/>
                                            <text x="120" y="32" text-anchor="middle" font-family="Space Grotesk" font-size="16" font-weight="800" fill="#ffb648">QR validé — début</text>
                                        </g>
                                    </svg>
                                </div>

                                {{-- ── TEMPS 5 : terminé, re-scan QR ── --}}
                                <div class="cx-beat" data-beat="4" style="opacity:0">
                                    <svg class="cx-svg" viewBox="0 0 1000 620" xmlns="http://www.w3.org/2000/svg">
                                        <rect x="120" y="120" width="760" height="420" rx="18" fill="#0e1830" stroke="#22324f"/>
                                        <use href="#cxWorker" x="170" y="190" width="220" height="360"/>
                                        <g transform="translate(470 230)">
                                            <use href="#cxPhone" width="150" height="290"/>
                                            <g transform="translate(20 28)">
                                                <use href="#cxQR" x="22" y="50" width="60" height="60"/>
                                                <rect data-cx-scanbox2 x="8" y="30" width="90" height="3" fill="#4fe3d6" class="cx-scanline"/>
                                            </g>
                                        </g>
                                        <g data-cx-stamp transform="translate(640 200)" opacity="0">
                                            <circle r="78" fill="none" stroke="#4fe3d6" stroke-width="5" stroke-dasharray="9 11"/>
                                            <circle r="56" fill="#0c1426" stroke="#4fe3d6"/>
                                            <path d="M-26 4l18 18 34-40" fill="none" stroke="#4fe3d6" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"/>
                                            <text y="100" text-anchor="middle" font-family="Space Grotesk" font-size="18" font-weight="800" fill="#4fe3d6">TERMINÉ</text>
                                        </g>
                                    </svg>
                                </div>

                                {{-- ── TEMPS 6 : la poignée de main ── --}}
                                <div class="cx-beat" data-beat="5" style="opacity:0">
                                    <svg class="cx-svg" viewBox="0 0 1000 620" xmlns="http://www.w3.org/2000/svg">
                                        <g data-cx-impact opacity="0">
                                            <g stroke="rgba(255,182,72,.5)" stroke-width="3">
                                                <path d="M500 310L380 150M500 310L620 150M500 310L330 300M500 310L670 300M500 310L400 470M500 310L600 470"/>
                                            </g>
                                        </g>
                                        <use href="#cxClient"  x="250" y="200" width="260" height="420"/>
                                        <use href="#cxWorker"  x="500" y="190" width="260" height="430"/>
                                        {{-- avant-bras qui se rejoignent --}}
                                        <g data-cx-handL><rect x="430" y="380" width="120" height="26" rx="13" fill="#ffe0c0" transform="rotate(14 430 380)"/></g>
                                        <g data-cx-handR><rect x="470" y="380" width="120" height="26" rx="13" fill="#ffd9a8" transform="rotate(-14 590 380)"/></g>
                                        <use href="#cxSpark" data-cx-shake x="486" y="372" width="40" height="40" class="cx-sparker"/>
                                        <g data-cx-bubble transform="translate(560 150)" opacity="0">
                                            <path d="M0 40a40 30 0 1 1 80 0a40 30 0 0 1 -40 30l-8 18-6-18a40 30 0 0 1 -26-30z" fill="#fff"/>
                                            <text x="40" y="46" text-anchor="middle" font-family="Space Grotesk" font-size="20" font-weight="800" fill="#1a1206">Merci !</text>
                                        </g>
                                    </svg>
                                </div>

                                <div class="cx-beat-cap"><b data-cx-beatcap>La cliente choisit un service</b></div>
                            </div>
                        </div>

                        <div class="mx-auto mt-8 max-w-md text-center">
                            <a href="{{ route('booking.create') }}" class="cx-btn cx-btn--primary px-7 py-3.5 text-base">Vivre cette histoire — réserver →</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ═══════════ ENTREPRISES (B2B) ═══════════ --}}
        <section id="b2b" class="relative z-[1] py-28">
            <div class="mx-auto grid max-w-7xl items-center gap-16 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
                <div data-cx-reveal>
                    <p class="cx-kicker">Entreprises</p>
                    <h2 class="cx-h mt-4 text-4xl sm:text-5xl">Pensé aussi pour<br><span class="cx-gradient-text">les pros multi-sites.</span></h2>
                    <p class="cx-lede mt-5 text-lg">Bureaux, sites multiples, validations internes, facturation groupée, centres de coûts et suivi opérationnel : la même plateforme passe à l’échelle.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        ['Multi-sites','Plusieurs adresses et responsables, un seul compte.'],
                        ['Workflow','Validation manager puis finance, traçable.'],
                        ['Facturation B2B','Factures groupées par période et centre de coût.'],
                        ['SLA & qualité','Suivi qualité et alertes opérationnelles.'],
                    ] as $i => $b)
                        <div class="cx-card p-6" data-cx-reveal data-cx-delay="{{ $i }}">
                            <p class="font-extrabold" style="font-family:var(--cx-display);color:var(--cx-text)">{{ $b[0] }}</p>
                            <p class="mt-2 text-sm" style="color:var(--cx-muted);line-height:1.6">{{ $b[1] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ═══════════ DESTINATION ═══════════ --}}
        <section class="cx-scene" data-cx-scene="hero">
            <div class="cx-scene__track cx-track--outro">
                <div class="cx-scene__pin">
                    <div class="cx-aura" data-cx-aura aria-hidden="true"></div>
                    <div data-cx-layer data-cx-role="in">
                        <div class="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
                            <span class="cx-chip"><span class="pip"></span> Fin de l’histoire</span>
                            <h2 class="cx-h mt-8 text-5xl sm:text-7xl">À vous<br><span class="cx-gradient-text">d’écrire la vôtre.</span></h2>
                            <p class="cx-lede mx-auto mt-7 max-w-xl text-lg">Quelques minutes, et votre prochaine mission est planifiée — du choix du service à la poignée de main.</p>
                            <div class="mt-10 flex flex-wrap items-center justify-center gap-4">
                                <a href="{{ route('booking.create') }}" class="cx-btn cx-btn--primary px-9 py-5 text-lg">Réserver maintenant →</a>
                                @guest<a href="{{ route('register') }}" class="cx-btn cx-btn--ghost px-9 py-5 text-lg">Créer un compte</a>@endguest
                            </div>
                            @if(Route::has('premium.offer'))
                                <p class="mt-8 text-sm" style="color:var(--cx-muted)">Client régulier ? <a href="{{ route('premium.offer') }}" class="font-bold underline decoration-dotted" style="color:var(--cx-amber)">Découvrez Premium</a></p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <script>
    (function () {
        var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var scenes = Array.prototype.slice.call(document.querySelectorAll('[data-cx-scene]'));
        if (reduce || !('IntersectionObserver' in window) || !('requestAnimationFrame' in window)) {
            scenes.forEach(function (s){ s.classList.add('cx-static'); }); return;
        }
        var clamp=function(v,a,b){return Math.max(a,Math.min(b,v));};
        var lerp =function(a,b,t){return a+(b-a)*t;};
        var active=new Set();
        var io=new IntersectionObserver(function(es){es.forEach(function(e){e.isIntersecting?active.add(e.target):active.delete(e.target);});},{rootMargin:'160px 0px 160px 0px'});
        scenes.forEach(function(s){io.observe(s);});

        function progressOf(scene){
            var t=scene.querySelector('.cx-scene__track').getBoundingClientRect();
            var vh=window.innerHeight||document.documentElement.clientHeight;
            var total=t.height-vh; if(total<=0) return 0;
            return clamp((-t.top)/total,0,1);
        }
        function renderHero(scene,p){
            var i=scene.querySelector('[data-cx-role="in"]'),o=scene.querySelector('[data-cx-role="out"]'),a=scene.querySelector('[data-cx-aura]');
            if(i){var oi=1-clamp((p-0.32)/0.30,0,1);i.style.opacity=oi;i.style.transform='translateY('+(-p*40)+'px) scale('+(1-p*0.06)+')';}
            if(o){var oo=clamp((p-0.42)/0.34,0,1);o.style.opacity=oo;o.style.transform='translateY('+((1-oo)*46)+'px)';}
            if(a)a.style.setProperty('--cx-aura',(0.7+p*0.9).toFixed(3));
        }
        function renderSteps(scene,p){
            var n=parseInt(scene.getAttribute('data-cx-count'),10)||1,t=p*n,idx=clamp(Math.floor(t),0,n-1),local=clamp(t-idx,0,1),nx=clamp(idx+1,0,n-1);
            var arts=scene.querySelectorAll('[data-cx-art]'),cps=scene.querySelectorAll('[data-cx-copy]'),sts=scene.querySelectorAll('[data-cx-step]'),
                ring=scene.querySelector('.cx-stage__ring'),tag=scene.querySelector('[data-cx-tradetag]');
            var tr=clamp((local-0.55)/0.45,0,1);
            for(var i=0;i<arts.length;i++){var op=0,sc=0.86;if(i===idx){op=1-tr;sc=1-0.14*tr;}else if(i===nx&&tr>0){op=tr;sc=0.86+0.14*tr;}arts[i].style.opacity=op;arts[i].style.transform='scale('+sc+')';}
            for(var c=0;c<cps.length;c++){var oc=0;if(c===idx)oc=1-tr;else if(c===nx&&tr>0)oc=tr;cps[c].style.opacity=oc;cps[c].style.transform='translateY('+((1-oc)*14)+'px)';}
            var shown=(tr>0.5)?nx:idx;
            for(var s=0;s<sts.length;s++)sts[s].classList.toggle('is-on',s===shown);
            if(ring)ring.style.setProperty('--cx-ring-rot',(p*220).toFixed(1)+'deg');
            if(ring&&scene.__colors)ring.style.setProperty('--trade',scene.__colors[shown]);
            if(tag&&scene.__names)tag.textContent=scene.__names[shown];
        }
        var STORY_CAPS=[
            'La cliente choisit un service',
            'Zoom sur le téléphone',
            'On suit la trajectoire en temps réel',
            'Arrivée — scan du QR — début',
            'Mission terminée — re-scan du QR',
            'La poignée de main'
        ];
        function renderStory(scene,p){
            var beats=scene.querySelectorAll('[data-beat]');
            var N=beats.length, t=p*N, idx=clamp(Math.floor(t),0,N-1), local=clamp(t-idx,0,1), nx=clamp(idx+1,0,N-1);
            var tr=clamp((local-0.7)/0.3,0,1); // crossfade fin de segment
            for(var i=0;i<beats.length;i++){
                var op=0;
                if(i===idx)op=1-tr; else if(i===nx&&tr>0)op=tr;
                beats[i].style.opacity=op;
                beats[i].style.transform = (op>0)? 'scale('+lerp(0.985,1,op)+')' : 'scale(.985)';
            }
            var shown=(tr>0.5)?nx:idx;
            var cap=scene.querySelector('[data-cx-beatcap]'); if(cap)cap.textContent=STORY_CAPS[shown];
            var cnt=scene.querySelector('[data-cx-counter]'); if(cnt)cnt.textContent=('0'+(shown+1))+' / 06';

            // ---- effets internes pilotés par "local" du beat courant ----
            // T1 : doigt -> carte, surbrillance
            if(idx===0){
                var fin=scene.querySelector('[data-cx-finger]'), pick=scene.querySelector('[data-cx-pick]');
                if(fin) fin.setAttribute('transform','translate('+lerp(720,737,clamp(local*1.4,0,1))+' '+lerp(470,402,clamp(local*1.4,0,1))+')');
                if(pick){ var on=clamp((local-0.55)/0.2,0,1); pick.setAttribute('stroke-width',(1+on*3).toFixed(2)); pick.setAttribute('fill', on>0.5?'#1c2a18':'#13203b'); }
            }
            // T2 : zoom téléphone
            if(idx===1){
                var pz=scene.querySelector('[data-cx-phonezoom]');
                if(pz){ var z=lerp(1,2.05,local); pz.setAttribute('transform','translate('+lerp(560,360,local)+' '+lerp(110,-40,local)+') scale('+z.toFixed(3)+')'); }
            }
            // T3 : tracé de route + déplacement + ETA
            if(idx===2){
                var rt=scene.querySelector('[data-cx-route]'), mv=scene.querySelector('[data-cx-mover]'), eta=scene.querySelector('[data-cx-eta]');
                if(rt){ var L=rt.getTotalLength(); rt.setAttribute('stroke-dasharray',L); rt.setAttribute('stroke-dashoffset',(L*(1-local)).toFixed(1));
                    if(mv){ var pt=rt.getPointAtLength(L*local); mv.setAttribute('transform','translate('+pt.x.toFixed(1)+' '+pt.y.toFixed(1)+')'); } }
                if(eta){ eta.textContent = Math.max(0,Math.round(12*(1-local))) + ' min'; }
            }
            // T4 : scan QR -> début (effets)
            if(idx===3){
                var fx=scene.querySelector('[data-cx-startfx]');
                if(fx) fx.setAttribute('opacity', clamp((local-0.5)/0.3,0,1));
            }
            // T5 : re-scan -> tampon TERMINÉ
            if(idx===4){
                var stp=scene.querySelector('[data-cx-stamp]');
                if(stp){ var s2=clamp((local-0.45)/0.3,0,1); stp.setAttribute('opacity',s2); stp.style.transformBox='fill-box'; stp.style.transformOrigin='center'; stp.style.transform='scale('+lerp(0.6,1,s2)+')'; }
            }
            // T6 : poignée de main
            if(idx===5){
                var hL=scene.querySelector('[data-cx-handL]'),hR=scene.querySelector('[data-cx-handR]'),
                    imp=scene.querySelector('[data-cx-impact]'),bub=scene.querySelector('[data-cx-bubble]');
                var join=clamp(local*1.3,0,1);
                if(hL) hL.style.transform='translateX('+lerp(-40,0,join)+'px)';
                if(hR) hR.style.transform='translateX('+lerp(40,0,join)+'px)';
                if(imp) imp.setAttribute('opacity', join>0.85? (1-(join-0.85)/0.15) : (join>0.7?1:0));
                if(bub){ var bb=clamp((local-0.6)/0.3,0,1); bub.setAttribute('opacity',bb); bub.style.transformBox='fill-box'; bub.style.transformOrigin='center'; bub.style.transform='scale('+lerp(0.5,1,bb)+')'; }
            }
        }
        scenes.forEach(function(scene){
            if(scene.getAttribute('data-cx-scene')==='steps'){
                scene.__names=[]; var pal=['#4fe3d6','#ffb648','#8b7bff','#5fd38a'];
                scene.querySelectorAll('[data-cx-step]').forEach(function(st){scene.__names.push(st.textContent.trim());});
                scene.__colors=pal.slice(0,scene.querySelectorAll('[data-cx-art]').length);
            }
        });
        var ticking=false;
        function frame(){
            ticking=false;
            scenes.forEach(function(scene){
                if(!active.has(scene)) return;
                var p=progressOf(scene), ty=scene.getAttribute('data-cx-scene');
                if(ty==='hero')renderHero(scene,p);
                else if(ty==='steps')renderSteps(scene,p);
                else if(ty==='story')renderStory(scene,p);
            });
        }
        function onScroll(){ if(!ticking){ticking=true;window.requestAnimationFrame(frame);} }
        window.addEventListener('scroll',onScroll,{passive:true});
        window.addEventListener('resize',onScroll,{passive:true});
        frame();
    })();
    </script>

</x-guest-layout>
