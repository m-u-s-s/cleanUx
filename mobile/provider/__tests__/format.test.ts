import { formatAdresse, formatDelai, messageDErreur, formatDateHeure } from '@brio/shared/format';

/**
 * CE QUE LE PRESTATAIRE LIT — relevé à l'écran, sur émulateur.
 *
 * Trois de ces défauts avaient déjà été corrigés côté client la veille, et sont réapparus à
 * l'identique ici : la date ISO, le message interne d'axios, le statut technique. Ces tests vivent
 * donc du côté prestataire ET les aides sont partagées — pour que la correction ne se perde pas une
 * troisième fois.
 */
describe('formatAdresse', () => {
  /** Vu à l'écran : « Rue Haute 42, null », sur la fiche qui dit au prestataire où se rendre. */
  it('n’écrit jamais le mot « null » dans une adresse', () => {
    expect(formatAdresse('Rue Haute 42', null)).toBe('Rue Haute 42');
    expect(formatAdresse('Rue Haute 42', undefined)).toBe('Rue Haute 42');
  });

  it('assemble ce qui existe', () => {
    expect(formatAdresse('Rue Haute 42', 'Bruxelles')).toBe('Rue Haute 42, Bruxelles');
  });

  /** LE TÉMOIN : tout vide donne une chaîne vide, pas une virgule orpheline. */
  it('ne laisse pas de ponctuation seule', () => {
    expect(formatAdresse(null, null)).toBe('');
    expect(formatAdresse('', 'Bruxelles')).toBe('Bruxelles');
  });
});

describe('formatDelai', () => {
  /**
   * Sous la minute, les secondes RESTENT — c'est l'unité d'une offre immédiate de vingt secondes,
   * et c'est là qu'elles pressent.
   */
  it('garde les secondes sous la minute', () => {
    expect(formatDelai(18)).toBe('18 s');
    expect(formatDelai(59)).toBe('59 s');
  });

  /** Vu à l'écran : « 1658 s pour répondre » sur une offre planifiée. Personne ne divise de tête. */
  it('passe aux minutes au-delà', () => {
    expect(formatDelai(60)).toBe('1 min');
    expect(formatDelai(1658)).toBe('27 min');
  });

  it('passe aux heures quand il le faut', () => {
    expect(formatDelai(3600)).toBe('1 h');
    expect(formatDelai(3900)).toBe('1 h 5 min');
  });

  it('ne rend jamais un délai négatif', () => {
    expect(formatDelai(-10)).toBe('0 s');
    expect(formatDelai(null)).toBe('0 s');
  });
});

describe('messageDErreur', () => {
  /**
   * LE DÉFAUT CENTRAL : « Request failed with status code 422 » s'affichait dans une alerte
   * française. C'est le texte interne d'axios, et il ne doit jamais atteindre un écran.
   */
  it('ne rend jamais le message interne d’axios', () => {
    const erreur = {
      message: 'Request failed with status code 422',
      response: { status: 422, data: { error: 'validation_failed', errors: {} } },
    };

    expect(messageDErreur(erreur)).not.toContain('status code');
    expect(messageDErreur(erreur)).toBe('Cette valeur n’a pas été acceptée. Vérifiez et réessayez.');
  });

  /** Le serveur sait pourquoi il refuse : son message prime sur toute traduction générique. */
  it('préfère le message du serveur', () => {
    const erreur = { response: { status: 422, data: { message: 'Ce code a expiré.' } } };

    expect(messageDErreur(erreur)).toBe('Ce code a expiré.');
  });

  /** À défaut de message, les erreurs de validation de Laravel disent déjà quelque chose d’utile. */
  it('remonte la première erreur de validation', () => {
    const erreur = {
      response: { status: 422, data: { errors: { code: ['Ce code n’est plus valable.'] } } },
    };

    expect(messageDErreur(erreur)).toBe('Ce code n’est plus valable.');
  });

  it('traduit les codes HTTP les plus courants', () => {
    expect(messageDErreur({ response: { status: 404, data: {} } })).toContain('n’existe plus');
    expect(messageDErreur({ response: { status: 401, data: {} } })).toContain('session');
  });

  /** LE TÉMOIN : sans réponse du tout — réseau coupé —, l'appelant fournit le repli. */
  it('utilise le repli de l’appelant quand il n’y a pas de réponse', () => {
    expect(messageDErreur(new Error('Network Error'), 'Ce code n’est pas valide.')).toBe(
      'Ce code n’est pas valide.',
    );
  });

  /**
   * LA FORME RÉELLE DES ERREURS DE CES APPLICATIONS.
   *
   * L'intercepteur convertit les échecs axios en `ApiError` : `status`, `message`, `errors` — et
   * AUCUN champ `response`. Une première version de cette aide ne lisait que `response.data` :
   * elle ne s'appliquait donc jamais sur le chemin principal et retombait en silence sur le repli.
   */
  it('lit l’ApiError des applications, pas seulement l’erreur axios brute', () => {
    const apiError = Object.assign(new Error('Ce code a expiré. Demandez-en un nouveau.'), {
      name: 'ApiError',
      status: 422,
      errorCode: 'invalid_code',
    });

    expect(messageDErreur(apiError)).toBe('Ce code a expiré. Demandez-en un nouveau.');
  });

  /** Le jargon d'axios recopié par l'intercepteur dans `message` doit être reconnu et écarté. */
  it('écarte le jargon d’axios même quand il arrive dans `message`', () => {
    const apiError = Object.assign(new Error('Request failed with status code 422'), {
      name: 'ApiError',
      status: 422,
      errorCode: 'http_error',
    });

    expect(messageDErreur(apiError)).toBe('Cette valeur n’a pas été acceptée. Vérifiez et réessayez.');
  });

  /** Les erreurs de validation portées par l'ApiError, pas par `response.data`. */
  it('remonte les erreurs de validation portées par l’ApiError', () => {
    const apiError = Object.assign(new Error('Request failed with status code 422'), {
      status: 422,
      errors: { code: ['Ce code n’est plus valable.'] },
    });

    expect(messageDErreur(apiError)).toBe('Ce code n’est plus valable.');
  });
});

describe('formatDateHeure', () => {
  it('écrit la date en français', () => {
    expect(formatDateHeure('2026-08-17', '09:00')).toBe('17 août 2026 à 09h00');
  });
});
