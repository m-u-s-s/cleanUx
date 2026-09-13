/**
 * LE MAILLAGE DE L'ICEBERG — de la géométrie, pas une image.
 *
 * POURQUOI PAS THREE.JS. La proposition « volumétrique » demandait un rendu 3D ; elle ne demandait
 * pas un second moteur de rendu. Skia sait dessiner un maillage éclairé par sommet
 * (`<Vertices/>`), il est déjà installé, et il rend déjà le fond. Ajouter `expo-gl` et
 * `@react-three/fiber` aurait coûté une dépendance native, un contexte GL par écran, et la
 * question de faire cohabiter deux moteurs avec le flou d'`expo-blur` — pour la même image.
 *
 * Ici la géométrie est construite UNE FOIS et projetée à chaque image. Deux cents triangles
 * projetés coûtent moins qu'un dégradé plein écran.
 *
 * LA FORME N'EST PAS UN CÔNE RETOURNÉ. Un iceberg tabulaire a une couronne dentelée, une taille
 * à la ligne de flottaison, et une quille bien plus longue et plus lisse que sa couronne. Le
 * rapport un pour neuf est respecté : c'est le sujet du thème.
 */

/** Un sommet dans l'espace du modèle, avant projection. */
export interface Sommet {
  x: number;
  y: number;
  z: number;
}

export interface Triangle {
  a: Sommet;
  b: Sommet;
  c: Sommet;
  /** La hauteur moyenne du triangle, de -1 (quille) à +1 (couronne). Elle décide de sa couleur. */
  hauteur: number;
}

/** Le générateur : à graine égale, le même iceberg. Un relief qui change à chaque rendu se voit. */
function tirage(graine: number) {
  let etat = graine;

  return () => {
    etat = (etat * 1664525 + 1013904223) % 4294967296;

    return etat / 4294967296;
  };
}

const COTES = 11;

/**
 * Construit l'iceberg.
 *
 * Quatre anneaux de sommets — la couronne, la ligne de flottaison, la panse, la pointe de quille —
 * reliés en bandes de triangles. Le rayon de chaque anneau est bruité côte par côte, ce qui donne
 * les facettes irrégulières ; sans ce bruit on obtiendrait une toupie.
 */
export function construireIceberg(): Triangle[] {
  const suivant = tirage(20260913);

  /** [hauteur, rayon moyen, désordre] — l'ordre va du sommet vers le fond. */
  const anneaux: Array<[number, number, number]> = [
    [1.0, 0.06, 0.02],
    [0.62, 0.34, 0.13],
    [0.12, 0.62, 0.1],
    [-0.05, 0.58, 0.08],
    [-0.55, 0.44, 0.16],
    [-1.15, 0.2, 0.12],
    [-1.5, 0.03, 0.02],
  ];

  const points: Sommet[][] = anneaux.map(([y, rayon, desordre]) =>
    Array.from({ length: COTES }, (_, i) => {
      const angle = (i / COTES) * Math.PI * 2;
      const r = rayon * (1 + (suivant() - 0.5) * 2 * desordre);

      return {
        x: Math.cos(angle) * r,
        y: y + (suivant() - 0.5) * desordre * 0.5,
        z: Math.sin(angle) * r,
      };
    }),
  );

  const triangles: Triangle[] = [];

  const pousser = (a: Sommet, b: Sommet, c: Sommet) => {
    triangles.push({ a, b, c, hauteur: (a.y + b.y + c.y) / 3 });
  };

  for (let bande = 0; bande < points.length - 1; bande++) {
    const haut = points[bande]!;
    const bas = points[bande + 1]!;

    for (let i = 0; i < COTES; i++) {
      const j = (i + 1) % COTES;

      pousser(haut[i]!, haut[j]!, bas[i]!);
      pousser(haut[j]!, bas[j]!, bas[i]!);
    }
  }

  return triangles;
}

/**
 * Le tri en profondeur, refait à chaque angle.
 *
 * Skia n'a pas de tampon de profondeur : sans ce tri, les faces arrière se dessinent par-dessus
 * les faces avant selon leur ordre de création, et le volume s'effondre en un patchwork plat.
 * On dessine du plus loin au plus près — l'algorithme du peintre.
 */
export function ordreDeDessin(triangles: Triangle[], angle: number): number[] {
  'worklet';

  const cos = Math.cos(angle);
  const sin = Math.sin(angle);

  const profondeurs = triangles.map((t, index) => {
    const z = ((t.a.x + t.b.x + t.c.x) / 3) * sin + ((t.a.z + t.b.z + t.c.z) / 3) * cos;

    return { index, z };
  });

  profondeurs.sort((p, q) => p.z - q.z);

  return profondeurs.map((p) => p.index);
}
