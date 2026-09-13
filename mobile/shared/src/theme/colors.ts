export const colors = {
  /**
   * LA RAMPE PRIMAIRE EST LA GLACE DEPUIS ICEBERG, et non plus indigo.
   *
   * Repointer la rampe plutot que corriger les appels : l'indigo ne vivait pas dans les jetons
   * mais dans 89 lectures directes de `colors.brand` reparties dans les ecrans. Un bouton indigo
   * pose sur Profondeur se lit comme un morceau rapporte, et corriger 89 sites un par un aurait
   * laisse passer ceux qu'on n'a pas vus.
   *
   * Elle se comporte comme une rampe doit se comporter : les crans clairs tiennent sur la nuit,
   * les crans sombres tiennent sur le jour.
   *
   *   sur #f4f9fc (jour)   400 = 2,48   600 = 5,51   800 = 9,42
   *   sur #0c2a3e (nuit)   400 = 5,65   600 = 2,54   800 = 1,48
   */
  brand: {
    50: '#eff7fd', 100: '#d9ecf9', 200: '#b6dbf3', 300: '#7fc4e8',
    400: '#4fa9d8', 500: '#2b87ba', 600: '#1c6b96', 700: '#185878',
    800: '#17475f', 900: '#163b4e', 950: '#0c2434',
  },
  surface: {
    50: '#fafafa', 100: '#f5f5f5', 200: '#e5e5e5', 300: '#d4d4d4',
    400: '#a3a3a3', 500: '#737373', 600: '#525252', 700: '#404040',
    800: '#262626', 900: '#171717', 950: '#0a0a0a',
  },
  success: { 50: '#ecfdf5', 500: '#10b981', 600: '#059669', 700: '#047857' },
  /* Le 800 existe pour le seul ambre en TEXTE sur le verre clair : depuis que la scene claire
     porte l'iceberg, le 700 y rend 4,47 — sous le seuil de trois centiemes. */
  warning: { 50: '#fffbeb', 500: '#f59e0b', 600: '#d97706', 700: '#b45309', 800: '#92400e' },
  /* Le 400 existe pour le seul cas du rouge en TEXTE sur le panneau de Profondeur : le 500 y
     rend 3,98, sous le seuil. Le panneau teal est plus clair que l'ancien panneau indigo. */
  danger:  { 50: '#fef2f2', 400: '#f87171', 500: '#ef4444', 600: '#dc2626', 700: '#b91c1c' },
  accent: { amber: '#ffb648', amberDeep: '#ff8a3d', cyan: '#4fe3d6', violet: '#8b7bff' },
  mode: {
    tool: { ink: '#1a2436', muted: '#5b6b85', card: 'rgba(255,255,255,0.9)', cardStrong: 'rgba(255,255,255,0.96)' },
    showcase: { night: '#070b14', nightSoft: '#0c1322', panel: '#111a2e', text: '#e8eefc', muted: '#93a4c6' },

    /**
     * ICEBERG — le meme objet, vu de deux profondeurs.
     *
     * Le theme CLAIR est la partie emergee : dix pour cent, sous un ciel blanc. Le theme SOMBRE
     * est la masse immergee : la meme glace, en entier, dans l'eau noire. Basculer ne change pas
     * le decor — ca change l'endroit d'ou l'on regarde.
     *
     * L'ambre traverse les deux, seule couleur chaude, reservee a l'argent. Sur de la glace, elle
     * est encore plus seule qu'ailleurs.
     */
    iceberg: {
      immerge: {
        /** Le fond de page : `eau` pres de la surface, `abysse` en bas. */
        eau: '#0a2033',
        abysse: '#04101c',
        /** Le panneau. Sa clarte est CONTRAINTE : tous les jetons semantiques doivent y tenir. */
        panneau: '#0c2a3e',
        panneauHaut: '#103850',
        texte: '#eaf3f9',
        muted: '#9fbdd1',
        /** La glace eclairee — l'action, les traces vivants, la couronne du modele. */
        glace: '#7fc4e8',
        /**
         * LE POINT LE PLUS CLAIR QUE LA SCENE NUIT PUISSE PRODUIRE — le pendant exact de
         * `emerge.plancher`. Ici le danger est inverse : un texte clair devient illisible sur une
         * facette trop lumineuse. `IcebergVolumetrique` l'applique comme PLAFOND.
         */
        plafond: '#4a7287',
      },
      emerge: {
        page: '#f4f8fb',
        /** Les deux extremes du ciel : le bleu froid en haut, le blanc franc en bas. */
        maillageClair: '#ffffff',
        maillageSombre: '#d9e8f3',
        texte: '#0b1a24',
        muted: '#4a6b84',
        /**
         * LE POINT LE PLUS SOMBRE QUE LA SCENE CLAIRE PUISSE PRODUIRE.
         *
         * Ce n'etait le maillage que tant que la scene n'avait pas d'objet. L'iceberg descend
         * plus bas : sa facette la moins eclairee, juste au-dessus de la flottaison, vaut cette
         * valeur — et `IcebergVolumetrique` l'applique comme PLANCHER, si bien que la borne est
         * exacte et non estimee. C'est elle que le verre clair doit rendre lisible.
         */
        plancher: '#b0d5f3',
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
 * DANS LES DEUX THÈMES : le voile de verre le plus FIN posé sur le point de la scène qui laisse
 * le moins de marge. En clair c'est le point le plus SOMBRE (`emerge.plancher`), en nuit le point
 * le plus CLAIR (`immerge.plafond`) — le texte y est clair, c'est la lumière qui le mange.
 *
 * En nuit, ce fut longtemps le panneau lui-même : c'était vrai tant que rien de plus clair ne
 * passait derrière une carte. L'iceberg a changé cela.
 */
export const surfacesDeReference = {
  jour: '#e9f3fc',
  nuit: '#1b3446',
} as const;
