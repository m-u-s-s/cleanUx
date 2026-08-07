/* ============================================================================
   Brio — Stats Section : auto-mount des îlots React (voie Blade/Livewire).
   ----------------------------------------------------------------------------
   Scanne le DOM pour [data-stats-section] et y monte <StatsSection> (Framer
   Motion). La config se passe en JSON inline :

     <div data-stats-section>
       <script type="application/json">
         { "eyebrow": "…", "title": "…",
           "stats": [ { "value": 12500, "suffix": "+", "label": "Missions", "progress": 92 } ] }
       </script>
     </div>

   À charger UNIQUEMENT sur les pages qui l'utilisent (React + motion) via
   @push('scripts') @vite('resources/js/stats-section.jsx') @endpush.
   Re-scanne après navigation Livewire ; démonte proprement à la sortie.

   React : importer directement <StatsSection> (resources/js/components/StatsSection).
   ========================================================================= */
import React from 'react';
import { createRoot } from 'react-dom/client';
import { StatsSection } from './components/StatsSection';

function mountInto(el) {
    if (el.__statsMounted) return;
    el.__statsMounted = true;

    let config = {};
    const json = el.querySelector('script[type="application/json"]');
    if (json) {
        try {
            config = JSON.parse(json.textContent || '{}');
        } catch (e) {
            // eslint-disable-next-line no-console
            console.error('[stats-section] config JSON invalide', e);
        }
    }

    const host = document.createElement('div');
    host.className = 'cx-stats-host';
    el.appendChild(host);
    const root = createRoot(host);
    root.render(
        <React.StrictMode>
            <StatsSection {...config} />
        </React.StrictMode>
    );
    el.__statsRoot = root;
    el.__statsHost = host;
}

function scan(root) {
    (root || document).querySelectorAll('[data-stats-section]').forEach(mountInto);
}

function boot() {
    scan(document);
}

function teardown() {
    document.querySelectorAll('[data-stats-section]').forEach((el) => {
        if (el.__statsRoot) {
            el.__statsRoot.unmount();
            el.__statsRoot = null;
        }
        if (el.__statsHost) {
            el.__statsHost.remove();
            el.__statsHost = null;
        }
        el.__statsMounted = false;
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
document.addEventListener('livewire:navigated', boot);
document.addEventListener('livewire:navigating', teardown);
window.addEventListener('pagehide', teardown);
