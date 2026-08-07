/* ============================================================================
   Brio — Floating Nav : entrée globale (voie vanilla).
   Chargée sur toutes les pages guest. Arme chaque <header data-floating-nav>
   (état transparent/solide + blur, hide/reveal au scroll, menu mobile a11y).
   Inerte tant qu'aucun [data-floating-nav] n'existe.
   Re-scanne après navigation Livewire ; démonte proprement à la sortie.
   ========================================================================= */
import { initFloatingNav } from './floating-nav-core';

let cleanups = [];

function boot() {
    document.querySelectorAll('[data-floating-nav]').forEach((el) => {
        const c = initFloatingNav(el);
        if (c) cleanups.push(c);
    });
}

function teardown() {
    cleanups.forEach((c) => c && c());
    cleanups = [];
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
document.addEventListener('livewire:navigated', boot);
document.addEventListener('livewire:navigating', teardown);
window.addEventListener('pagehide', teardown);
