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
 * LES DEUX THÈMES SONT LE MÊME ICEBERG, VU DE DEUX PROFONDEURS. En clair on est au-dessus :
 * la partie émergée sous un ciel blanc. En sombre on est dessous : la masse entière dans
 * l'eau noire. Voir `colors.mode.iceberg`.
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
  const nuit = colors.mode.iceberg.immerge;
  const jour = colors.mode.iceberg.emerge;

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
     * TRANSPARENT DES DEUX CÔTÉS. La toile est montée UNE FOIS à la racine par `NightShell` et
     * elle rend maintenant dans les deux thèmes ; tout conteneur qui repeint son fond la masque
     * entièrement. La régression est sournoise parce que la couleur masquante est presque la même :
     * sur une capture, la page a l'air juste ; l'iceberg, lui, a disparu.
     *
     * Pour un aplat opaque assumé — une carte, une feuille modale — c'est `card` qu'il faut.
     */
    page: 'transparent',

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
    textMuted: isDark ? '#84a4ba' : '#4e6e85',
    border: isDark ? 'rgba(234, 243, 249, 0.12)' : 'rgba(11, 26, 36, 0.10)',
    inputBg: isDark ? 'rgba(234, 243, 249, 0.07)' : 'rgba(255, 255, 255, 0.66)',

    // ── La matière verre ────────────────────────────────────────────────────────────────────
    /*
     * `glass` porte un PLANCHER d'opacité, et c'est le jeton le plus délicat du lot.
     *
     * Le contraste d'un texte posé sur du verre est garanti par le VOILE, pas par le flou : un
     * flou mélange les pixels, il ne les fonce pas. Sous ce plancher, le texte devient illisible
     * dès que quelque chose de clair défile derrière — et ça n'arrive qu'en usage réel, jamais
     * sur une maquette au fond fixe.
     *
     * LE VERRE NUIT EST FUMÉ, PLUS TEINTÉ. Le voile était un bleu CLAIR à 0,13 : il ne fonçait
     * rien, et ne tenait que parce que la toile d'alors était sombre partout. L'iceberg passe
     * maintenant derrière les cartes, et sur sa couronne `textMuted` tombait à 1,39. Le voile
     * fonce dans les deux thèmes désormais — blanc sur le jour, encre sur la nuit — et laisse
     * L'OPACITÉ SE DÉDUIT DE LA SCÈNE, elle ne se choisit pas. Le fond est maintenant un rendu
     * photoréaliste qui va du noir de la quille au blanc des caustiques : le voile doit tenir sur
     * ces deux extrêmes-là. C'est le prix du rendu de la planche, et c'est aussi ce que montre la
     * planche elle-même — ses cartes sont des plaques givrées denses, pas des vitres.
     */
    glass: isDark ? 'rgba(7, 25, 42, 0.90)' : 'rgba(255, 255, 255, 0.94)',
    glassStrong: isDark ? 'rgba(7, 25, 42, 0.94)' : 'rgba(255, 255, 255, 0.96)',
    glassBorder: isDark ? 'rgba(200, 232, 250, 0.30)' : 'rgba(74, 107, 132, 0.20)',
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
     * LE ROUGE GARDE SON CRAN CLAIR EN SOMBRE : le panneau immerge est plus clair que l'ancien
     * panneau indigo, `danger.500` y tomberait sous le seuil. Le 400 rend 5,36.
     *
     * LE JOUR A DESCENDU D'UN CRAN pour l'ambre et le rouge. La scene claire porte maintenant
     * l'iceberg, dont la facette la moins eclairee est plus sombre que le maillage du ciel :
     * `warning.700` y rendait 4,47 et `danger.600` 4,30, tous deux sous le seuil.
     *
     *   sur #e9f3fc (le verre clair, pire cas)   4,88 / 6,31 / 5,76
     *   sur #0c2a3e (le panneau immerge)         5,84 / 6,90 / 5,36
     */
    success: isDark ? colors.success[500] : colors.success[700],
    warning: isDark ? colors.warning[500] : colors.warning[800],
    danger: isDark ? colors.danger[400] : colors.danger[700],

    /*
     * LE TEXTE D'ACTION — un lien, une entree cliquable. Il vaut `action` : deux couleurs
     * differentes pour la meme intention se lisent comme deux intentions.
     *
     *   sur #f4f9fc   #0b6f8f = 5,37        sur #0c2a3e   #7fc4e8 = 7,74
     */
    brandText: isDark ? '#7fc4e8' : '#0b6f8f',

    /** La lumiere dans l'eau. Absente en clair : on est deja au-dessus de la surface. */
    glow: isDark ? 'rgba(127, 196, 232, 0.24)' : 'transparent',

    /** La glace eclairee : traces vivants, etats en cours, couronne du modele. */
    glace: isDark ? nuit.glace : '#2f7f9e',

    /*
     * L'ACTION PRINCIPALE PREND LA LUMIERE DU MONDE, et non l'indigo de marque.
     *
     * Un bouton indigo pose sur Profondeur se lit comme un morceau rapporte : c'est la premiere
     * chose qu'on voit de l'ecran, et la seule couleur qui n'appartient a rien. Le bleu de la
     * glace est deja celui de ce qui vit — un trajet, une mission en cours.
     *
     * DEUX VALEURS, parce qu'aucune ne tient des deux cotes : #7fc4e8 rend 1,26 sur la page
     * claire, un bouton y serait invisible. Le clair prend donc un bleu profond.
     *
     *   sombre  #7fc4e8 sur l'abysse 10,00   texte #052230 dessus 8,58
     *   clair   #0b6f8f sur la page   5,34   texte blanc dessus  5,70
     */
    action: isDark ? '#7fc4e8' : '#0b6f8f',
    textOnAction: isDark ? '#052230' : '#ffffff',

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
      brand: isDark ? 'rgba(127, 196, 232, 0.20)' : 'rgba(43, 135, 186, 0.12)',
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
