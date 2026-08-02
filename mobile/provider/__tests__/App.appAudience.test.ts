/**
 * L'application prestataire se DÉCLARE au serveur.
 *
 * Le serveur refuse un compte prestataire ici, et un compte client dans l'application
 * professionnelle — mais il ne peut le faire que s'il sait à qui il parle. En l'absence de
 * déclaration, il laisse passer : il le faut, sinon la mise en service de ce garde-fou
 * déconnecterait tout le parc déjà installé.
 *
 * D'où ce test. L'oubli de la déclaration serait SILENCIEUX : aucune erreur, aucun écran cassé,
 * simplement un garde-fou qui ne garde rien. Rien d'autre dans la chaîne ne le remarquerait.
 *
 * On lit la racine plutôt que de la monter, comme le test de GestureHandlerRootView à côté :
 * monter l'application demanderait de simuler la moitié de ses fournisseurs pour vérifier une
 * seule chose, qui est structurelle.
 */
import fs from 'fs';
import path from 'path';

const APP_ROOT = path.join(__dirname, '..', 'App.tsx');

describe('Déclaration de l’application prestataire', () => {
  const source = fs.readFileSync(APP_ROOT, 'utf8');

  it('importe setAppAudience', () => {
    expect(source).toMatch(/import\s*\{[^}]*setAppAudience[^}]*\}/);
  });

  it('se déclare comme application PRESTATAIRE', () => {
    expect(source).toMatch(/setAppAudience\(\s*'provider'\s*\)/);
  });

  it('se déclare avant de monter quoi que ce soit', () => {
    const declaration = source.indexOf('setAppAudience(');
    const composant = source.indexOf('export default function');

    expect(declaration).toBeGreaterThan(-1);
    expect(declaration).toBeLessThan(composant);
  });
});
