/* ============================================================================
   splitText — découpe le texte d'un élément en LIGNES (et éventuellement MOTS),
   chaque unité enveloppée dans un masque overflow:hidden pour pouvoir la
   translater / clipper sans perturber les voisines.
   ----------------------------------------------------------------------------
   - Texte brut uniquement (titres, paragraphes). Le markup inline (gras, liens)
     n'est pas préservé — wrappez plutôt chaque segment.
   - Le découpage en lignes est MESURÉ (offsetTop) après une première passe en
     spans inline : le retour à la ligne reste donc identique au flux naturel
     -> aucun layout shift, et recalcul correct à toute largeur (responsive).
   - Idempotent & restaurable : le texte d'origine est mémorisé sur l'élément,
     ce qui permet de re-découper au resize puis de restaurer au démontage.
   ========================================================================= */

const ORIGINAL = '__ctrOriginalText';

/**
 * @param {HTMLElement} el
 * @param {'line'|'word'} granularity
 * @returns {{ targets: HTMLElement[], lines: HTMLElement[], words: HTMLElement[] }}
 */
export function splitText(el, granularity = 'line') {
    if (!el) return { targets: [], lines: [], words: [] };

    // Mémorise le texte pristine une seule fois (re-splits déterministes).
    if (el[ORIGINAL] == null) el[ORIGINAL] = el.textContent;
    const source = String(el[ORIGINAL]).replace(/\s+/g, ' ').trim();
    if (!source) return { targets: [], lines: [], words: [] };

    const tokens = source.split(' ');

    // 1) Passe de mesure : mots en spans inline -> wrapping naturel.
    el.textContent = '';
    const measured = [];
    tokens.forEach((word, i) => {
        const span = document.createElement('span');
        span.className = 'ctr-word';
        span.textContent = word;
        el.appendChild(span);
        measured.push(span);
        if (i < tokens.length - 1) el.appendChild(document.createTextNode(' '));
    });

    // 2) Regroupe les mots par offsetTop -> lignes.
    const linesData = [];
    let current = null;
    measured.forEach((span) => {
        const top = span.offsetTop;
        if (!current || Math.abs(top - current.top) > 1) {
            current = { top, words: [] };
            linesData.push(current);
        }
        current.words.push(span.textContent);
    });

    // 3) Reconstruit : ligne = masque(.ctr-line) > inner(.ctr-line__inner) > mots.
    el.textContent = '';
    const lines = [];
    const words = [];

    linesData.forEach((line) => {
        const mask = document.createElement('span');
        mask.className = 'ctr-line';
        const inner = document.createElement('span');
        inner.className = 'ctr-line__inner';

        line.words.forEach((word, j) => {
            if (granularity === 'word') {
                const wm = document.createElement('span');
                wm.className = 'ctr-word-mask';
                const wi = document.createElement('span');
                wi.className = 'ctr-word__inner';
                wi.textContent = word;
                wm.appendChild(wi);
                inner.appendChild(wm);
                words.push(wi);
            } else {
                const w = document.createElement('span');
                w.className = 'ctr-word';
                w.textContent = word;
                inner.appendChild(w);
                words.push(w);
            }
            if (j < line.words.length - 1) inner.appendChild(document.createTextNode(' '));
        });

        mask.appendChild(inner);
        el.appendChild(mask);
        lines.push(inner);
    });

    el.classList.add('ctr-split');
    return {
        targets: granularity === 'word' ? words : lines,
        lines,
        words,
    };
}

/** Restaure le texte d'origine (cleanup / avant re-split). */
export function restoreText(el) {
    if (!el) return;
    if (el[ORIGINAL] != null) {
        el.textContent = el[ORIGINAL];
        delete el[ORIGINAL];
    }
    el.classList.remove('ctr-split');
}

export default splitText;
