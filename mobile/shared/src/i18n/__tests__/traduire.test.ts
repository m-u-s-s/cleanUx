/** Le traducteur : recherche, repli sur le français, interpolation. */
import { interpoler, traduire } from '../traduire';

const FR = { 'a.b': 'Bonjour :nom', seul: 'Français seulement' };
const NL = { 'a.b': 'Hallo :nom' };

describe('traduire', () => {
  it('rend la chaîne de la langue demandée', () => {
    expect(traduire(NL, FR, 'a.b', { nom: 'Ana' })).toBe('Hallo Ana');
  });

  it('se rabat sur le français quand la clé manque', () => {
    expect(traduire(NL, FR, 'seul')).toBe('Français seulement');
  });

  it('rend la clé en dernier recours, pour qu’elle se voie', () => {
    expect(traduire(NL, FR, 'clef.absente.partout')).toBe('clef.absente.partout');
  });

  it('laisse le paramètre en place quand aucune valeur ne lui correspond', () => {
    expect(interpoler('Bonjour :nom', { autre: 'x' })).toBe('Bonjour :nom');
  });

  it('remplace plusieurs paramètres, chiffres compris', () => {
    expect(interpoler(':n message(s) pour :nom', { n: 3, nom: 'Ana' })).toBe('3 message(s) pour Ana');
  });

  /** LE TÉMOIN : sans valeurs, le texte ressort intact — la mesure compare quelque chose. */
  it('témoin : un texte sans paramètre traverse sans être touché', () => {
    expect(traduire(FR, FR, 'seul')).toBe('Français seulement');
  });
});
