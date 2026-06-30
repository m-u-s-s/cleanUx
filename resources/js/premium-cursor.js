/* ============================================================================
   CleanUx — Premium Cursor : entrée globale (voie vanilla).
   Chargée sur toutes les pages guest. Crée le curseur custom (singleton attaché
   au <body>) si l'appareil a un pointeur fin et n'est pas en reduced-motion ;
   sinon noop total (curseur natif conservé, coût ~0).

   Survol / boutons magnétiques / aperçu image passent par de la DÉLÉGATION sur
   `document` -> aucun re-scan nécessaire pour les éléments ajoutés après coup.
   On recrée néanmoins le singleton sur navigation Livewire (le <body> est
   re-rendu) : teardown sur `livewire:navigating`, boot sur `livewire:navigated`.

   React : importer initPremiumCursor / <MagneticButton> (même cœur cursor-core).
   ========================================================================= */
import { initPremiumCursor } from './cursor-core';

let cleanup = null;

function boot() {
    if (cleanup) return;
    cleanup = initPremiumCursor();
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
