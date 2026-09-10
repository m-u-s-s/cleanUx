/* ============================================================================
   Brio — « Le Film » : le décodeur de frames.
   ----------------------------------------------------------------------------
   350 frames AVIF (14 plans × 25) fabriquées par
   scripts/journey-film/construire-les-frames.sh. Elles ne sont PAS téléchargées
   d'un bloc : on charge par vagues de chapitre, et la vague k+1 se précharge
   pendant que le visiteur traverse le chapitre k. Qui ne descend pas ne paie
   qu'une vague (~500 Ko en desktop, ~235 Ko en mobile).

   Ce module ne connaît ni WebGL ni le scroll : il rend un index et deux images
   voisines. C'est le moteur de rendu qui décide quoi en faire.
   ========================================================================= */

const BASE = '/images/journey-film/';

/* Détection AVIF : une image d'un pixel. Sans AVIF, on ne charge RIEN — la
   section reste sur son poster et son stepper (repli déjà en place). */
const AVIF_1PX =
    'data:image/avif;base64,AAAAIGZ0eXBhdmlmAAAAAGF2aWZtaWYxbWlhZk1BMUIAAADybWV0YQAAAAAAAAAoaGRscgAAAAAAAAAAcGljdAAAAAAAAAAAAAAAAGxpYmF2aWYAAAAADnBpdG0AAAAAAAEAAAAeaWxvYwAAAABEAAABAAEAAAABAAABGgAAAB0AAAAoaWluZgAAAAAAAQAAABppbmZlAgAAAAABAABhdjAxQ29sb3IAAAAAamlwcnAAAABLaXBjbwAAABRpc3BlAAAAAAAAAAEAAAABAAAAEHBpeGkAAAAAAwgICAAAAAxhdjFDgQAMAAAAABNjb2xybmNseAACAAIABoAAAAAXaXBtYQAAAAAAAAABAAEEAQKDBAAAACVtZGF0EgAKCBgABogQEDQgMgkQAAAAB8dSLfI=';

export function avifDisponible() {
    return new Promise((resolve) => {
        const img = new Image();
        img.onload = () => resolve(img.width === 1);
        img.onerror = () => resolve(false);
        img.src = AVIF_1PX;
    });
}

/**
 * Le manifeste, TOUJOURS REVALIDÉ.
 *
 * Il portait `cache: 'force-cache'`, ce qui servait l'ancien manifeste même quand un nouveau
 * film était en ligne — donc l'ancienne version, donc les anciennes frames. C'est le seul
 * fichier du lot qu'on ne peut pas versionner : il est celui qui PORTE la version.
 */
export async function chargerManifeste() {
    const reponse = await fetch(BASE + 'frames.json', { cache: 'no-cache' });
    if (!reponse.ok) throw new Error('manifeste absent');
    return reponse.json();
}

/**
 * Le magasin de frames.
 *
 * @param {{total:number, parPlan:number, plans:number}} manifeste
 * @param {'desktop'|'mobile'} taille
 */
export function creerMagasin(manifeste, taille) {
    const total = manifeste.total;
    const parPlan = manifeste.parPlan;
    const images = new Array(total).fill(null);
    const enCours = new Set();
    const vaguesDemandees = new Set();
    const attentes = new Map(); // index -> liste de resolveurs

    let derniereConnue = -1; // la dernière frame réellement décodée : jamais de trou noir
    let abandonne = false;

    // LE JETON DE VERSION EST OBLIGATOIRE DANS L'URL.
    // Les 700 fichiers gardent leurs noms d'un tournage à l'autre : sans lui, le navigateur d'un
    // visiteur qui a déjà vu le film lui reservirait l'ancien depuis son cache, et aucun
    // rechargement de page n'y changerait quoi que ce soit.
    const jeton = manifeste.version ? `?v=${encodeURIComponent(manifeste.version)}` : '';

    function url(index) {
        return `${BASE}${taille}/${String(index).padStart(3, '0')}.avif${jeton}`;
    }

    /** Réveille ce qui attendait cette frame — chargée ou définitivement absente. */
    function reveiller(index, image) {
        const liste = attentes.get(index);
        if (!liste) return;
        attentes.delete(index);
        liste.forEach((resoudre) => resoudre(image));
    }

    function charger(index) {
        if (abandonne) return;
        if (index < 0 || index >= total) return;
        if (images[index] || enCours.has(index)) return;

        enCours.add(index);
        const img = new Image();
        img.decoding = 'async';
        img.onload = () => {
            enCours.delete(index);
            images[index] = img;
            if (derniereConnue < 0) derniereConnue = index;
            reveiller(index, img);
        };
        img.onerror = () => {
            enCours.delete(index);
            // Une frame manquante n'arrête pas le film : le rendu garde la précédente.
            reveiller(index, null);
        };
        img.src = url(index);
    }

    /** Une vague = un chapitre entier (25 frames), l'unité de découpe du film. */
    function chargerVague(vague) {
        if (vague < 0 || vague >= manifeste.plans) return;
        if (vaguesDemandees.has(vague)) return;
        vaguesDemandees.add(vague);

        const debut = vague * parPlan;
        for (let i = debut; i < debut + parPlan; i++) charger(i);
    }

    return {
        total,

        /** La première vague, celle qui permet d'afficher quelque chose. */
        amorcer() {
            chargerVague(0);
            return this.pret(0);
        },

        /**
         * Positionne le magasin : charge le chapitre courant et précharge le suivant.
         * Retourne les deux images à fondre et le poids du fondu.
         *
         * @param {number} position 0..1 sur tout le film
         */
        placer(position) {
            const exact = Math.max(0, Math.min(total - 1.0001, position * (total - 1)));
            const bas = Math.floor(exact);
            const haut = Math.min(total - 1, bas + 1);
            const melange = exact - bas;

            const vague = Math.floor(bas / parPlan);
            chargerVague(vague);
            chargerVague(vague + 1); // le chapitre suivant arrive pendant celui-ci
            if (melange > 0.5) chargerVague(Math.floor(haut / parPlan));

            if (images[bas]) derniereConnue = bas;

            return {
                a: images[bas] || images[derniereConnue] || null,
                b: images[haut] || images[bas] || images[derniereConnue] || null,
                melange: images[bas] && images[haut] ? melange : 0,
                index: bas,
            };
        },

        /**
         * Attend qu'une frame précise soit décodée — utilisé pour le premier rendu.
         *
         * ÉVÉNEMENTIEL, PAS UN SONDAGE EN rAF : `requestAnimationFrame` ne tourne PAS dans un
         * onglet que le navigateur ne peint pas (arrière-plan, fenêtre masquée). Une attente
         * qui sondait en rAF ne se résolvait alors jamais — même pas sur son propre délai de
         * garde — et `init()` restait bloqué à vie sans jamais rendre la main au poster.
         */
        pret(index) {
            return new Promise((resolve) => {
                if (images[index]) return resolve(images[index]);
                if (abandonne) return resolve(null);

                let repondu = false;
                const repondre = (image) => {
                    if (repondu) return;
                    repondu = true;
                    clearTimeout(garde);
                    resolve(image || images[index] || null);
                };

                // 6 s : au-delà, on rend la main et la section reste sur son poster.
                const garde = setTimeout(() => repondre(null), 6000);

                if (!attentes.has(index)) attentes.set(index, []);
                attentes.get(index).push(repondre);
            });
        },

        detruire() {
            abandonne = true;
            // On réveille avant de vider : une attente laissée en suspens retiendrait son
            // appelant, et donc tout le module, pour la durée de vie de la page.
            attentes.forEach((liste) => liste.forEach((resoudre) => resoudre(null)));
            attentes.clear();

            for (let i = 0; i < total; i++) {
                if (images[i]) images[i].src = '';
                images[i] = null;
            }
            enCours.clear();
            vaguesDemandees.clear();
        },
    };
}
