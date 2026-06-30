/* ============================================================================
   RevealImage — composant ergonomique au-dessus de useImageReveal.
   ----------------------------------------------------------------------------
   <RevealImage
       src="/images/hero.jpg" alt="…"
       effects={['clip', 'blur']} parallax={0.2}
       width={1200} height={800} className="rounded-2xl" />

   Rend un <figure class="ir"> contenant le <img> (loading lazy + decoding async
   par défaut). Toutes les options de useImageReveal passent en props : effects,
   parallax, once, threshold, rootMargin.
   ========================================================================= */
import React from 'react';
import { useImageReveal } from '../hooks/useImageReveal';

export function RevealImage({
    src,
    alt = '',
    effects = ['clip', 'fade'],
    parallax = 0,
    once = true,
    threshold = 0.2,
    rootMargin = '0px 0px -10% 0px',
    className = '',
    imgClassName = '',
    width,
    height,
    loading = 'lazy',
    decoding = 'async',
    sizes,
    srcSet,
    ...rest
}) {
    const ref = useImageReveal({ effects, parallax, once, threshold, rootMargin });

    return (
        <figure ref={ref} className={['ir', className].filter(Boolean).join(' ')} {...rest}>
            <img
                src={src}
                alt={alt}
                width={width}
                height={height}
                loading={loading}
                decoding={decoding}
                sizes={sizes}
                srcSet={srcSet}
                className={imgClassName || undefined}
            />
        </figure>
    );
}

export default RevealImage;
