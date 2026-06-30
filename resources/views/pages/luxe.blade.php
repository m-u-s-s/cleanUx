{{--
    Landing luxe sombre — « Obsidia » (marque FICTIVE, contenu ORIGINAL : aucun
    logo/nom/texte/visuel réel). Dark premium · large type · cinematic motion.
    Architecture : Hero → Overview → Pillars → Interactive → Stats → Services →
    Presence → Content → Footer. Réutilise R3F (WebGL), Lenis+GSAP (premium-scroll),
    text/clip reveals, curseur magnétique, floating-nav. Dev only (routes/public.php).
--}}
<x-luxe-layout title="Obsidia — A Global House of Ventures">

    {{-- ══════════════ 1 · HERO (fullscreen · WebGL · floating layers · parallaxe) ══════════════ --}}
    <section class="luxe-hero" data-lux-hero data-lux-parallax-root>
        <div class="luxe-hero__bg" data-lux-r3f
             data-lux-colors="#d2a655,#f1e0b8,#6b4524" data-lux-fog="#0c0d11" aria-hidden="true"></div>
        <div class="luxe-hero__veil" aria-hidden="true"></div>
        <div class="luxe-hero__float luxe-hero__float--1" data-lux-parallax="18" aria-hidden="true"></div>
        <div class="luxe-hero__float luxe-hero__float--2" data-lux-parallax="-12" aria-hidden="true"></div>
        <div class="luxe-hero__float luxe-hero__float--3" data-lux-parallax="26" aria-hidden="true"></div>

        <div class="luxe-hero__content ed-container">
            <p class="ed-eyebrow" data-ed-reveal>Obsidia — Global house of ventures</p>
            <h1 class="ed-display" data-ed-lines style="margin-top:var(--s-md);max-width:14ch">
                <span class="ed-line">We build</span>
                <span class="ed-line">rare things,</span>
                <span class="ed-line"><span class="ed-italic ed-accent">slowly</span>.</span>
            </h1>
            <div class="ed-grid" style="margin-top:var(--s-2xl);row-gap:var(--s-lg);align-items:end">
                <p class="ed-lead ed-col-5" data-ed-reveal style="--i:1">
                    Capital, craft, and conviction — compounding across decades, not quarters.
                </p>
                <div class="ed-col-4 ed-start-8" data-ed-reveal style="--i:2">
                    <a href="#group" class="ed-cta" data-magnetic data-cursor-text="Enter">
                        <span data-magnetic-inner>Explore the group</span>
                        <span class="ed-cta__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
                        </span>
                    </a>
                </div>
            </div>
        </div>

        <a href="#group" class="luxe-hero__cue" aria-label="Scroll to overview">Scroll</a>
    </section>

    {{-- ══════════════ 2 · COMPANY OVERVIEW ══════════════ --}}
    <section class="ed-section" id="group">
        <div class="ed-container">
            <div class="ed-grid">
                <p class="ed-eyebrow ed-col-12" data-ed-reveal>The group</p>
                <h2 class="ed-h2 ed-col-9" data-ed-reveal style="--i:1;margin-top:var(--s-sm)">
                    A house built on a single, unfashionable belief: that the best things are made
                    by the <span class="ed-italic ed-accent">patient</span>.
                </h2>
            </div>
            <div class="ed-grid" style="margin-top:var(--s-2xl)">
                <div class="ed-col-5 ed-start-2" data-ed-reveal>
                    <p class="ed-body">Obsidia is a privately held group that owns and builds enduring
                        companies. We are not in the business of exits; we are in the business of decades.</p>
                    <p class="ed-body">Our capital is permanent, our horizon is generational, and our
                        method is restraint — we do less, and we do it for longer.</p>
                </div>
                <div class="ed-col-4 ed-start-8" data-ed-reveal style="--i:1">
                    <p class="ed-body">From frontier technology to real assets and houses of taste, we hold
                        what compounds and let time do the rest.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ══════════════ 3 · BUSINESS PILLARS ══════════════ --}}
    <section class="ed-section ed-section--tight">
        <div class="ed-container">
            <p class="ed-eyebrow" data-ed-reveal style="margin-bottom:var(--s-xl)">What we hold — four pillars</p>
            <div class="ed-grid">
                @php
                    $pillars = [
                        ['01', 'Ventures', 'Early conviction in founders building category-defining companies.'],
                        ['02', 'Real assets', 'Land, energy, and infrastructure held for the long arc.'],
                        ['03', 'Brands', 'Houses of taste, operated with patience and restraint.'],
                        ['04', 'Capital', 'Permanent capital, deployed without a clock.'],
                    ];
                @endphp
                @foreach ($pillars as $i => [$num, $title, $desc])
                    <div class="ed-col-3" data-ed-reveal style="--i:{{ $i }}">
                        <div class="luxe-pillar">
                            <span class="luxe-pillar__num">{{ $num }}</span>
                            <h3 class="luxe-pillar__title">{{ $title }}</h3>
                            <p class="ed-caption">{{ $desc }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══════════════ 4 · INTERACTIVE — horizontal rail (premium-scroll) ══════════════ --}}
    <section class="ed-rail-section" id="ventures" data-scroll-horizontal aria-label="Ventures — horizontal rail">
        <div class="ed-rail" data-scroll-track>
            <article class="ed-panel ed-panel--intro" data-scroll-panel>
                <div class="ed-panel__inner" data-scroll-panel-inner>
                    <p class="ed-eyebrow">The portfolio</p>
                    <h2 class="ed-display" style="font-size:clamp(2.6rem,7vw,6rem);max-width:13ch;margin-top:var(--s-md)">
                        Held, not <span class="ed-italic ed-accent">traded</span>.
                    </h2>
                    <p class="ed-lead" style="margin-top:var(--s-lg)">A horizontal cut through the house —
                        scroll to move sideways.</p>
                    <span class="ed-rail__cue" aria-hidden="true">Scroll to advance</span>
                </div>
            </article>
            @php
                $ventures = [
                    ['Lumen Labs', 'Frontier AI', 'Since 2019', 'linear-gradient(150deg, #15161c, #c9a86a 240%)'],
                    ['Atlas Energy', 'Real assets', 'Since 2016', 'radial-gradient(120% 100% at 78% 22%, #2a3a36, #0c1310 60%)'],
                    ['Vela Maritime', 'Infrastructure', 'Since 2014', 'linear-gradient(135deg, #14161d, #5a6b7a 220%)'],
                    ['Corten Works', 'Industrial', 'Since 2012', 'radial-gradient(120% 120% at 22% 16%, #3a2a1e, #1a120c 60%)'],
                    ['Noor Capital', 'Permanent capital', 'Since 2008', 'linear-gradient(160deg, #1a1b22, #c9a86a 300%)'],
                ];
            @endphp
            @foreach ($ventures as $i => [$name, $cat, $since, $art])
                <article class="ed-panel" data-scroll-panel>
                    <div class="ed-panel__inner" data-scroll-panel-inner>
                        <div class="ed-panel__grid">
                            <div class="ed-panel__art" style="--art:{{ $art }}" data-cursor-text="View"></div>
                            <div class="ed-panel__text">
                                <span class="ed-index-num">0{{ $i + 1 }} / 05</span>
                                <h3 class="ed-panel__title">{{ $name }}</h3>
                                <p class="ed-caption" style="margin-top:.5rem">{{ $cat }} · {{ $since }}</p>
                                <a href="#contact" class="ed-navlink" data-cursor-text="Open"
                                   style="margin-top:var(--s-lg);display:inline-block;font-family:var(--ed-display);font-size:1.1rem">View company →</a>
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    {{-- ══════════════ 5 · STATISTICS ══════════════ --}}
    <section class="ed-section ed-section--tight">
        <div class="ed-container">
            <div class="ed-grid">
                @php
                    $stats = [
                        ['12', '€', 'B', 'Under stewardship'],
                        ['60', '', '+', 'Companies held'],
                        ['24', '', '', 'Countries'],
                        ['30', '', '+', 'Years compounding'],
                    ];
                @endphp
                @foreach ($stats as $i => [$n, $pre, $suf, $label])
                    <div class="ed-col-3 ed-stat" data-ed-reveal style="--i:{{ $i }}">
                        <div class="ed-stat__num" data-ed-count="{{ $n }}"
                             @if($pre) data-ed-prefix="{{ $pre }}" @endif
                             @if($suf) data-ed-suffix="{{ $suf }}" @endif>{{ $pre }}0{{ $suf }}</div>
                        <div class="ed-stat__label">{{ $label }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══════════════ 6 · SERVICES ══════════════ --}}
    <section class="ed-section ed-section--tight" id="services">
        <div class="ed-container">
            <p class="ed-eyebrow" data-ed-reveal style="margin-bottom:var(--s-xl)">What we do — 04 services</p>
            <div class="ed-index">
                @php
                    $services = [
                        ['01', 'Stewardship', 'Long-horizon ownership, governance, and capital allocation.'],
                        ['02', 'Operating partnership', 'Hands-on building alongside founders and teams.'],
                        ['03', 'Advisory', 'Counsel on strategy, brand, and the long game.'],
                        ['04', 'Capital markets', 'Structuring patient capital for ambitious work.'],
                    ];
                @endphp
                @foreach ($services as $i => [$num, $title, $desc])
                    <a href="#contact" class="ed-index__row" data-ed-reveal style="--i:{{ $i }}" data-cursor-text="Brief">
                        <span class="ed-index-num">{{ $num }}</span>
                        <span>
                            <span class="ed-index__title">{{ $title }}</span>
                            <span class="ed-index__desc" style="display:block;margin-top:.4rem">{{ $desc }}</span>
                        </span>
                        <span class="ed-index__arrow" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══════════════ 7 · GLOBAL PRESENCE ══════════════ --}}
    <section class="ed-section" id="presence">
        <div class="ed-container">
            <div class="ed-grid" style="align-items:center;row-gap:var(--s-2xl)">
                <div class="ed-col-6" data-ed-reveal>
                    <p class="ed-eyebrow">Where we are</p>
                    <h2 class="ed-h2" style="margin-top:var(--s-sm)">On every <span class="ed-italic ed-accent">meridian</span>.</h2>
                    <div style="margin-top:var(--s-xl);border-top:1px solid var(--ed-line)">
                        @php
                            $cities = [
                                ['London', '51.5°N 0.1°W'], ['New York', '40.7°N 74.0°W'],
                                ['Singapore', '1.3°N 103.8°E'], ['Zürich', '47.4°N 8.5°E'],
                                ['São Paulo', '23.5°S 46.6°W'], ['Tokyo', '35.7°N 139.7°E'],
                            ];
                        @endphp
                        @foreach ($cities as $c)
                            <div class="luxe-city">
                                <span class="luxe-city__name">{{ $c[0] }}</span>
                                <span class="luxe-city__coord">{{ $c[1] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="ed-col-4 ed-start-8" data-ed-reveal style="--i:1">
                    <div class="luxe-globe" data-scroll-parallax="0.16" aria-hidden="true"></div>
                </div>
            </div>
        </div>
    </section>

    {{-- ══════════════ 8 · CONTENT BLOCKS (insights) ══════════════ --}}
    <section class="ed-section ed-section--tight">
        <div class="ed-container">
            <div class="ed-grid" style="align-items:end;margin-bottom:var(--s-lg)">
                <h2 class="ed-h2 ed-col-7" data-ed-reveal>From the house.</h2>
                <p class="ed-eyebrow ed-col-3 ed-start-10" data-ed-reveal style="--i:1;justify-self:end">Insights</p>
            </div>
            @php
                $insights = [
                    ['The case for patient capital', 'Essay', '2026'],
                    ['On owning, not trading', 'Note', '2026'],
                    ['Compounding, quietly', 'Essay', '2025'],
                    ['What a decade buys', 'Field note', '2025'],
                ];
            @endphp
            <div style="border-top:1px solid var(--ed-line)">
                @foreach ($insights as $i => [$title, $kind, $date])
                    <a href="#contact" class="ed-journal__row" data-ed-reveal style="--i:{{ $i }}" data-cursor-text="Read">
                        <span class="ed-journal__title">{{ $title }}</span>
                        <span style="display:flex;gap:var(--s-md);align-items:baseline">
                            <span class="ed-caption">{{ $kind }}</span>
                            <span class="ed-journal__date">{{ $date }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ══════════════ 9 · CONTACT / CTA ══════════════ --}}
    <section class="ed-section" id="contact">
        <div class="ed-container">
            <p class="ed-eyebrow" data-ed-reveal>Connect</p>
            <p class="ed-footer__big" data-ed-reveal style="--i:1;margin-top:var(--s-md);max-width:13ch">
                Let's build something <span class="ed-italic ed-accent">enduring</span>.
            </p>
            <div class="ed-grid" style="margin-top:var(--s-2xl);align-items:center;row-gap:var(--s-lg)">
                <div class="ed-col-6" data-ed-reveal>
                    <a href="#contact" class="ed-cta" data-magnetic data-magnetic-strength="0.35" data-cursor-text="Hello"
                       style="font-size:1.05rem">
                        <span data-magnetic-inner>Start a conversation</span>
                        <span class="ed-cta__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M9 7h8v8"/></svg>
                        </span>
                    </a>
                </div>
                <div class="ed-col-4 ed-start-9" data-ed-reveal style="--i:1">
                    <p class="ed-caption">Group enquiries</p>
                    <a href="#contact" class="ed-navlink" style="font-family:var(--ed-display);font-size:1.25rem">ventures@obsidia.group</a>
                </div>
            </div>
        </div>
    </section>

</x-luxe-layout>
