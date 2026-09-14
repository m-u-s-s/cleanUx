import React from 'react';
import { Ionicons } from '@expo/vector-icons';
import { colors } from '@/theme';

interface IconProps {
  name: keyof typeof Ionicons.glyphMap;
  size?: number;
  color?: string;
  /**
   * À ne donner QUE si l'icône porte du sens à elle seule — un bouton sans libellé.
   * Sans elle, l'icône est décorative et se tait : c'est le bon défaut.
   */
  accessibilityLabel?: string;
}

/**
 * UNE ICÔNE SANS ÉTIQUETTE SE TAIT, ELLE N'ÉPELLE PAS SON GLYPHE.
 *
 * Le repli était `accessibilityLabel={accessibilityLabel ?? name}` : les 81 `<Icon/>` du dépôt
 * n'en passent AUCUNE, et un lecteur d'écran francophone entendait donc « chevron-forward »,
 * « person-outline », « alert-circle-outline » — neuf, cinq et quatre fois par écran.
 *
 * Une icône décorative doit être MASQUÉE, pas étiquetée de son nom technique : le libellé utile
 * est presque toujours déjà porté par le texte ou le rôle du parent.
 */
export function Icon({ name, size = 24, color = colors.surface[600], accessibilityLabel }: IconProps) {
  const parlante = accessibilityLabel !== undefined && accessibilityLabel !== '';

  return (
    <Ionicons
      name={name}
      size={size}
      color={color}
      accessible={parlante}
      accessibilityRole={parlante ? 'image' : undefined}
      accessibilityLabel={parlante ? accessibilityLabel : undefined}
      accessibilityElementsHidden={!parlante}
      importantForAccessibility={parlante ? 'yes' : 'no-hide-descendants'}
    />
  );
}
