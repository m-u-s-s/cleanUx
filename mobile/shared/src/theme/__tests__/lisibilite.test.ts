/**
 * AUCUNE COULEUR DE TEXTE NE DOIT TOMBER SOUS LE SEUIL — dans l'un OU l'autre theme.
 *
 * Le raisonnement d'origine ne portait que sur la nuit : « success.600 sur un fond de nuit
 * passe sous le seuil, success.500 le tient ». Personne n'avait fait le calcul dans l'autre
 * sens, et sur le blanc des cartes `success.600` rendait 3,77 et `warning.600` 3,18.
 *
 * LES SURFACES NE SONT PLUS RECOPIEES ICI. Elles etaient ecrites en dur — `#111a2e` — et
 * personne ne les mettait a jour en meme temps que la palette : chaque changement de palette les a
 * rendues fausses d'un coup a chaque changement de palette, et le test aurait continue de passer
 * en mesurant un fond qui
 * n'existe plus. Elles viennent maintenant de `surfacesDeReference`, avec la palette.
 *
 * En clair, la surface de reference n'est PAS le blanc : c'est le voile de verre le plus fin
 * pose sur le point le plus sombre du maillage. C'est la que le texte a le moins de marge.
 */
import { colors, surfacesDeReference } from '../colors';

const { jour: JOUR, nuit: NUIT } = surfacesDeReference;

const SEUIL = 4.5;

const luminance = (hex: string): number => {
  const h = hex.replace('#', '');
  const canal = (i: number) => {
    const v = parseInt(h.slice(i, i + 2), 16) / 255;

    return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
  };

  return 0.2126 * canal(0) + 0.7152 * canal(2) + 0.0722 * canal(4);
};

const contraste = (a: string, b: string): number => {
  const [x, y] = [luminance(a), luminance(b)];

  return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
};

const iceberg = colors.mode.iceberg;

// Ce que `useThemeColors` rend pour chaque jeton : [nom, valeur en clair, valeur en sombre].
const JETONS: Array<[string, string, string]> = [
  ['text', iceberg.emerge.texte, iceberg.immerge.texte],
  ['textSecondary', iceberg.emerge.muted, iceberg.immerge.muted],
  ['textMuted', '#4e6e85', '#84a4ba'],
  ['success', colors.success[700], colors.success[500]],
  ['warning', colors.warning[800], colors.warning[500]],
  ['danger', colors.danger[700], colors.danger[400]],
  ['brandText', colors.brand[600], colors.brand[400]],
  ['accent', '#241603', '#ffb648'],
  ['argent', colors.warning[800], colors.accent.amber],
];

describe('les couleurs de texte du theme', () => {
  it.each(JETONS)('%s tient le seuil dans les deux themes', (_nom, clair, sombre) => {
    expect(contraste(clair, JOUR)).toBeGreaterThanOrEqual(SEUIL);
    expect(contraste(sombre, NUIT)).toBeGreaterThanOrEqual(SEUIL);
  });

  /**
   * L'AMBRE EN CLAIR NE SE POSE PAS SUR DU VERRE — il se pose SOUS du texte sombre.
   *
   * `accent` ci-dessus est teste avec `textOnAccent` du cote clair, ce qui n'est pas une faute
   * de recopie : sur fond clair l'ambre est un FOND, jamais une couleur de texte. Le sens de la
   * mesure suit l'emploi reel.
   */
  it('l’ambre porte son texte sombre', () => {
    expect(contraste('#241603', '#ffb648')).toBeGreaterThanOrEqual(SEUIL);
  });

  /*
   * TEMOIN. Sans lui, ce fichier passerait au vert si `contraste()` rendait toujours un grand
   * nombre — il mesurerait alors sa propre panne, pas la palette.
   */
  it('temoin : le calcul sait reconnaitre un couple illisible', () => {
    // Les valeurs ecartees, et la raison de chaque cran. Elles DOIVENT echouer.
    expect(contraste(colors.success[600], JOUR)).toBeLessThan(SEUIL);
    expect(contraste(colors.warning[600], JOUR)).toBeLessThan(SEUIL);
    expect(contraste(colors.danger[500], NUIT)).toBeLessThan(SEUIL);

    // Et un couple evident doit passer, sinon le calcul est casse dans l'autre sens.
    expect(contraste('#000000', '#ffffff')).toBeCloseTo(21, 0);
  });

  /**
   * LA RAMPE PRIMAIRE A LA BONNE POLARITE.
   *
   * Une rampe utilisable se comporte d'une seule facon : ses crans CLAIRS tiennent sur la nuit et
   * echouent sur le jour, ses crans SOMBRES font l'inverse. Une rampe qui passerait partout
   * n'aurait pas assez d'amplitude, et une qui echouerait partout serait mal centree — dans les
   * deux cas, choisir un cran deviendrait un tirage au sort.
   *
   * Ce test l'a attrape une fois : le temoin d'origine affirmait que `brand[500]` echouait sur la
   * nuit. C'etait vrai de l'indigo, ca ne l'est plus du teal, et l'assertion mesurait donc une
   * propriete de l'ancienne palette.
   */
  it('la rampe primaire s’inverse d’un theme a l’autre', () => {
    // Les crans clairs : lisibles sur la nuit, perdus sur le jour.
    expect(contraste(colors.brand[400], NUIT)).toBeGreaterThanOrEqual(SEUIL);
    expect(contraste(colors.brand[400], JOUR)).toBeLessThan(SEUIL);

    // Les crans sombres : l'inverse exactement.
    expect(contraste(colors.brand[800], JOUR)).toBeGreaterThanOrEqual(SEUIL);
    expect(contraste(colors.brand[800], NUIT)).toBeLessThan(SEUIL);
  });

  /**
   * LE PIRE CAS DU VERRE CLAIR SE CALCULE, il ne se decrete pas.
   *
   * `surfacesDeReference.jour` affirme qu'un voile blanc a 0,72 pose sur le point le plus sombre
   * de la scene claire rend cette valeur. Si la scene s'assombrit, elle ment, et tout le reste du
   * fichier mesure une surface plus claire que la vraie.
   */
  it('la surface de reference du clair est bien le voile pose sur le plancher', () => {
    const voile = 0.94;
    const fond = iceberg.emerge.plancher.replace('#', '');
    const compose = [0, 2, 4]
      .map(i => Math.round(parseInt(fond.slice(i, i + 2), 16) * (1 - voile) + 255 * voile))
      .map(v => v.toString(16).padStart(2, '0'))
      .join('');

    expect(`#${compose}`).toBe(JOUR);
  });

  /**
   * LA SURFACE DE NUIT SE CALCULE AUSSI, et par la meme regle : le voile le plus fin pose sur le
   * point de la scene qui laisse le moins de marge. En nuit c'est le point le plus CLAIR.
   */
  it('la surface de reference de la nuit est bien le voile pose sur le plafond', () => {
    const voile = 0.9;
    const encre = [7, 25, 42];
    const fond = iceberg.immerge.plafond.replace('#', '');
    const compose = [0, 2, 4]
      .map((i, k) => Math.round(parseInt(fond.slice(i, i + 2), 16) * (1 - voile) + encre[k]! * voile))
      .map(v => v.toString(16).padStart(2, '0'))
      .join('');

    expect(`#${compose}`).toBe(NUIT);
  });

  /**
   * LE TEXTE POSE A NU SUR LA TOILE N'A PAS DE VOILE POUR LE PROTEGER.
   *
   * Tout le reste de ce fichier mesure le texte sur du VERRE. Un titre d'ecran, une etiquette de
   * section, une salutation : ceux-la touchent la scene directement, et la couronne de l'iceberg
   * est ce qu'elle a de plus clair. Le plafond de la scene est CHOISI pour ce cas — c'est lui qui
   * l'a fixe, pas le verre.
   *
   * LA REGLE QUI EN DECOULE : RIEN NE SE POSE A NU SUR LA TOILE. Le fond va du noir de la quille
   * au blanc des caustiques ; aucune couleur de texte ne tient sur les deux. Tout texte s'assoit
   * sur une plaque de verre — c'est ce que fait la planche, et c'est ce que le balayage impose.
   */
  it('AUCUN texte ne tient a nu sur la scene, dans aucun des deux themes', () => {
    // Les deux extremes du rendu. Aucune couleur de texte ne survit a l'un ET a l'autre.
    expect(contraste(iceberg.immerge.texte, iceberg.immerge.plafond)).toBeLessThan(SEUIL);
    expect(contraste(iceberg.emerge.texte, iceberg.emerge.plancher)).toBeLessThan(SEUIL);
  });

  /*
   * TEMOIN. Le test ci-dessus serait vert si `contraste()` rendait toujours un petit nombre. Les
   * memes couleurs, posees sur la surface de reference de LEUR theme, doivent tenir largement.
   */
  it('temoin : ces memes couleurs tiennent sur le verre', () => {
    expect(contraste(iceberg.immerge.texte, NUIT)).toBeGreaterThanOrEqual(SEUIL);
    expect(contraste(iceberg.emerge.texte, JOUR)).toBeGreaterThanOrEqual(SEUIL);
  });

  /**
   * TEMOIN — LE PLAFOND EST BIEN PLUS DUR QUE LE PANNEAU.
   *
   * La nuit a longtemps pris le panneau pour reference. Y revenir redonnerait du vert a des
   * couples que la couronne de l'iceberg ne tient plus.
   */
  it('temoin : le plafond de la scene est plus clair que le panneau', () => {
    expect(contraste('#000000', iceberg.immerge.plafond)).toBeGreaterThan(
      contraste('#000000', iceberg.immerge.panneau),
    );
  });

  /**
   * TEMOIN — LE PLANCHER EST BIEN PLUS DUR QUE LE CIEL.
   *
   * Le test au-dessus resterait vert si `plancher` valait `maillageSombre` : il ne verifie qu'une
   * composition. Or c'est exactement la regression a craindre — revenir a la surface d'avant
   * l'iceberg, plus claire, et redonner du vert a des couples qui ne tiennent plus.
   */
  it('temoin : le plancher de la scene est plus sombre que le ciel', () => {
    expect(contraste('#ffffff', iceberg.emerge.plancher)).toBeGreaterThan(
      contraste('#ffffff', iceberg.emerge.maillageSombre),
    );
  });
});
