/* ============================================================================
   RevealText — composant ergonomique au-dessus de useTextReveal.
   ----------------------------------------------------------------------------
   <RevealText as="h1" className="ctr-display" stagger={0.1}>
       Texte à révéler
   </RevealText>

   `children` DOIT être du texte brut (le découpage opère sur textContent ; le
   markup inline serait perdu — utilisez plusieurs RevealText pour ça).
   Toutes les options de useTextReveal passent en props (granularity, trigger,
   threshold, once, stagger, duration, delay, distance, clip, easing).
   ========================================================================= */
import React from 'react';
import { useTextReveal } from '../hooks/useTextReveal';

export function RevealText({
    as: Tag = 'span',
    className = '',
    children,
    granularity,
    trigger,
    threshold,
    once,
    stagger,
    duration,
    delay,
    distance,
    clip,
    easing,
    ...rest
}) {
    const ref = useTextReveal({
        granularity,
        trigger,
        threshold,
        once,
        stagger,
        duration,
        delay,
        distance,
        clip,
        easing,
    });

    return (
        <Tag ref={ref} className={['ctr', className].filter(Boolean).join(' ')} {...rest}>
            {children}
        </Tag>
    );
}

export default RevealText;
