{{--
    Étude de design éditorial premium — « Northlight » (marque FICTIVE, contenu
    ORIGINAL : aucun logo/nom/texte/visuel réel reproduit). Démontre 6 systèmes :
    espacement · grille · rythme typographique · mouvement · interaction · hiérarchie.
    Dev/staging only (cf. routes/public.php). Moteurs réutilisés via data-attributes.
--}}
<x-editorial-layout title="Northlight — Design & Innovation Studio">

    {{-- ════════════════════ 1 · HERO (hiérarchie + mouvement) ════════════════════ --}}
    <section class="ed-section" style="padding-top:clamp(8rem,16vh,12rem)">
        <div class="ed-container">
            {{-- mark décoratif en parallaxe (procédural, aria-hidden) --}}
            <div aria-hidden="true" data-scroll-parallax="0.32" style="position:absolute;right:var(--edge);top:6rem;width:clamp(8rem,22vw,20rem);height:clamp(8rem,22vw,20rem);border-radius:999px;background:radial-gradient(60% 60% at 35% 30%,#e7cfa9,var(--ed-accent) 70%,transparent);filter:blur(2px);opacity:.5;pointer-events:none"></div>

            <p class="ed-eyebrow" data-ed-reveal>Independent design &amp; innovation studio</p>

            <h1 class="ed-display" data-ed-lines style="margin-top:var(--s-md);max-width:16ch">
                <span class="ed-line">We design</span>
                <span class="ed-line">brands with</span>
                <span class="ed-line"><span class="ed-italic ed-accent">gravity</span>.</span>
            </h1>

            <div class="ed-grid" style="margin-top:var(--s-2xl);row-gap:var(--s-lg);align-items:end">
                <p class="ed-lead ed-col-5" data-ed-reveal style="--i:1">
                    A studio for companies that refuse the obvious — partnering from first
                    principle to last pixel to build identity, product, and experience with
                    lasting pull.
                </p>
                <div class="ed-col-4 ed-start-7" data-ed-reveal style="--i:2">
                    <p class="ed-caption">Established 2014</p>
                    <p class="ed-caption">Brussels · New York</p>
                    <p class="ed-caption" style="margin-top:var(--s-sm);color:var(--ed-ink-2)">
                        Branding — Product — Motion — Strategy
                    </p>
                </div>
                <div class="ed-col-3" data-ed-reveal style="--i:3;align-self:end">
                    <a href="#work" class="ed-cta ed-cta--ghost" data-magnetic data-cursor-text="Look">
                        <span data-magnetic-inner>See the work</span>
                        <span class="ed-cta__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
                        </span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- ════════════════════ 2 · MANIFESTO (rythme typographique) ════════════════════ --}}
    <section class="ed-section" id="studio">
        <div class="ed-container">
            <div class="ed-grid">
                <p class="ed-eyebrow ed-col-12" data-ed-reveal>The studio</p>
                <h2 class="ed-h2 ed-col-9" data-ed-reveal style="--i:1;margin-top:var(--s-sm)">
                    A small studio with a long view. We work in close partnership, not high
                    volume — so every detail carries the <span class="ed-italic ed-accent">weight of the whole</span>.
                </h2>
            </div>

            <div class="ed-grid" style="margin-top:var(--s-2xl)">
                <div class="ed-col-5 ed-start-2" data-ed-reveal>
                    <p class="ed-body">We believe taste is a discipline, not an accident. Each
                        engagement begins with a question worth answering and ends with a system
                        precise enough to survive contact with the real world.</p>
                    <p class="ed-body">No templates, no house style imposed from above — only a
                        method: reduce until what remains is inevitable, then make it move.</p>
                </div>
                <div class="ed-col-4 ed-start-8" data-ed-reveal style="--i:1">
                    <p class="ed-body">The result is work that feels both new and somehow already
                        true — identities that scale, products that earn their restraint, and
                        motion that means something.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ════════════════════ 3 · CAPABILITIES (grille + interaction) ════════════════════ --}}
    <section class="ed-section ed-section--tight">
        <div class="ed-container">
            <p class="ed-eyebrow" data-ed-reveal style="margin-bottom:var(--s-xl)">What we do — 06 disciplines</p>

            <div class="ed-index">
                @php
                    $caps = [
                        ['01', 'Brand systems', 'Identities engineered to scale across every surface and decade.'],
                        ['02', 'Digital products', 'Interfaces with the restraint of print and the responsiveness of software.'],
                        ['03', 'Motion & interaction', 'Movement that means something — choreography, not decoration.'],
                        ['04', 'Editorial & art direction', 'A point of view, set in type and image.'],
                        ['05', 'Spatial & environment', 'Brands made physical — from retail floor to exhibition hall.'],
                        ['06', 'Strategy & naming', 'The thinking that earns the work its place.'],
                    ];
                @endphp
                @foreach ($caps as $i => [$num, $title, $desc])
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

    {{-- ════════════════════ 4 · SELECTED WORK (grille asymétrique + image-reveal) ════════════════════ --}}
    <section class="ed-section" id="work">
        <div class="ed-container ed-container--wide">
            <div class="ed-grid" style="align-items:end;margin-bottom:var(--s-2xl)">
                <h2 class="ed-h2 ed-col-7" data-ed-reveal>Recent chapters.</h2>
                <p class="ed-body ed-col-4 ed-start-9" data-ed-reveal style="--i:1">A selection of
                    engagements across identity, product, and place. Names and visuals are original.</p>
            </div>

            @php
                $work = [
                    ['Halcyon', 'Identity system', '2026', '7', 'radial-gradient(120% 120% at 18% 12%, #efd9b6, #b5572e 58%, #2a1812)'],
                    ['Meridian', 'Type & editorial', '2025', '5', 'linear-gradient(150deg, #2a2622, #6c5848 60%, #c89b6a)'],
                    ['Atlas Pavilion', 'Spatial', '2025', '5', 'radial-gradient(120% 90% at 80% 20%, #cfe0d6, #5e7a6b 55%, #20302a)'],
                    ['Sable & Stone', 'Product', '2024', '7', 'linear-gradient(135deg, #f0e7d8, #c08a5a 55%, #5a3a26)'],
                ];
            @endphp
            <div class="ed-grid" style="row-gap:var(--s-2xl)">
                @foreach ($work as $i => [$name, $cat, $year, $span, $art])
                    <article class="ed-col-{{ $span }}" data-ed-reveal style="--i:{{ $i }}">
                        <figure class="ed-figure {{ $span === '7' ? 'ed-figure--wide' : '' }}"
                                data-cursor-text="View" style="margin:0">
                            <div class="ed-figure__core">
                                <div class="ed-figure__art" style="--art:{{ $art }}"></div>
                            </div>
                        </figure>
                        <figcaption class="ed-figure__meta">
                            <span class="ed-h3">{{ $name }}</span>
                            <span class="ed-caption" style="text-align:right">{{ $cat }}<br>{{ $year }}</span>
                        </figcaption>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ════════════════════ 4b · WORK RAIL (scroll horizontal — moteur premium-scroll) ════════════════════
         Base scroll-snap natif (mobile/tactile/sans-JS) → upgrade pin + translateX
         GSAP sur desktop. Panneaux 100vw ; [data-scroll-panel-inner] animé au passage. --}}
    <section class="ed-rail-section" data-scroll-horizontal aria-label="The archive — horizontal work rail">
        <div class="ed-rail" data-scroll-track>

            {{-- Panneau d'intro --}}
            <article class="ed-panel ed-panel--intro" data-scroll-panel>
                <div class="ed-panel__inner" data-scroll-panel-inner>
                    <p class="ed-eyebrow">The archive</p>
                    <h2 class="ed-display" style="font-size:clamp(2.6rem,7vw,6rem);max-width:13ch;margin-top:var(--s-md)">
                        Chapters, in <span class="ed-italic ed-accent">sequence</span>.
                    </h2>
                    <p class="ed-lead" style="margin-top:var(--s-lg)">A horizontal cut through recent work —
                        scroll to move sideways.</p>
                    <span class="ed-rail__cue" aria-hidden="true">Scroll to advance</span>
                </div>
            </article>

            {{-- Panneaux projets (noms/visuels originaux) --}}
            @php
                $archive = [
                    ['Lume', 'Identity & packaging', '2024', 'Light, made legible — a system that carries a brand from shelf to screen without losing its glow.', 'radial-gradient(120% 120% at 22% 14%, #f1ddba, #b5572e 60%, #311a10)'],
                    ['Corten', 'Spatial & wayfinding', '2023', 'A material language for a building that wanted to feel inevitable, not designed.', 'linear-gradient(145deg, #2c2622, #7a6450 58%, #cda36e)'],
                    ['Ortolan', 'Editorial & art direction', '2023', 'A quarterly with a spine — opinion set in type, paced like a long walk.', 'radial-gradient(120% 100% at 78% 22%, #cfe0d6, #5e7a6b 56%, #20302a)'],
                    ['Vesper', 'Product & interface', '2022', 'Software with the manners of print: quiet, exact, and never in a hurry.', 'linear-gradient(135deg, #efe6d6, #c08a5a 56%, #5a3a26)'],
                    ['Marl', 'Brand world', '2022', 'A whole world from a single gesture — earthy, optimistic, and built to age well.', 'radial-gradient(120% 120% at 30% 80%, #e8d3b2, #9a6b3f 55%, #2a1d12)'],
                ];
            @endphp
            @foreach ($archive as $i => [$name, $cat, $year, $note, $art])
                <article class="ed-panel" data-scroll-panel>
                    <div class="ed-panel__inner" data-scroll-panel-inner>
                        <div class="ed-panel__grid">
                            <div class="ed-panel__art" style="--art:{{ $art }}" data-cursor-text="View"></div>
                            <div class="ed-panel__text">
                                <span class="ed-index-num">0{{ $i + 1 }} / 05</span>
                                <h3 class="ed-panel__title">{{ $name }}</h3>
                                <p class="ed-caption" style="margin-top:.5rem">{{ $cat }} · {{ $year }}</p>
                                <p class="ed-body" style="margin-top:var(--s-md)">{{ $note }}</p>
                                <a href="#contact" class="ed-navlink" data-cursor-text="Open"
                                   style="margin-top:var(--s-md);display:inline-block;font-family:var(--ed-display);font-size:1.1rem">View case →</a>
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach

        </div>
    </section>

    {{-- ════════════════════ 5 · STATS (compteurs + hiérarchie) ════════════════════ --}}
    <section class="ed-section ed-section--tight">
        <div class="ed-container">
            <div class="ed-grid">
                @php
                    $stats = [
                        ['11', '', '', 'Years independent'],
                        ['240', '+', '', 'Engagements shipped'],
                        ['18', '', '', 'Countries reached'],
                        ['31', '+', '', 'Honours & features'],
                    ];
                @endphp
                @foreach ($stats as $i => [$n, $suf, $pre, $label])
                    <div class="ed-col-3 ed-stat" data-ed-reveal style="--i:{{ $i }}">
                        <div class="ed-stat__num" data-ed-count="{{ $n }}" @if($suf) data-ed-suffix="{{ $suf }}" @endif>{{ $pre }}0{{ $suf }}</div>
                        <div class="ed-stat__label">{{ $label }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ════════════════════ 6 · PULL-QUOTE ════════════════════ --}}
    <section class="ed-section">
        <div class="ed-container">
            <blockquote class="ed-quote" data-ed-reveal>
                We'd rather make one thing that <em>lasts</em> than ten that merely launch.
            </blockquote>
        </div>
    </section>

    {{-- ════════════════════ 7 · THINKING / JOURNAL ════════════════════ --}}
    <section class="ed-section ed-section--tight" id="thinking">
        <div class="ed-container">
            <div class="ed-grid" style="align-items:end;margin-bottom:var(--s-lg)">
                <h2 class="ed-h2 ed-col-7" data-ed-reveal>From the studio.</h2>
                <p class="ed-eyebrow ed-col-3 ed-start-10" data-ed-reveal style="--i:1;justify-self:end">Thinking</p>
            </div>

            @php
                $journal = [
                    ['On restraint as a design strategy', 'Essay', 'Mar 2026'],
                    ['The grid is a moral position', 'Note', 'Jan 2026'],
                    ['Motion that earns its keep', 'Essay', 'Nov 2025'],
                    ['Naming things that outlive trends', 'Field note', 'Sep 2025'],
                ];
            @endphp
            <div style="border-top:1px solid var(--ed-line)">
                @foreach ($journal as $i => [$title, $kind, $date])
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

    {{-- ════════════════════ 8 · CONTACT / CTA ════════════════════ --}}
    <section class="ed-section" id="contact">
        <div class="ed-container">
            <p class="ed-eyebrow" data-ed-reveal>Start a project</p>
            <p class="ed-footer__big" data-ed-reveal style="--i:1;margin-top:var(--s-md);max-width:14ch">
                Let's make something that <span class="ed-italic ed-accent">lasts</span>.
            </p>
            <div class="ed-grid" style="margin-top:var(--s-2xl);align-items:center;row-gap:var(--s-lg)">
                <div class="ed-col-6" data-ed-reveal>
                    <a href="#contact" class="ed-cta" data-magnetic data-magnetic-strength="0.35" data-cursor-text="Hello"
                       style="font-size:1.05rem">
                        <span data-magnetic-inner>Tell us what you're building</span>
                        <span class="ed-cta__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M9 7h8v8"/></svg>
                        </span>
                    </a>
                </div>
                <div class="ed-col-4 ed-start-9" data-ed-reveal style="--i:1">
                    <p class="ed-caption">Studio enquiries</p>
                    <a href="#contact" class="ed-navlink" style="font-family:var(--ed-display);font-size:1.25rem">hello@northlight.studio</a>
                </div>
            </div>
        </div>
    </section>

</x-editorial-layout>
