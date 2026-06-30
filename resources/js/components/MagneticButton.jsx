/* ============================================================================
   MagneticButton — bouton magnétique pour le curseur premium (parité React).
   ----------------------------------------------------------------------------
   Le curseur custom est un singleton global (resources/js/premium-cursor.js) qui
   capte les boutons magnétiques par DÉLÉGATION sur [data-magnetic]. Ce composant
   ne fait donc QUE poser les bons data-attributes — aucune logique d'animation
   ici (tout vit dans cursor-core.js, partagé avec la voie vanilla).

   <MagneticButton strength={0.4} text="Réserver" onClick={…}>
       <span data-magnetic-inner>Réserver</span>   // optionnel : parallaxe interne
   </MagneticButton>

   Marche aussi sans React : <button data-magnetic data-magnetic-strength="0.4">.
   ========================================================================= */
import React from 'react';

export function MagneticButton({
    as: Tag = 'button',
    strength,
    text, // -> data-cursor-text (label flottant au survol)
    inner = false, // enrobe les enfants dans un [data-magnetic-inner]
    className = '',
    children,
    ...rest
}) {
    return (
        <Tag
            data-magnetic=""
            data-magnetic-strength={strength != null ? String(strength) : undefined}
            data-cursor-text={text || undefined}
            className={className || undefined}
            {...rest}
        >
            {inner ? <span data-magnetic-inner="">{children}</span> : children}
        </Tag>
    );
}

export default MagneticButton;
