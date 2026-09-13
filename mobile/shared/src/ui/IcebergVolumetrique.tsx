import React, { useMemo } from 'react';
import { BlurMask, Circle, Group, Vertices } from '@shopify/react-native-skia';
import { useDerivedValue, type SharedValue } from 'react-native-reanimated';
import { colors } from '@/theme';
import {
  BAS_DU_MODELE,
  HAUT_DU_MODELE,
  LIGNE_D_EAU,
  construireIceberg,
  ordreDeDessin,
} from './icebergMaillage';

/**
 * L'ICEBERG, RENDU EN VOLUME.
 *
 * Un maillage projeté et éclairé sommet par sommet, dessiné par Skia. Voir `icebergMaillage.ts`
 * pour la géométrie et pour la raison de ne pas avoir pris three.js.
 *
 * LA GLACE S'ÉCLAIRE DE L'INTÉRIEUR — c'est la demande de la planche retenue, et ce n'est pas
 * l'éclairage qui la produit. Une part de la clarté ne vient pas de l'angle de la face mais de
 * l'ÉPAISSEUR traversée : c'est la diffusion sous la surface, et c'est ce qui sépare la glace de
 * la pierre. Un halo flou posé derrière la crête achève l'effet, comme une source prise dans la
 * masse.
 *
 * LA FLOTTAISON N'EST PAS UN TRAIT, C'EST UNE RUPTURE DE TON. Un trait blanc en travers de
 * l'écran a été essayé : il coupait la composition en deux images. Ici la glace change franchement
 * de valeur en passant sous l'eau, et c'est cette cassure qu'on lit comme la surface.
 *
 * LE CADRAGE NE CHANGE PAS D'UN THÈME À L'AUTRE. Même échelle, même centre : seul l'éclairage
 * bascule. Deux cadrages différents feraient lire deux objets.
 */

function decomposer(hex: string): [number, number, number] {
  const h = hex.replace('#', '');

  return [0, 2, 4].map(i => parseInt(h.slice(i, i + 2), 16)) as [number, number, number];
}

/** Le plancher du thème clair : la scène n'y descend jamais plus bas — voir `colors.ts`. */
const PLANCHER_CLAIR = decomposer(colors.mode.iceberg.emerge.plancher);

/** Le pendant en nuit : au-dessus de cette valeur, le texte clair se perd sur la glace. */
const PLAFOND_NUIT = decomposer(colors.mode.iceberg.immerge.plafond);

/** Le modèle occupe 69 % de la hauteur d'écran, comme sur la planche retenue. */
const PART_DE_L_ECRAN = 0.69;
const HAUTEUR_DU_MODELE = HAUT_DU_MODELE - BAS_DU_MODELE;

interface Props {
  /** 0 → 1, un tour complet. Figée à 0 quand le mouvement est réduit. */
  phase: SharedValue<number>;
  largeur: number;
  hauteur: number;
  sombre: boolean;
}

export function IcebergVolumetrique({ phase, largeur, hauteur, sombre }: Props) {
  const triangles = useMemo(() => construireIceberg(), []);

  const echelle = (hauteur * PART_DE_L_ECRAN) / HAUTEUR_DU_MODELE;
  const centreX = largeur * 0.5;
  /* La crête tombe à 14,5 % du haut ; le centre s'en déduit, il ne se règle pas à la main. */
  const centreY = hauteur * 0.145 + HAUT_DU_MODELE * echelle;

  /*
   * UN SEUL WORKLET POUR LES DEUX PROPRIÉTÉS. Projeter et éclairer partagent la rotation ET le
   * tri en profondeur ; deux `useDerivedValue` indépendants les recalculaient tous les deux.
   */
  const modele = useDerivedValue(() => {
    const angle = phase.value * Math.PI * 2;
    const cos = Math.cos(angle);
    const sin = Math.sin(angle);
    const ordre = ordreDeDessin(triangles, angle);

    const points: Array<{ x: number; y: number }> = [];
    const teintes: string[] = [];

    /*
     * LA RAMPE VA D'UNE BORNE À L'AUTRE, exactement. Le fond EST la borne basse du thème —
     * l'abysse en nuit, le plancher en clair — et `fond + base` atteint la borne haute à intensité
     * pleine. Régler ces six nombres à vue redonne aussitôt une crête éteinte ou un texte illisible.
     */
    const [rFond, vFond, bFond] = sombre ? [4, 12, 20] : [176, 213, 243];
    const [rBase, vBase, bBase] = sombre ? [70, 102, 115] : [81, 43, 12];
    const [rMin, vMin, bMin] = sombre ? [0, 0, 0] : PLANCHER_CLAIR;
    const [rMax, vMax, bMax] = sombre ? PLAFOND_NUIT : [255, 255, 255];

    /*
     * LES DEUX THÈMES N'ONT PAS LA MÊME COURSE UTILE.
     *
     * En nuit la quille peut aller jusqu'au noir : elle occupe presque toute la rampe. En clair
     * elle bute sur le plancher au bout de quelques centièmes, et une course trop longue l'y
     * écrasait tout entière — une masse d'un seul bleu, sans une facette. Ici sa course est
     * courte et posée JUSTE au-dessus du plancher, ce qui lui rend son relief sans le franchir.
     */
    const sousLEau = sombre ? [0.2, 0.18, 0.94] : [0.06, 0.2, 0.55];
    const auGrandJour = sombre ? [0.22, 0.22, 0.62] : [0.45, 0.25, 0.55];

    for (let k = 0; k < ordre.length; k++) {
      const t = triangles[ordre[k]!]!;

      const ax = t.a.x * cos - t.a.z * sin;
      const az = t.a.x * sin + t.a.z * cos;
      const bx = t.b.x * cos - t.b.z * sin;
      const bz = t.b.x * sin + t.b.z * cos;
      const cx = t.c.x * cos - t.c.z * sin;
      const cz = t.c.x * sin + t.c.z * cos;

      // Projection orthographique légèrement inclinée : on voit un peu le dessus des facettes.
      points.push({ x: centreX + ax * echelle, y: centreY - t.a.y * echelle + az * echelle * 0.16 });
      points.push({ x: centreX + bx * echelle, y: centreY - t.b.y * echelle + bz * echelle * 0.16 });
      points.push({ x: centreX + cx * echelle, y: centreY - t.c.y * echelle + cz * echelle * 0.16 });

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

      // Éclairage de studio : une source haute, légèrement de face.
      const lambert = Math.max(0, (ny / norme) * 0.74 + (nz / norme) * 0.4);

      /*
       * C'EST LA DIFFUSION QUI ÉCLAIRE, PAS L'ANGLE — sans quoi la crête reste noire.
       *
       * Premier essai : `lambert` menait la couleur. Une flèche est faite de faces presque
       * VERTICALES, qui ne reçoivent quasi rien d'une source haute : la crête sortait plus sombre
       * que l'épaulement plat sous elle, exactement l'inverse de ce qu'on voit sur de la glace.
       * Ici c'est la HAUTEUR au-dessus de l'eau qui commande — l'épaisseur traversée par la
       * lumière — et l'angle ne fait plus que sculpter les facettes.
       */
      const immerge = t.hauteur < LIGNE_D_EAU;

      let intensite;

      if (immerge) {
        // Sous l'eau, tout s'éteint avec la profondeur, jusqu'à la pointe qu'on ne voit plus.
        const immersion = Math.min(1, (LIGNE_D_EAU - t.hauteur) / (LIGNE_D_EAU - BAS_DU_MODELE));

        intensite = (sousLEau[0]! + lambert * sousLEau[1]!) * (1 - immersion * sousLEau[2]!);
      } else {
        const emergence = Math.min(1, (t.hauteur - LIGNE_D_EAU) / (HAUT_DU_MODELE - LIGNE_D_EAU));

        intensite = auGrandJour[0]! + lambert * auGrandJour[1]! + Math.pow(emergence, 0.7) * auGrandJour[2]!;
      }

      /*
       * LA RAMPE EST CALÉE SUR LES BORNES, elle n'est pas coupée à leur hauteur : un `Math.min`
       * sur le plafond de nuit écrasait toute la crête en un triangle d'un seul gris.
       */
      const r = Math.round(Math.min(rMax, Math.max(rMin, rFond + rBase * intensite)));
      const v = Math.round(Math.min(vMax, Math.max(vMin, vFond + vBase * intensite)));
      const b = Math.round(Math.min(bMax, Math.max(bMin, bFond + bBase * intensite)));

      // Trois sommets par triangle : l'ombrage est PLAT, comme sur un rendu à facettes.
      const teinte = `rgb(${r}, ${v}, ${b})`;

      teintes.push(teinte, teinte, teinte);
    }

    return { points, teintes };
  });

  const sommets = useDerivedValue(() => modele.value.points);
  const couleurs = useDerivedValue(() => modele.value.teintes);

  const hautDeLaCrete = centreY - HAUT_DU_MODELE * echelle;

  return (
    <Group>
      {/*
        LE HALO EST DERRIÈRE LE MAILLAGE, jamais devant. Posé par-dessus, il ferait passer la
        crête au-dessus du plafond de la scène et le texte clair s'y perdrait. Derrière, il ne
        touche que l'eau — et c'est bien de là que la lumière doit venir.
      */}
      <Group opacity={sombre ? 0.45 : 0.5}>
        <Circle
          cx={centreX}
          cy={hautDeLaCrete + echelle * 0.3}
          r={echelle * 0.5}
          color={sombre ? colors.mode.iceberg.immerge.plafond : '#ffffff'}
        >
          <BlurMask blur={echelle * 0.3} style="normal" />
        </Circle>
      </Group>

      {/*
        PAS DE `blendMode` ICI, ET C'EST DÉLIBÉRÉ. Le mode combine les couleurs des sommets
        (destination) avec celle de la peinture (source), noire par défaut : `srcOver` posait du
        noir opaque par-dessus le maillage. Le défaut, `dstOver`, laisse gagner les sommets.
      */}
      <Vertices vertices={sommets} colors={couleurs} mode="triangles" />
    </Group>
  );
}
