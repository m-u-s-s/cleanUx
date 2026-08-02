import { authErrorMessage } from '../authShell';
import { ApiError } from '@/api';

/**
 * Le refus d'application doit arriver INTACT jusqu'à l'écran.
 *
 * Le serveur refuse un compte prestataire dans l'application cliente — et l'inverse — avec une
 * phrase qui dit quoi faire : « Connectez-vous depuis l'application brio Pro. » Le traducteur
 * d'erreurs retombait sur « Connexion impossible pour le moment. » pour tout ce qui n'était ni 401,
 * ni 422, ni 429, ni 5xx.
 *
 * Or c'est exactement le message qui évite l'appel au support : sans lui, on retente, on doute de
 * son mot de passe, et on finit par croire que son compte est cassé.
 */
describe('authErrorMessage — refus d’application', () => {
  it('laisse passer le message du serveur sur un 403', () => {
    const error = new ApiError(
      403,
      'wrong_app',
      'Ce compte est un compte professionnel. Connectez-vous depuis l’application brio Pro.',
    );

    expect(authErrorMessage(error, 'login')).toBe(
      'Ce compte est un compte professionnel. Connectez-vous depuis l’application brio Pro.',
    );
  });

  it('retombe sur le message générique si le serveur n’explique rien', () => {
    expect(authErrorMessage(new ApiError(403, 'forbidden', ''), 'login'))
      .toBe('Connexion impossible pour le moment.');
  });

  /** Les identifiants incorrects restent volontairement vagues : on ne dit pas si l'email existe. */
  it('ne détaille toujours pas un 401', () => {
    expect(authErrorMessage(new ApiError(401, 'bad', 'Utilisateur inconnu'), 'login'))
      .toBe('Identifiants incorrects.');
  });
});
