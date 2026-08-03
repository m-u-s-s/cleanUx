import { useColorScheme } from './useColorScheme';
import { colors } from './colors';

/**
 * La source unique des couleurs de l'interface.
 *
 * POURQUOI CE HOOK PORTE AUTANT DE JETONS. Le mode sombre existait déjà et ne fonctionnait pas :
 * sur 137 fichiers d'interface, 7 le consultaient. Les autres écrivaient `colors.surface[900]` —
 * du quasi-noir, sur un fond devenu sombre. Personne ne l'a fait exprès : il manquait simplement
 * un jeton pour la plupart des besoins, et inventer une couleur était plus court que d'en
 * chercher une. Le jeu ci-dessous vise à ne laisser aucune raison d'inventer.
 *
 * LES TEINTES DE NUIT VIENNENT DE `colors.mode.showcase`, la palette du travail éditorial. En
 * introduire une seconde ferait diverger deux définitions du même noir.
 *
 * LE MODE CLAIR N'EST PAS TOUCHÉ par le traitement verre : un prestataire en plein soleil a
 * besoin de contraste, pas de translucidité.
 */
export function useThemeColors() {
  const { colorScheme } = useColorScheme();
  const isDark = colorScheme === 'dark';
  const nuit = colors.mode.showcase;

  return {
    /**
     * Évite que chaque composant réimporte `useColorScheme` et compare la chaîne lui-même —
     * autant d'occasions de se tromper qu'il y a de composants.
     */
    isDark,

    // ── Surfaces et texte ───────────────────────────────────────────────────────────────────
    bg: isDark ? nuit.night : colors.surface[50],
    card: isDark ? nuit.panel : '#ffffff',
    cardElevated: isDark ? nuit.nightSoft : '#ffffff',
    text: isDark ? nuit.text : colors.surface[900],
    textSecondary: isDark ? nuit.muted : colors.surface[600],
    textMuted: isDark ? 'rgba(147, 164, 198, 0.72)' : colors.surface[400],
    border: isDark ? 'rgba(232, 238, 252, 0.10)' : colors.surface[200],
    inputBg: isDark ? 'rgba(232, 238, 252, 0.06)' : colors.surface[50],

    // ── La matière verre ────────────────────────────────────────────────────────────────────
    /*
     * `glass` porte un PLANCHER d'opacité, et c'est le jeton le plus délicat du lot.
     *
     * Le contraste d'un texte posé sur du verre est garanti par le VOILE, pas par le flou : un
     * flou mélange les pixels, il ne les fonce pas. Sous ce plancher, le texte devient illisible
     * dès que quelque chose de clair défile derrière — et ça n'arrive qu'en usage réel, jamais
     * sur une maquette au fond fixe.
     */
    glass: isDark ? 'rgba(232, 238, 252, 0.06)' : 'rgba(255, 255, 255, 0.72)',
    glassStrong: isDark ? 'rgba(232, 238, 252, 0.10)' : 'rgba(255, 255, 255, 0.86)',
    glassBorder: isDark ? 'rgba(232, 238, 252, 0.14)' : 'rgba(15, 23, 42, 0.08)',
    textOnGlass: isDark ? nuit.text : colors.surface[900],
    mutedOnGlass: isDark ? nuit.muted : colors.surface[600],

    /** La lueur de marque du fond nuit. Absente en clair : elle n'y aurait aucun sens. */
    glow: isDark ? 'rgba(99, 102, 241, 0.30)' : 'transparent',

    /*
     * LES TEINTES — des voiles sémantiques, à poser en FOND.
     *
     * Elles remplacent les extrémités claires des rampes (`colors.brand[50]` et consorts), qui
     * sont des neutres déguisés : un indigo quasi-blanc porte la marque sur fond clair, et rend
     * le texte invisible sur fond sombre. Un voile, lui, prend la couleur de ce qu'il recouvre et
     * fonctionne des deux côtés.
     *
     * L'opacité est plus forte en sombre : sur un fond nuit, un voile trop discret ne se
     * distingue plus du fond.
     */
    tint: {
      brand: isDark ? 'rgba(99, 102, 241, 0.22)' : 'rgba(99, 102, 241, 0.10)',
      success: isDark ? 'rgba(16, 185, 129, 0.20)' : 'rgba(16, 185, 129, 0.10)',
      warning: isDark ? 'rgba(245, 158, 11, 0.20)' : 'rgba(245, 158, 11, 0.12)',
      danger: isDark ? 'rgba(239, 68, 68, 0.20)' : 'rgba(239, 68, 68, 0.10)',
    },
  };
}

/**
 * Le jeu de jetons rendu par `useThemeColors()`.
 *
 * Exporté pour que les feuilles de style puissent devenir des FABRIQUES qui le prennent en
 * paramètre — `StyleSheet.create` étant évalué au chargement du module, c'est la seule façon
 * d'avoir des couleurs de thème dans une feuille sans les sortir une par une vers le JSX.
 */
export type ThemeTokens = ReturnType<typeof useThemeColors>;
