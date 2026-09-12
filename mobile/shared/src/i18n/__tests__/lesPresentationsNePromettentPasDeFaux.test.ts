/**
 * Les deux présentations de première ouverture ne promettent que ce que le moteur fait.
 *
 * Elles annonçaient « Améliorez votre score pour obtenir plus de missions » et « Débloquez des
 * bonus avec vos badges » : mesuré dans le moteur de répartition, les badges n'entrent dans
 * AUCUNE des neuf mesures du classement, et aucun bonus ne leur est attaché. Côté client, elles
 * disaient « scannez le QR code » alors que le client MONTRE son code, et « réservez en 5 étapes »
 * alors que le parcours n'en compte pas cinq.
 *
 * Ces phrases ne coûtent rien à réécrire et beaucoup à laisser : un prestataire qui s'inscrit
 * pour un bonus inexistant part au premier relevé.
 */
import { fr } from '../catalogues/fr';
import { catalogues } from '../catalogues';
import { LANGUES } from '../types';

/** Les clés des six écrans de présentation, client puis prestataire. */
const PRESENTATIONS = [
    ...[1, 2, 3].flatMap(n => [`onboarding.titre_${n}`, `onboarding.texte_${n}`]),
    ...[1, 2, 3].flatMap(n => [`walkthrough.titre_${n}`, `walkthrough.texte_${n}`]),
];

/**
 * Chaque motif est une promesse que le code NE TIENT PAS, avec la raison de son interdiction.
 * Les motifs visent le français : c'est lui qui fait foi, les autres langues en dérivent.
 */
const INTERDITS: Array<{ motif: RegExp; pourquoi: string }> = [
    { motif: /badge/i, pourquoi: 'les badges n’entrent dans aucune des neuf mesures du classement' },
    { motif: /bonus/i, pourquoi: 'aucun bonus n’est attaché à quoi que ce soit dans le moteur' },
    { motif: /débloqu/i, pourquoi: 'rien ne se débloque : il n’y a ni palier ni récompense' },
    { motif: /plus de missions|davantage de missions/i, pourquoi: 'aucun volume n’est garanti' },
    { motif: /revenus? augmente/i, pourquoi: 'aucune progression de revenu n’est garantie' },
    { motif: /assurance|assuré/i, pourquoi: 'aucun assureur n’est câblé' },
    { motif: /garanti/i, pourquoi: 'aucune garantie n’est bornée dans le code' },
    { motif: /\b\d+\s*étapes?\b/i, pourquoi: 'le nombre d’étapes dépend du métier, il n’est pas fixe' },
    { motif: /scannez/i, pourquoi: 'le client MONTRE son code ; c’est le prestataire qui le scanne' },
    { motif: /24\s*\/\s*7|24h\s*\/\s*24/i, pourquoi: 'aucun horaire de support n’est tenu' },
    { motif: /note moyenne|\d[,.]\d\s*★|avis vérifiés/i, pourquoi: 'les tables d’avis sont vides' },
];

describe('les présentations de première ouverture', () => {
    it('ne promettent rien que le moteur ne fasse', () => {
        const fautes: string[] = [];

        for (const cle of PRESENTATIONS) {
            const texte = fr[cle];
            expect(typeof texte).toBe('string');

            for (const { motif, pourquoi } of INTERDITS) {
                if (motif.test(texte)) {
                    fautes.push(`${cle} : « ${texte} » — ${pourquoi}`);
                }
            }
        }

        expect(fautes).toEqual([]);
    });

    /**
     * LE TÉMOIN. Sans lui, la liste ci-dessus passerait au vert avec des motifs qui ne
     * reconnaissent plus rien — par exemple après une refonte qui les casserait tous.
     */
    it('reconnaît bien une promesse interdite quand il y en a une', () => {
        const ancienTexte = 'Débloquez des bonus avec vos badges et obtenez plus de missions.';
        const reconnus = INTERDITS.filter(({ motif }) => motif.test(ancienTexte));

        expect(reconnus.length).toBeGreaterThanOrEqual(3);
    });

    it.each([...LANGUES])('la présentation existe en %s', langue => {
        const manquantes = PRESENTATIONS.filter(cle => !catalogues[langue][cle]);

        expect(manquantes).toEqual([]);
    });

    /** Un titre qui déborde pousse le texte hors de l'écran sur un téléphone étroit. */
    it.each([...LANGUES])('les titres restent courts en %s', langue => {
        const trop = PRESENTATIONS
            .filter(cle => cle.includes('titre'))
            .map(cle => [cle, catalogues[langue][cle]] as const)
            .filter(([, texte]) => texte.length > 44);

        expect(trop).toEqual([]);
    });
});
