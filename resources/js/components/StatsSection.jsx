/* ============================================================================
   StatsSection — section statistiques animée (Framer Motion / `motion`).
   ----------------------------------------------------------------------------
   Features :
     · count up      — chaque chiffre s'incrémente de 0 → valeur (useMotionValue
                       + animate, écrit dans le DOM sans re-render)
     · progress bars — barre 0 → N % (motion.span width, ease premium)
     · cards responsive — grille auto-fit (CSS), s'adapte de 1 à N colonnes
     · stagger reveal — les cartes entrent en cascade (variants staggerChildren)
     · viewport trigger — démarre quand la section entre dans le viewport (useInView)

   A11y : prefers-reduced-motion (useReducedMotion) -> valeurs finales affichées
   immédiatement, aucune animation. Les chiffres sont dans le DOM (lisibles).

   Usage React :
     <StatsSection
        eyebrow="CleanUx en chiffres" title="La confiance, en données"
        stats={[{ value: 12500, suffix: '+', label: 'Missions', progress: 92 }]} />

   Usage Blade/Livewire (auto-mount) : voir resources/js/stats-section.jsx
   (<div data-stats-section> + <script type="application/json">{…}</script>).
   ========================================================================= */
import React, { useEffect, useMemo, useRef } from 'react';
import { motion, useInView, useMotionValue, animate, useReducedMotion } from 'motion/react';

const EASE = [0.16, 1, 0.3, 1];

const containerVariants = {
    hidden: {},
    show: { transition: { staggerChildren: 0.12, delayChildren: 0.08 } },
};
const cardVariants = {
    hidden: { opacity: 0, y: 28 },
    show: { opacity: 1, y: 0, transition: { duration: 0.6, ease: EASE } },
};

/* ---- Count up : 0 -> value (écrit le texte directement, zéro re-render) --- */
function CountUp({ value, decimals = 0, prefix = '', suffix = '', duration = 2, start, reduced }) {
    const ref = useRef(null);
    const mv = useMotionValue(0);
    const fmt = useMemo(
        () =>
            new Intl.NumberFormat(
                (typeof document !== 'undefined' && document.documentElement.lang) || undefined,
                { minimumFractionDigits: decimals, maximumFractionDigits: decimals }
            ),
        [decimals]
    );

    useEffect(() => {
        if (!start) return undefined;
        const node = ref.current;
        if (reduced) {
            if (node) node.textContent = prefix + fmt.format(value) + suffix;
            return undefined;
        }
        const unsub = mv.on('change', (v) => {
            if (node) node.textContent = prefix + fmt.format(v) + suffix;
        });
        const controls = animate(mv, value, { duration, ease: EASE });
        return () => {
            controls.stop();
            unsub();
        };
    }, [start, value, reduced, duration, prefix, suffix, fmt, mv]);

    return (
        <span ref={ref} className="cx-stat__num">
            {prefix + fmt.format(reduced ? value : 0) + suffix}
        </span>
    );
}

/* ---- Progress bar : 0 -> value% ----------------------------------------- */
function ProgressBar({ value, start, reduced }) {
    return (
        <div
            className="cx-stat__bar"
            role="progressbar"
            aria-valuenow={Math.round(value)}
            aria-valuemin={0}
            aria-valuemax={100}
        >
            <motion.span
                className="cx-stat__bar-fill"
                initial={{ width: 0 }}
                animate={{ width: start ? `${value}%` : 0 }}
                transition={reduced ? { duration: 0 } : { duration: 1.1, ease: EASE, delay: 0.15 }}
            />
        </div>
    );
}

export function StatsSection({ eyebrow, title, description, stats = [] }) {
    const ref = useRef(null);
    const reduced = useReducedMotion();
    const inView = useInView(ref, { once: true, amount: 0.35 });
    const start = inView || reduced; // reduced-motion -> tout est visible d'emblée

    return (
        <section ref={ref} className="cx-stats">
            {(eyebrow || title || description) && (
                <motion.header
                    className="cx-stats__head"
                    initial={reduced ? false : { opacity: 0, y: 20 }}
                    animate={start ? { opacity: 1, y: 0 } : { opacity: 0, y: 20 }}
                    transition={{ duration: 0.6, ease: EASE }}
                >
                    {eyebrow && <p className="cx-stats__eyebrow">{eyebrow}</p>}
                    {title && <h2 className="cx-stats__title">{title}</h2>}
                    {description && <p className="cx-stats__desc">{description}</p>}
                </motion.header>
            )}

            <motion.div
                className="cx-stats__grid"
                variants={containerVariants}
                initial={reduced ? 'show' : 'hidden'}
                animate={start ? 'show' : 'hidden'}
            >
                {stats.map((s, i) => {
                    const progress =
                        s.progress != null ? Math.max(0, Math.min(100, Number(s.progress))) : null;
                    return (
                        <motion.article key={i} className="cx-stat" variants={cardVariants}>
                            {s.icon && (
                                <span className="cx-stat__icon" aria-hidden="true">
                                    {s.icon}
                                </span>
                            )}
                            <CountUp
                                value={Number(s.value) || 0}
                                decimals={s.decimals || 0}
                                prefix={s.prefix || ''}
                                suffix={s.suffix || ''}
                                duration={s.duration || 2}
                                start={start}
                                reduced={reduced}
                            />
                            {s.label && <p className="cx-stat__label">{s.label}</p>}
                            {progress != null && (
                                <ProgressBar value={progress} start={start} reduced={reduced} />
                            )}
                            {s.hint && <p className="cx-stat__hint">{s.hint}</p>}
                        </motion.article>
                    );
                })}
            </motion.div>
        </section>
    );
}

export default StatsSection;
