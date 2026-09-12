/**
 * La bascule client / prestataire de l'accueil.
 *
 * Elle pilote toute la page sous le héros. Trois sources décident du côté affiché,
 * dans cet ordre : le lien reçu (#prestataire), le choix mémorisé, puis « client ».
 * Un artisan à qui l'on envoie la page doit arriver sur son côté.
 *
 * Le texte du carrousel des métiers est injecté depuis Blade (`window.brioTextes`)
 * plutôt que dupliqué ici : il doit rester traduisible depuis /admin/traductions.
 */
const MEMOIRE = 'brio-cote';
const COTES = ['client', 'prestataire'];

function lireLaMemoire() {
    // Un navigateur en navigation privée refuse l'accès et lève : le défaut suffit.
    try {
        const v = window.localStorage.getItem(MEMOIRE);

        return COTES.includes(v) ? v : null;
    } catch (e) {
        return null;
    }
}

function ecrireLaMemoire(valeur) {
    try {
        window.localStorage.setItem(MEMOIRE, valeur);
    } catch (e) {
        /* le choix vaut pour cette visite, c'est assez */
    }
}

function cotesBrio() {
    return {
        cote: 'client',
        textes: window.brioTextes || { metiers: {} },

        demarrer() {
            const ancre = window.location.hash.replace('#', '');
            this.cote = COTES.includes(ancre) ? ancre : (lireLaMemoire() ?? 'client');

            // Le même lien doit rouvrir le même côté après un partage.
            window.addEventListener('hashchange', () => {
                const a = window.location.hash.replace('#', '');
                if (COTES.includes(a)) this.choisir(a, false);
            });
        },

        choisir(valeur, remonter = true) {
            if (! COTES.includes(valeur) || valeur === this.cote) return;

            this.cote = valeur;
            ecrireLaMemoire(valeur);

            // Le carrousel épinglé a mesuré la hauteur de l'autre côté : sans ce
            // signal, le défilement saute d'un écran au premier changement.
            window.dispatchEvent(new CustomEvent('brio:cote', { detail: { cote: valeur } }));

            if (! remonter) return;

            // On ramène le lecteur au début du côté qu'il vient de choisir, sinon il
            // atterrit au milieu d'un argumentaire qu'il n'a pas commencé.
            const bascule = document.querySelector('.cx-bascule');
            if (bascule) {
                const doux = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                bascule.scrollIntoView({ behavior: doux ? 'auto' : 'smooth', block: 'start' });
            }
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('cotesBrio', cotesBrio);
});

// Alpine peut avoir démarré avant ce module : on enregistre alors directement.
if (window.Alpine && window.Alpine.data) {
    window.Alpine.data('cotesBrio', cotesBrio);
}
