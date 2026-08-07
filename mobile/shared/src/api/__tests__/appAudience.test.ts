import MockAdapter from 'axios-mock-adapter';
import { apiClient } from '../client';
import { setAppAudience, getAppAudience } from '../appAudience';

/**
 * Chaque APK se déclare, sur CHAQUE requête.
 *
 * Le serveur refuse un compte prestataire dans l'application cliente et l'inverse, mais il ne peut
 * le faire que s'il sait à qui il parle. L'en-tête doit donc voyager avec la connexion ET avec la
 * reprise de session : bloquer la porte d'entrée ne sert à rien si la fenêtre reste ouverte.
 *
 * Une application qui oublie de se déclarer n'est pas refusée — le serveur laisse passer en
 * l'absence d'en-tête, pour ne pas déconnecter le parc déjà installé. C'est justement pourquoi ce
 * test existe : l'oubli serait SILENCIEUX.
 */
describe('déclaration de l’application', () => {
  let mock: MockAdapter;

  beforeEach(() => {
    mock = new MockAdapter(apiClient);
    setAppAudience(null);
  });

  afterEach(() => {
    mock.restore();
    setAppAudience(null);
  });

  it('n’envoie rien tant que l’application ne s’est pas déclarée', async () => {
    mock.onGet('/ping').reply(200, {});

    await apiClient.get('/ping');

    expect(mock.history['get']![0]!.headers?.['X-Brio-App']).toBeUndefined();
  });

  it('envoie l’en-tête une fois l’application déclarée', async () => {
    setAppAudience('provider');
    mock.onGet('/auth/me').reply(200, {});

    await apiClient.get('/auth/me');

    expect(mock.history['get']![0]!.headers?.['X-Brio-App']).toBe('provider');
  });

  it('accompagne aussi la connexion', async () => {
    setAppAudience('client');
    mock.onPost('/auth/login').reply(200, { token: 't', user: {} });

    await apiClient.post('/auth/login', {});

    expect(mock.history['post']![0]!.headers?.['X-Brio-App']).toBe('client');
  });

  it('retient la dernière déclaration', () => {
    setAppAudience('client');
    expect(getAppAudience()).toBe('client');
  });
});
