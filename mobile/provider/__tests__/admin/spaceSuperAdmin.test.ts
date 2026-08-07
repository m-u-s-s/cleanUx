/**
 * LE QUATRIÈME ESPACE DE L'APPLICATION PRESTATAIRE.
 *
 * Elle en servait trois : le terrain, la console d'administration, la société prestataire. Le
 * super administrateur passait par la console — `is_admin` étant vrai pour lui comme pour un
 * administrateur ordinaire, rien ne l'en distinguait, et le sixième rôle n'existait pas sur
 * mobile.
 *
 * L'ORDRE DES CONDITIONS EST TOUT, et ce fichier ne teste que ça. C'est la même leçon que côté
 * société : `resolveSpace` a déjà enfermé un gérant hors de son espace parce qu'une condition
 * était évaluée trop tard.
 */
import { resolveSpace } from '@/admin/space';

const base = { isLoading: false, isAuthenticated: true, onboardingComplete: true };

describe('resolveSpace — le super administrateur', () => {
  it('ouvre son espace au démarrage, sans lui demander de choisir', () => {
    // Lui imposer le sélecteur à chaque lancement ferait payer un écran de choix à quelqu'un qui
    // fait le même geste tous les matins — le raisonnement qui a déjà décidé de RETENIR le choix.
    expect(
      resolveSpace({ ...base, user: { is_admin: true, is_super_admin: true } }),
    ).toBe('superAdmin');
  });

  it('respecte un choix explicite pour la console', () => {
    expect(
      resolveSpace({ ...base, user: { is_admin: true, is_super_admin: true }, chosenSpace: 'admin' }),
    ).toBe('admin');
  });

  it('respecte un choix explicite pour le terrain', () => {
    // Le super administrateur d'une petite structure intervient parfois lui-même.
    expect(
      resolveSpace({
        ...base,
        user: { is_admin: true, is_super_admin: true, is_provider: true },
        chosenSpace: 'provider',
      }),
    ).toBe('provider');
  });

  it('N’OUVRE PAS son espace à un administrateur ordinaire', () => {
    /*
     * LA DISTINCTION QUI DONNE SON SENS AU RÔLE.
     *
     * `is_admin` est vrai pour les deux. Aiguiller dessus donnerait au sixième rôle exactement le
     * même espace qu'au cinquième, et le distinguer n'aurait servi à rien.
     */
    expect(
      resolveSpace({ ...base, user: { is_admin: true, is_super_admin: false } }),
    ).toBe('admin');
  });

  it('ignore un drapeau absent — le parc installé n’en sait rien', () => {
    // Les jetons émis avant cette version ne portent pas `is_super_admin`. Sans drapeau, le compte
    // est traité comme un administrateur : c'est ce qu'il était hier, et rien ne se casse.
    expect(resolveSpace({ ...base, user: { is_admin: true } })).toBe('admin');
  });

  it('ne donne rien à qui n’est pas authentifié', () => {
    expect(
      resolveSpace({ ...base, isAuthenticated: false, user: null }),
    ).toBe('login');
  });
});
