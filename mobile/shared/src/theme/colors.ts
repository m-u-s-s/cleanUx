export const colors = {
  brand: {
    50: '#eef2ff', 100: '#e0e7ff', 200: '#c7d2fe', 300: '#a5b4fc',
    400: '#818cf8', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca',
    800: '#3730a3', 900: '#312e81', 950: '#1e1b4b',
  },
  surface: {
    50: '#fafafa', 100: '#f5f5f5', 200: '#e5e5e5', 300: '#d4d4d4',
    400: '#a3a3a3', 500: '#737373', 600: '#525252', 700: '#404040',
    800: '#262626', 900: '#171717', 950: '#0a0a0a',
  },
  success: { 50: '#ecfdf5', 500: '#10b981', 600: '#059669', 700: '#047857' },
  warning: { 50: '#fffbeb', 500: '#f59e0b', 600: '#d97706', 700: '#b45309' },
  /* Le 400 existe pour le seul cas du rouge en TEXTE sur le panneau de Profondeur : le 500 y
     rend 3,98, sous le seuil. Le panneau teal est plus clair que l'ancien panneau indigo. */
  danger:  { 50: '#fef2f2', 400: '#f87171', 500: '#ef4444', 600: '#dc2626', 700: '#b91c1c' },
  accent: { amber: '#ffb648', amberDeep: '#ff8a3d', cyan: '#4fe3d6', violet: '#8b7bff' },
  mode: {
    tool: { ink: '#1a2436', muted: '#5b6b85', card: 'rgba(255,255,255,0.9)', cardStrong: 'rgba(255,255,255,0.96)' },
    showcase: { night: '#070b14', nightSoft: '#0c1322', panel: '#111a2e', text: '#e8eefc', muted: '#93a4c6' },

    /**
     * MARÉE — un seul système, vu des deux côtés de la surface de l'eau.
     *
     * Le thème clair est AU-DESSUS : un maillage pâle qui dérive, du verre blanc dépoli posé
     * dessus. Le thème sombre est EN DESSOUS : la lumière tombe du haut en caustiques, s'éteint
     * vers le bas, et le verre prend la teinte de l'eau.
     *
     * Ce ne sont pas deux palettes voisines, c'est la même idée retournée — d'où l'ambre commun :
     * la seule couleur chaude, des deux côtés, réservée à l'argent.
     */
    maree: {
      profondeur: {
        /** Le fond de page : `eau` en haut, `abysse` en bas. La lumière vient du dessus. */
        eau: '#04222e',
        abysse: '#01121a',
        /** Le panneau. Sa clarté est CONTRAINTE : plus sombre, l'ambre écrase ; plus clair, le rouge tombe. */
        panneau: '#082a3a',
        panneauHaut: '#0c3646',
        texte: '#e8f6f6',
        muted: '#9dc0c9',
        /** La couleur de la lumière dans l'eau — les caustiques, les tracés, les états vivants. */
        caustique: '#2fd9c5',
      },
      givre: {
        page: '#eef2f7',
        /** Les deux extrêmes du maillage. Le plus sombre est le pire cas du contraste. */
        maillageClair: '#f6f8fc',
        maillageSombre: '#dbe4f2',
        texte: '#16242b',
        muted: '#4a6b8f',
      },
    },
  },
} as const;

/**
 * LES SURFACES QUE LE TEXTE AFFRONTE VRAIMENT — la plus dure de chaque thème.
 *
 * Le garde-fou de contraste les recopiait en dur, et personne ne les mettait à jour en même
 * temps que la palette : un thème pouvait dériver sans que le test s'en aperçoive. Ici la
 * définition est unique, et `lisibilite.test.ts` la lit.
 *
 * En clair, ce n'est PAS le blanc : c'est le voile de verre le plus fin (0,72) posé sur le point
 * le plus sombre du maillage. C'est là que le texte a le moins de marge.
 */
export const surfacesDeReference = {
  jour: '#f5f7fb',
  nuit: colors.mode.maree.profondeur.panneau,
} as const;
