import React, { useMemo } from 'react';
import { Group, LinearGradient, Rect, Vertices, vec } from '@shopify/react-native-skia';
import { useDerivedValue, type SharedValue } from 'react-native-reanimated';
import { colors } from '@/theme';
import { construireIceberg, ordreDeDessin } from './icebergMaillage';

/**
 * L'ICEBERG, RENDU EN VOLUME.
 *
 * Un maillage projeté et éclairé sommet par sommet, dessiné par Skia. Voir `icebergMaillage.ts`
 * pour la géométrie et pour la raison de ne pas avoir pris three.js.
 *
 * LA LUMIÈRE VIENT DE LA SURFACE, TOUJOURS. C'est ce qui fait lire « iceberg » plutôt que
 * « rocher » : la couronne est éclatante, la quille s'éteint avec la profondeur, et une part de
 * la clarté ne vient pas de l'angle de la face mais de l'ÉPAISSEUR traversée — c'est ce qu'on
 * appelle la diffusion sous la surface, et c'est ce qui distingue la glace de la pierre.
 *
 * LE THÈME NE CHANGE PAS L'OBJET, il change le cadrage. En clair on regarde la couronne, posée
 * haut dans l'écran, le reste sortant par le bas. En sombre on prend tout, la quille comprise.
 */

/** La hauteur du modèle qui coïncide avec la surface de l'eau — la taille de l'iceberg. */
const LIGNE_D_EAU = 0.03;

/**
 * LE PLANCHER DU THÈME CLAIR, APPLIQUÉ ET NON ESPÉRÉ.
 *
 * Le garde-fou de contraste mesure le texte sur `surfacesDeReference.jour`, qui est le voile de
 * verre le plus fin posé sur le point le plus sombre de la scène. Sans cette borne, la quille
 * descend bien plus bas que la palette ne l'annonce, et le garde-fou mesure alors une surface qui
 * n'existe plus : vert au test, illisible sur l'appareil.
 */
const PLANCHER_CLAIR = decomposer(colors.mode.iceberg.emerge.plancher);

/** Le pendant en nuit : au-dessus de cette valeur, le texte clair se perd sur la glace. */
const PLAFOND_NUIT = decomposer(colors.mode.iceberg.immerge.plafond);

function decomposer(hex: string): [number, number, number] {
  const h = hex.replace('#', '');

  return [0, 2, 4].map(i => parseInt(h.slice(i, i + 2), 16)) as [number, number, number];
}

interface Props {
  /** 0 → 1, un tour complet. Figée à 0 quand le mouvement est réduit. */
  phase: SharedValue<number>;
  largeur: number;
  hauteur: number;
  sombre: boolean;
}

export function IcebergVolumetrique({ phase, largeur, hauteur, sombre }: Props) {
  const triangles = useMemo(() => construireIceberg(), []);

  /*
   * LE CADRAGE, MESURÉ SUR L'APPAREIL.
   *
   * Le modèle est large de 1,24 unité à la ligne de flottaison et haut de 2,5 : l'échelle se règle
   * donc sur la LARGEUR, sinon un écran étroit le fait sortir des deux côtés. À 0,30 de la hauteur
   * il occupe 371 dp de large sur les 448 d'un téléphone courant — la marge se voit, et c'est elle
   * qui donne l'échelle du bloc.
   *
   * Le thème ne change que le point de vue : en sombre on prend la quille, en clair la mer la noie.
   */
  const echelle = hauteur * (sombre ? 0.32 : 0.3);
  const centreX = largeur * 0.5;
  /* En clair on cale d'abord la LIGNE D'EAU aux deux tiers de l'écran, et le centre s'en déduit. */
  const horizon = hauteur * 0.66;
  const centreY = sombre ? hauteur * 0.38 : horizon + LIGNE_D_EAU * echelle;

  const sommets = useDerivedValue(() => {
    const angle = phase.value * Math.PI * 2;
    const cos = Math.cos(angle);
    const sin = Math.sin(angle);
    const ordre = ordreDeDessin(triangles, angle);

    const points: Array<{ x: number; y: number }> = [];

    for (let k = 0; k < ordre.length; k++) {
      const t = triangles[ordre[k]!]!;

      for (const s of [t.a, t.b, t.c]) {
        // Rotation autour de l'axe vertical, puis projection orthographique legerement inclinee.
        const x = s.x * cos - s.z * sin;
        const z = s.x * sin + s.z * cos;

        points.push({
          x: centreX + x * echelle,
          y: centreY - s.y * echelle + z * echelle * 0.16,
        });
      }
    }

    return points;
  });

  const couleurs = useDerivedValue(() => {
    const angle = phase.value * Math.PI * 2;
    const cos = Math.cos(angle);
    const sin = Math.sin(angle);
    const ordre = ordreDeDessin(triangles, angle);

    const teintes: string[] = [];

    for (let k = 0; k < ordre.length; k++) {
      const t = triangles[ordre[k]!]!;

      // La normale de la face, calculee apres rotation : l'eclairage doit tourner avec l'objet.
      const ax = t.a.x * cos - t.a.z * sin;
      const az = t.a.x * sin + t.a.z * cos;
      const bx = t.b.x * cos - t.b.z * sin;
      const bz = t.b.x * sin + t.b.z * cos;
      const cx = t.c.x * cos - t.c.z * sin;
      const cz = t.c.x * sin + t.c.z * cos;

      const ux = bx - ax;
      const uy = t.b.y - t.a.y;
      const uz = bz - az;
      const vx = cx - ax;
      const vy = t.c.y - t.a.y;
      const vz = cz - az;

      const nx = uy * vz - uz * vy;
      const ny = uz * vx - ux * vz;
      const nz = ux * vy - uy * vx;
      const norme = Math.sqrt(nx * nx + ny * ny + nz * nz) || 1;

      // La lumiere descend de la surface, legerement de face.
      const lambert = Math.max(0, (ny / norme) * 0.78 + (nz / norme) * 0.34);

      /*
       * LA PROFONDEUR ETEINT TOUT. `t.hauteur` va de +1 (couronne) a -1,5 (pointe de quille) :
       * on la ramene entre 0 et 1, et c'est ce facteur qui fait la difference entre de la glace
       * eclatante et une masse qu'on devine a peine.
       */
      const profondeur = Math.min(1, Math.max(0, (t.hauteur + 1.5) / 2.5));
      const diffusion = Math.pow(profondeur, 1.7);

      const intensite = 0.1 + lambert * 0.45 + diffusion * 0.72;

      /*
       * LA RAMPE EST CALÉE SUR LES BORNES, elle n'est pas coupée à leur hauteur.
       *
       * Premier essai : un `Math.min` sur le plafond de nuit. La couronne, toute entière au-dessus
       * du plafond, est devenue un triangle d'un seul gris — l'objet avait perdu ses facettes en
       * gardant sa silhouette. La rampe part donc du fond et arrive EXACTEMENT sur la borne à
       * l'intensité maximale (0,1 + 0,45 + 0,72 = 1,27), ce qui garde tout l'ombrage entre les deux.
       *
       * En clair l'ombre est froide et non grise : une valeur neutre sous les faces les moins
       * éclairées donnait un entonnoir d'ardoise — de la pierre, pas de la glace.
       */
      const [rFond, vFond, bFond] = sombre ? [4, 12, 20] : [126, 150, 170];
      const [rBase, vBase, bBase] = sombre ? [57, 83, 94] : [122, 152, 176];

      const [rMin, vMin, bMin] = sombre ? [0, 0, 0] : PLANCHER_CLAIR;
      const [rMax, vMax, bMax] = sombre ? PLAFOND_NUIT : [255, 255, 255];

      const r = Math.round(Math.min(rMax, Math.max(rMin, rFond + rBase * intensite)));
      const v = Math.round(Math.min(vMax, Math.max(vMin, vFond + vBase * intensite)));
      const b = Math.round(Math.min(bMax, Math.max(bMin, bFond + bBase * intensite)));

      // Trois sommets par triangle : l'ombrage est PLAT, comme sur un rendu a facettes.
      teintes.push(`rgb(${r}, ${v}, ${b})`, `rgb(${r}, ${v}, ${b})`, `rgb(${r}, ${v}, ${b})`);
    }

    return teintes;
  });

  return (
    <Group opacity={sombre ? 1 : 0.92}>
      {/*
        PAS DE `blendMode` ICI, ET C'EST DÉLIBÉRÉ.
        Le mode combine les couleurs des sommets (destination) avec celle de la peinture (source),
        noire par défaut. `srcOver` pose donc du noir opaque par-dessus le maillage : sur
        l'émulateur, l'iceberg était un hexagone entièrement noir. Le défaut par défaut, `dstOver`,
        laisse gagner les sommets — c'est celui qu'il faut.
      */}
      <Vertices vertices={sommets} colors={couleurs} mode="triangles" />

      {/*
        LA MER — et c'est elle qui fait le thème clair.
        Sans elle, le mode clair montrait la quille entière : le côté invisible de l'iceberg, en
        plein jour. L'eau la noie sans l'effacer tout à fait, parce qu'on devine toujours la masse
        sous une mer polaire. Elle ne descend jamais plus bas que `maillageSombre` : c'est la
        surface de référence du garde-fou de contraste, et le verre posé dessus doit rester lisible.
      */}
      {sombre ? null : (
        <>
          <Rect x={0} y={horizon} width={largeur} height={hauteur - horizon}>
            <LinearGradient
              start={vec(0, horizon)}
              end={vec(0, hauteur)}
              colors={['rgba(217, 232, 243, 0.05)', 'rgba(217, 232, 243, 0.82)', 'rgba(217, 232, 243, 0.95)']}
              positions={[0, 0.16, 1]}
            />
          </Rect>

          {/* Le trait de flottaison : la seule arête franche de la composition. */}
          <Rect x={0} y={horizon - 1} width={largeur} height={2} color="rgba(255, 255, 255, 0.9)" />
        </>
      )}
    </Group>
  );
}
