/**
 * LE MAILLAGE DE L'ICEBERG — de la géométrie, pas une image.
 *
 * POURQUOI PAS THREE.JS. La proposition « volumétrique » demandait un rendu 3D ; elle ne demandait
 * pas un second moteur de rendu. Skia sait dessiner un maillage éclairé par sommet
 * (`<Vertices/>`), il est déjà installé, et il rend déjà le fond. Ajouter `expo-gl` et
 * `@react-three/fiber` aurait coûté une dépendance native, un contexte GL par écran, et la
 * question de faire cohabiter deux moteurs avec le flou d'`expo-blur` — pour la même image.
 *
 * LA COURONNE EST DENTELÉE, ET C'EST TOUT LE SUJET. La première version tournait chaque anneau
 * autour d'un rayon presque constant : à l'écran, une toupie. Un iceberg tabulaire a des FLÈCHES —
 * quelques azimuts montent bien plus haut que leurs voisins — une taille irrégulière à la ligne de
 * flottaison, et une quille plus longue et plus lisse que sa couronne. Le rapport un pour neuf est
 * respecté : c'est le sujet du thème.
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
  /** La hauteur moyenne du triangle, de -1,6 (pointe de quille) à +1 (flèche). Elle décide de sa couleur. */
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

const COTES = 18;

/**
 * Les flèches de la couronne : [position autour de l'axe 0→1, hauteur, largeur angulaire].
 *
 * Une seule domine, deux la secondent, deux épaulements ferment la silhouette. Des hauteurs
 * régulières donneraient une couronne, pas une crête.
 */
const FLECHES: Array<[number, number, number]> = [
  [0.02, 1.0, 0.11],
  [0.14, 0.66, 0.07],
  [0.52, 0.82, 0.09],
  [0.63, 0.47, 0.06],
  [0.79, 0.58, 0.08],
];

/** La hauteur de base de la crête, entre les flèches. */
const CRETE = 0.26;

/** Les anneaux de la quille : [profondeur, part du rayon de flottaison, désordre]. */
const QUILLE: Array<[number, number, number]> = [
  [-0.3, 0.9, 0.13],
  [-0.68, 0.64, 0.15],
  [-1.12, 0.31, 0.12],
  [-1.62, 0.03, 0.02],
];

/** Les paliers de la partie émergée, de la crête à la flottaison. */
const PALIERS = [0, 0.32, 0.64, 1];

/** La distance angulaire la plus courte entre deux positions du tour, en fraction de tour. */
function ecart(u: number, v: number): number {
  const d = Math.abs(u - v);

  return Math.min(d, 1 - d);
}

/**
 * Construit l'iceberg.
 *
 * Le profil est établi côté par côté — hauteur de crête, rayon au sommet, rayon à la flottaison —
 * puis les anneaux sont interpolés entre ces bornes et reliés en bandes de triangles. Le bruit
 * s'applique au PROFIL, pas aux anneaux : c'est ce qui donne des arêtes qui descendent d'un bout à
 * l'autre de l'objet, au lieu de bosses indépendantes empilées.
 */
export function construireIceberg(): Triangle[] {
  const suivant = tirage(20260913);

  const hautDeCrete: number[] = [];
  const rayonDeCrete: number[] = [];
  const rayonDeFlottaison: number[] = [];

  for (let i = 0; i < COTES; i++) {
    const u = i / COTES;
    let sommet = CRETE * (0.62 + suivant() * 0.76);

    for (const [position, hauteur, largeur] of FLECHES) {
      const d = ecart(u, position);

      if (d < largeur) {
        // Décroissance ANGULEUSE : une gaussienne arrondirait les flèches en collines.
        sommet = Math.max(sommet, hauteur * Math.pow(1 - d / largeur, 0.72));
      }
    }

    hautDeCrete.push(sommet);
    rayonDeCrete.push(0.05 + suivant() * 0.13);
    /* 0,34 et non 0,6 : une fleche, pas une toupie. C'est le rapport hauteur/largeur qui fait
       lire « iceberg » avant meme que les facettes soient visibles. */
    rayonDeFlottaison.push(0.34 * (0.8 + suivant() * 0.4));
  }

  const anneaux: Sommet[][] = [];

  for (const t of PALIERS) {
    anneaux.push(
      Array.from({ length: COTES }, (_, i) => {
        const angle = (i / COTES) * Math.PI * 2;
        // Le flanc s'évase vite près du sommet puis se redresse : une flèche, pas un cône.
        const r =
          rayonDeCrete[i]! + (rayonDeFlottaison[i]! - rayonDeCrete[i]!) * Math.pow(t, 0.78);

        return {
          x: Math.cos(angle) * r,
          y: hautDeCrete[i]! * (1 - t),
          z: Math.sin(angle) * r,
        };
      }),
    );
  }

  for (const [profondeur, part, desordre] of QUILLE) {
    anneaux.push(
      Array.from({ length: COTES }, (_, i) => {
        const angle = (i / COTES) * Math.PI * 2;
        const r = rayonDeFlottaison[i]! * part * (1 + (suivant() - 0.5) * 2 * desordre);

        return {
          x: Math.cos(angle) * r,
          y: profondeur + (suivant() - 0.5) * desordre * 0.35,
          z: Math.sin(angle) * r,
        };
      }),
    );
  }

  const triangles: Triangle[] = [];

  const pousser = (a: Sommet, b: Sommet, c: Sommet) => {
    triangles.push({ a, b, c, hauteur: (a.y + b.y + c.y) / 3 });
  };

  for (let bande = 0; bande < anneaux.length - 1; bande++) {
    const haut = anneaux[bande]!;
    const bas = anneaux[bande + 1]!;

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

/** La hauteur du modèle qui coïncide avec la surface de l'eau — la taille de l'iceberg. */
export const LIGNE_D_EAU = 0;

/** Les deux extrêmes du modèle, pour cadrer sans les recompter à chaque rendu. */
export const HAUT_DU_MODELE = 1;
export const BAS_DU_MODELE = -1.62;
