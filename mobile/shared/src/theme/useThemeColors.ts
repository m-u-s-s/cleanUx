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
 * LES DEUX THÈMES SONT LE MÊME MONDE, VU DES DEUX CÔTÉS DE LA SURFACE DE L'EAU. En clair on est
 * au-dessus : maillage pâle qui dérive, verre blanc dépoli. En sombre on est en dessous : la
 * lumière tombe du haut en caustiques et s'éteint vers le bas. Voir `colors.mode.maree`.
 *
 * CE QUE CE CHANGEMENT ANNULE. Le commentaire d'origine disait « le mode clair n'est pas touché
 * par le traitement verre : un prestataire en plein soleil a besoin de contraste, pas de
 * translucidité ». La règle reste vraie, la conclusion ne l'était pas : un voile blanc à 0,72
 * posé sur le maillage rend #f5f7fb, soit 14,8:1 sous le texte. Le verre clair ne coûte pas de
 * contraste — il en coûtait tant qu'on le croyait gris.
 */
export function useThemeColors() {
  const { colorScheme } = useColorScheme();
  const isDark = colorScheme === 'dark';
  const nuit = colors.mode.maree.profondeur;
  const jour = colors.mode.maree.givre;

  return {
    /**
     * Évite que chaque composant réimporte `useColorScheme` et compare la chaîne lui-même —
     * autant d'occasions de se tromper qu'il y a de composants.
     */
    isDark,

    // ── Surfaces et texte ───────────────────────────────────────────────────────────────────
    bg: isDark ? nuit.abysse : jour.page,

    /**
     * Le fond d'un conteneur PLEINE PAGE — et rien d'autre.
     *
     * En sombre il vaut `transparent` : la toile nuit est montée UNE FOIS à la racine par
     * `NightShell`, et tout écran qui repeint son fond la masque. La régression est particulièrement
     * sournoise parce que la couleur masquante est presque la même : sur une capture, on ne voit
     * rien ; sur l'appareil, les gouttes ont disparu.
     *
     * En clair il vaut `bg`, à l'identique de ce qui existait.
     */
    page: isDark ? 'transparent' : jour.page,

    card: isDark ? nuit.panneau : '#ffffff',

    /**
     * La carte DISCRÈTE — celle qui, en clair, se confond presque avec la page.
     *
     * Beaucoup de cartes portaient `colors.surface[50]`, soit la couleur de la page elle-même. La
     * migration les a traduites en `bg`, ce qui reste juste en clair mais donne en sombre une carte
     * exactement de la couleur du fond : un rectangle invisible. Ce jeton garde le clair identique
     * et relève le sombre au panneau.
     */
    cardSubtle: isDark ? nuit.panneau : jour.page,
    cardElevated: isDark ? nuit.panneauHaut : '#ffffff',
    text: isDark ? nuit.texte : jour.texte,
    textSecondary: isDark ? nuit.muted : jour.muted,
    /* Opaque des deux cotes : un rgba se compose avec ce qu'il y a dessous, et le
       garde-fou mesurait alors une couleur que personne n'affiche. */
    textMuted: isDark ? '#8fb4bd' : '#567082',
    border: isDark ? 'rgba(232, 246, 246, 0.12)' : 'rgba(22, 36, 43, 0.10)',
    inputBg: isDark ? 'rgba(232, 246, 246, 0.07)' : 'rgba(255, 255, 255, 0.66)',

    // ── La matière verre ────────────────────────────────────────────────────────────────────
    /*
     * `glass` porte un PLANCHER d'opacité, et c'est le jeton le plus délicat du lot.
     *
     * Le contraste d'un texte posé sur du verre est garanti par le VOILE, pas par le flou : un
     * flou mélange les pixels, il ne les fonce pas. Sous ce plancher, le texte devient illisible
     * dès que quelque chose de clair défile derrière — et ça n'arrive qu'en usage réel, jamais
     * sur une maquette au fond fixe.
     */
    glass: isDark ? 'rgba(47, 217, 197, 0.07)' : 'rgba(255, 255, 255, 0.72)',
    glassStrong: isDark ? 'rgba(47, 217, 197, 0.11)' : 'rgba(255, 255, 255, 0.86)',
    glassBorder: isDark ? 'rgba(232, 246, 246, 0.16)' : 'rgba(91, 127, 166, 0.18)',
    textOnGlass: isDark ? nuit.texte : jour.texte,
    mutedOnGlass: isDark ? nuit.muted : jour.muted,

    /**
     * Le texte posé SUR la couleur de marque — un bouton plein, un badge.
     *
     * IDENTIQUE DANS LES DEUX THÈMES, et c'est voulu : l'indigo de marque ne change pas d'un mode à
     * l'autre, donc ce qui se pose dessus ne change pas non plus. Le jeton existe pour qu'on cesse
     * d'écrire `'#ffffff'` à la main — trois écrans venaient de le faire, et le garde-fou les a
     * signalés avant qu'ils atteignent le mode sombre.
     */
    textOnBrand: '#ffffff',

    /*
     * L'ACCENT DE MARQUE — l'ambre, la meme valeur que `--cx-amber` du web.
     *
     * Le natif employait `colors.brand[500]`, un indigo, la ou le web porte l'ambre depuis
     * toujours : les deux plateformes n'avaient pas la meme couleur de marque. Ce jeton les
     * reunit sans toucher a `colors.brand`, que d'autres ecrans lisent encore.
     *
     * `textOnAccent` est SOMBRE, et ce n'est pas un oubli : du blanc sur de l'ambre tombe
     * sous 2:1. Le meme calcul vaut sur le web, ou le bouton d'accent porte `#241603`.
     */
    accent: '#ffb648',
    accentDeep: '#ff8a3d',
    textOnAccent: '#241603',

    /*
     * LES STATUTS EN COULEUR PLEINE. `tint.*` sont des VOILES a poser en fond ; ceux-ci
     * portent du texte, et le cran differe par theme parce que la surface differe.
     *
     * LE ROUGE A CHANGE DE CRAN EN PASSANT A PROFONDEUR : le panneau teal est plus clair que
     * l'ancien panneau indigo, `danger.500` y tombe a 3,98. Le 400 rend 5,41.
     *
     *   sur #f5f7fb (le verre clair, pire cas)   5,11 / 4,68 / 4,50
     *   sur #082a3a (le panneau de Profondeur)   5,90 / 6,97 / 5,41
     */
    success: isDark ? colors.success[500] : colors.success[700],
    warning: isDark ? colors.warning[500] : colors.warning[700],
    danger: isDark ? colors.danger[400] : colors.danger[600],

    /*
     * LA MARQUE, QUAND ELLE PORTE DU TEXTE. Aucun indigo unique ne tient sur les deux fonds :
     * `brand.500` echoue des deux cotes. Ce jeton double `brand`, il ne le remplace pas.
     *
     *   sur #f5f7fb   brand.600 = 5,86        sur #082a3a   brand.400 = 5,02
     */
    brandText: isDark ? colors.brand[400] : colors.brand[600],

    /** La lumiere dans l'eau. Absente en clair : on est deja au-dessus de la surface. */
    glow: isDark ? 'rgba(47, 217, 197, 0.26)' : 'transparent',

    /** La couleur de la lumiere dans l'eau : traces vivants, caustiques, etats en cours. */
    caustique: isDark ? nuit.caustique : '#2f8fa6',

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
