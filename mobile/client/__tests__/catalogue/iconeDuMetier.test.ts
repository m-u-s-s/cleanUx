import fs from 'fs';
import path from 'path';
import { ICONES_DU_CATALOGUE, ICONE_PAR_DEFAUT, iconeDuMetier } from '@/catalogue';

/*
 * LE VRAI FICHIER DE GLYPHES, PAS LE BOUCHON.
 *
 * `__mocks__/@expo/vector-icons.tsx` répond « présent » pour n'importe quel nom : un test appuyé
 * dessus passerait avec une icône qui n'existe pas. npm place le paquet côté client ou à la racine
 * de l'espace de travail selon les versions : les deux emplacements sont essayés.
 */
const CANDIDATS = [
  path.resolve(__dirname, '../../node_modules/@expo/vector-icons/build/vendor/react-native-vector-icons/glyphmaps/Ionicons.json'),
  path.resolve(__dirname, '../../../node_modules/@expo/vector-icons/build/vendor/react-native-vector-icons/glyphmaps/Ionicons.json'),
];
const fichier = CANDIDATS.find(p => fs.existsSync(p));
const glyphes: Record<string, number> = fichier ? JSON.parse(fs.readFileSync(fichier, 'utf8')) : {};

describe('iconeDuMetier', () => {
  it('trouve le fichier de glyphes Ionicons', () => {
    expect(fichier).toBeDefined();
  });

  it('témoin : un nom inventé est absent du vrai fichier', () => {
    expect('icone-qui-n-existe-pas' in glyphes).toBe(false);
    expect('briefcase-outline' in glyphes).toBe(true);
  });

  it.each(Object.entries(ICONES_DU_CATALOGUE))('%s → %s existe dans Ionicons', (_serveur, ionicons) => {
    expect(ionicons in glyphes).toBe(true);
  });

  it('traduit les noms du serveur', () => {
    expect(iconeDuMetier('wrench')).toBe('build-outline');
    expect(iconeDuMetier('shield-check')).toBe('shield-checkmark-outline');
  });

  it('retombe sur la mallette pour un nom absent, vide ou piégé', () => {
    expect(iconeDuMetier('inconnu')).toBe(ICONE_PAR_DEFAUT);
    expect(iconeDuMetier(null)).toBe(ICONE_PAR_DEFAUT);
    expect(iconeDuMetier('')).toBe(ICONE_PAR_DEFAUT);
    expect(iconeDuMetier('constructor')).toBe(ICONE_PAR_DEFAUT);
  });
});
