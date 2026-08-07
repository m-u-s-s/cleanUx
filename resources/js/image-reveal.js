/* ============================================================================
   Brio — Image Reveal : entrée globale (voie vanilla).
   Chargée sur toutes les pages guest mais inerte tant qu'aucun élément
   [data-image-reveal] n'existe. Scanne le DOM et arme la révélation au scroll
   (IntersectionObserver). Re-scanne après navigation Livewire.
   Pour React : importer useImageReveal / RevealImage (même cœur).
   ========================================================================= */
import { initImageReveals } from './image-reveal-core';

let cleanup = null;

function boot() {
    cleanup = initImageReveals(document);
}

function teardown() {
    if (cleanup) {
        cleanup();
        cleanup = null;
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
document.addEventListener('livewire:navigated', boot);
document.addEventListener('livewire:navigating', teardown);
window.addEventListener('pagehide', teardown);
