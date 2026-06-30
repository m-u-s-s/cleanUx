/* ============================================================================
   CleanUx — Démo des hooks de révélation de texte cinématique.
   Îlot React monté dans #text-reveal-demo (page vitrine dev /premium-scroll).
   Montre useTextReveal / RevealText avec différentes granularités et triggers.
   ========================================================================= */
import React from 'react';
import { createRoot } from 'react-dom/client';
import { RevealText } from './components/RevealText';
import { RevealImage } from './components/RevealImage';
import { useTextReveal } from './hooks/useTextReveal';

function Demo() {
    // Exemple d'usage du hook brut (sans le composant).
    const rawRef = useTextReveal({ granularity: 'word', stagger: 0.04, delay: 0.15, distance: '0.6em' });

    return (
        <div className="trd">
            <p className="trd-eyebrow">Cinematic text reveal · hooks React</p>

            {/* Lignes : cascade ligne par ligne (clip-path + translate + opacity) */}
            <RevealText as="h2" className="ctr-display trd-h" stagger={0.12} distance="0.5em">
                Un texte qui se révèle ligne après ligne, comme au cinéma
            </RevealText>

            {/* Mots : cascade plus granulaire, via le hook brut */}
            <p ref={rawRef} className="ctr ctr-lead trd-p">
                Chaque mot émerge derrière son masque, recadré au clip-path et fondu en opacité.
            </p>

            {/* Re-jeu à chaque entrée dans le viewport (once: false) */}
            <RevealText
                as="h3"
                className="ctr-headline trd-h"
                granularity="line"
                once={false}
                stagger={0.1}
            >
                Responsive : les lignes sont recalculées à chaque changement de largeur.
            </RevealText>

            {/* Voie React du moteur d'image reveal (même cœur que la voie vanilla) */}
            <RevealImage
                src="/images/og-cleanux.svg"
                alt="Démo RevealImage (React)"
                effects={['clip', 'blur', 'fade']}
                parallax={0.15}
                className="trd-img"
            />
        </div>
    );
}

const mount = document.getElementById('text-reveal-demo');
if (mount) {
    createRoot(mount).render(
        <React.StrictMode>
            <Demo />
        </React.StrictMode>
    );
}
