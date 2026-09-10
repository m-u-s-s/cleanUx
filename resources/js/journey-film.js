/* ============================================================================
   Brio — « Le Film » : le chargeur léger.
   ----------------------------------------------------------------------------
   La section vit SOUS la ligne de flottaison. On n'importe donc ni three.js ni
   GSAP au chargement de la home : ce chargeur minuscule observe la section et
   ne tire le cœur que lorsqu'elle approche du viewport (~700 px avant).

   Il décide aussi de ne RIEN charger du tout :
     • prefers-reduced-motion -> le stepper statique fait le travail
     • navigator.connection.saveData -> le poster suffit
   ========================================================================= */

let coeur = null;
let observateur = null;

function mouvementReduit() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function economiseurDeDonnees() {
    return (navigator.connection || {}).saveData === true;
}

function chargerCoeur() {
    if (coeur) {
        coeur.init();
        return;
    }

    import('./journey-film-core')
        .then((m) => {
            coeur = m;
            coeur.init();
        })
        .catch(() => {
            /* échec d'import -> le poster et le stepper restent en place */
        });
}

function observer() {
    const section = document.querySelector('[data-cx-film]');
    if (!section) return;

    // Le mode économie de données mérite mieux qu'un film de 7 Mo : il garde le poster.
    if (mouvementReduit() || economiseurDeDonnees()) return;

    if (coeur) {
        coeur.init();
        return;
    }

    if (observateur) return; // boot peut refirer : pas de doublon d'observateur

    if (!('IntersectionObserver' in window)) {
        if ('requestIdleCallback' in window) window.requestIdleCallback(chargerCoeur, { timeout: 3000 });
        else setTimeout(chargerCoeur, 600);
        return;
    }

    // `io` local : le rappel ne déréférence jamais la variable de module, qui peut
    // avoir été remise à null par un teardown entre-temps.
    const io = new IntersectionObserver(
        (entrees) => {
            if (entrees.some((e) => e.isIntersecting)) {
                io.disconnect();
                if (observateur === io) observateur = null;
                chargerCoeur();
            }
        },
        { rootMargin: '700px 0px 700px 0px' },
    );

    observateur = io;
    io.observe(section);
}

function demarrer() {
    observer();
}

function demonter() {
    if (observateur) {
        observateur.disconnect();
        observateur = null;
    }
    if (coeur) coeur.teardown();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', demarrer);
} else {
    demarrer();
}

document.addEventListener('livewire:navigated', demarrer);
document.addEventListener('livewire:navigating', demonter);
