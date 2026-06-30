import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        react(),
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // Heavy libs loaded only on pages that need them (see @vite in those blades).
                'resources/js/apexcharts.js',
                'resources/js/fullcalendar.js',
                // Home "parcours d'une mission" : GSAP ScrollTrigger + globe 3D Three.js.
                // Chargé uniquement sur la home via @push('scripts') dans home.blade.php.
                'resources/js/home-journey.js',
                // Home luxury hero : GSAP entrance + Motion scroll effects.
                'resources/js/luxury-hero.js',
                // Home luxury hero : React Three Fiber particle background (procedural
                // GPU points). React island, self-hosted via Vite (CSP-safe).
                'resources/js/hero-r3f.jsx',
                // Premium scroll engine : Lenis smooth scroll + GSAP ScrollTrigger
                // behaviors (parallax / pin / horizontal / progress). Chargé
                // globalement via le layout guest mais inerte tant qu'une page
                // n'opte pas via [data-premium-scroll] / [data-scroll-*]. Les libs
                // lourdes y sont importées dynamiquement (coût ~0 sinon).
                'resources/css/premium-scroll.css',
                'resources/js/premium-scroll.js',
                // Cinematic text reveal : CSS des masques + helpers typo fluide,
                // chargé globalement (inerte tant que les hooks React ne tournent
                // pas). resources/js/hooks/useTextReveal.js est importé par les
                // îlots React qui l'utilisent (pas une entrée à part).
                'resources/css/text-reveal.css',
                // Démo des hooks (page dev /premium-scroll uniquement).
                'resources/js/text-reveal-demo.jsx',
                // Image reveal : CSS des effets + entrée vanilla (scanne
                // [data-image-reveal], IntersectionObserver). Inerte sinon.
                // Le composant React <RevealImage> partage image-reveal-core.js.
                'resources/css/image-reveal.css',
                'resources/js/image-reveal.js',
                // Premium cursor : follower 2 couches + boutons magnétiques +
                // états de survol + aperçu image, piloté par un seul rAF (lerp).
                // Inerte si pointeur grossier / reduced-motion. Boutons via
                // [data-magnetic] (React : <MagneticButton>), partage cursor-core.js.
                'resources/css/premium-cursor.css',
                'resources/js/premium-cursor.js',
                // Stats section animée (Framer Motion / motion) : count-up +
                // progress bars + cards responsive + stagger + viewport trigger.
                // CSS global (drop-in) ; l'îlot React s'auto-monte sur
                // [data-stats-section] et se charge PAR PAGE (React+motion) via
                // @push('scripts'). React direct : <StatsSection>.
                'resources/css/stats-section.css',
                'resources/js/stats-section.jsx',
                // Floating nav : transparent→solide+blur, hide/reveal au scroll,
                // menu mobile accessible (focus trap + Échap + scroll lock).
                // Arme [data-floating-nav] (le header guest). Vanilla, global.
                'resources/css/floating-nav.css',
                'resources/js/floating-nav.js',
                // Étude de design éditorial (page dev /editorial, marque fictive
                // « Northlight ») : système de tokens + grille + typo + mouvement.
                // Re-thème les moteurs réutilisés via le pont --cx-* (classe .ed).
                'resources/css/editorial.css',
                'resources/js/editorial.js',
            ],
            refresh: true,
        }),
    ],
});
