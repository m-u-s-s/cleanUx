/**
 * Les six langues existent partout où elles doivent exister.
 *
 * Trois listes vivent séparément et doivent rester d'accord : le type `Langue`, la table
 * `catalogues`, et l'écran de langue de CHAQUE application. Une langue ajoutée au type mais
 * absente de l'écran serait choisissable par le serveur et invisible au réglage ; absente du
 * catalogue, elle rendrait toute l'interface en français sans qu'aucune erreur ne remonte.
 *
 * Le mobile n'en proposait que trois quand le web en servait six.
 */
import fs from 'fs';
import path from 'path';
import { LANGUES, type Langue } from '../types';
import { catalogues } from '../catalogues';
import { fr } from '../catalogues/fr';

/** La liste du web, `SetLocale::SUPPORTED`. Le mobile ne peut pas en proposer d'autres. */
const LANGUES_DU_WEB: Langue[] = ['fr', 'nl', 'en', 'es', 'it', 'de'];

const RACINE = path.resolve(__dirname, '../../../../');

describe('les six langues', () => {
    it('le type les déclare toutes, et rien de plus', () => {
        expect([...LANGUES].sort()).toEqual([...LANGUES_DU_WEB].sort());
    });

    it('chacune a son catalogue', () => {
        expect(Object.keys(catalogues).sort()).toEqual([...LANGUES_DU_WEB].sort());
    });

    it.each(LANGUES_DU_WEB)('le catalogue %s porte toutes les clés du français', langue => {
        const manquantes = Object.keys(fr).filter(cle => !(cle in catalogues[langue]));

        expect(manquantes).toEqual([]);
    });

    it.each(LANGUES_DU_WEB)('le catalogue %s n’invente aucune clé', langue => {
        const inventees = Object.keys(catalogues[langue]).filter(cle => !(cle in fr));

        expect(inventees).toEqual([]);
    });

    /**
     * Un jeton perdu à la traduction laisse un trou dans la phrase : « Écran sur » au lieu
     * de « Écran 2 sur 3 ». Rien ne le signale à la compilation.
     */
    it.each(LANGUES_DU_WEB)('le catalogue %s garde les jetons de chaque phrase', langue => {
        const ecarts: string[] = [];

        for (const [cle, texteFr] of Object.entries(fr)) {
            const attendus = (texteFr.match(/:[a-zA-Z_]+/g) ?? []).sort();
            if (!attendus.length) continue;

            const traduit = catalogues[langue][cle] ?? '';
            const trouves = (traduit.match(/:[a-zA-Z_]+/g) ?? []).sort();

            if (attendus.join(',') !== trouves.join(',')) {
                ecarts.push(`${cle} : attendu ${attendus.join(' ')}, trouvé ${trouves.join(' ') || '(aucun)'}`);
            }
        }

        expect(ecarts).toEqual([]);
    });

    it.each(['client', 'provider'])("l'écran de langue de %s les propose toutes", application => {
        const chemin = path.join(RACINE, application, 'src/screens/LanguageScreen.tsx');
        const source = fs.readFileSync(chemin, 'utf8');
        const proposees = [...source.matchAll(/code: '([a-z]{2})'/g)].map(m => m[1]);

        expect(proposees.sort()).toEqual([...LANGUES_DU_WEB].sort());
    });
});
