/**
 * Adresse du serveur en développement : déduite de la machine qui sert Metro.
 *
 * Régression du 2026-09-14 : une IP figée dans .env a cassé la connexion à chaque changement de
 * Wi-Fi (192.168.1.18, puis 10.114.10.223, puis 192.168.1.16 en une heure). L'écran de connexion
 * annonçait « Impossible de joindre brio » alors que le compte et le serveur allaient bien.
 *
 * Runs from mobile/client via jest-expo (its jest.config.ts roots include ../shared/src).
 */

type EnvModule = typeof import('../env');

const VARIABLES = ['EXPO_PUBLIC_API_URL', 'EXPO_PUBLIC_WEB_URL'] as const;
type Variable = (typeof VARIABLES)[number];

const globalWithDev = globalThis as { __DEV__?: boolean };
const initialDev = globalWithDev.__DEV__;
const initialVariables = Object.fromEntries(VARIABLES.map((name) => [name, process.env[name]]));

function setVariables(values: Partial<Record<Variable, string | undefined>>): void {
  for (const name of VARIABLES) {
    const value = values[name];
    if (value === undefined) delete process.env[name];
    else process.env[name] = value;
  }
}

function loadEnv(options: {
  hostUri?: string;
  dev?: boolean;
  variables?: Partial<Record<Variable, string>>;
}): EnvModule['env'] {
  setVariables(options.variables ?? {});
  globalWithDev.__DEV__ = options.dev ?? true;

  let loaded: EnvModule['env'] | undefined;
  jest.isolateModules(() => {
    jest.doMock('expo-constants', () => ({
      __esModule: true,
      default: { expoConfig: options.hostUri === undefined ? null : { hostUri: options.hostUri } },
    }));
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    loaded = (require('../env') as EnvModule).env;
  });

  return loaded!;
}

afterEach(() => {
  jest.dontMock('expo-constants');
  globalWithDev.__DEV__ = initialDev;
  setVariables(initialVariables);
});

describe('env — adresse du serveur en développement', () => {
  it('vise la machine qui sert Metro, sur le port de artisan serve', () => {
    const env = loadEnv({ hostUri: '192.168.1.16:8081' });

    expect(env.apiUrl).toBe('http://192.168.1.16:8000/api');
    expect(env.webUrl).toBe('http://192.168.1.16:8000');
  });

  it('suit un changement de réseau sans toucher au .env', () => {
    expect(loadEnv({ hostUri: '10.114.10.223:8081' }).apiUrl).toBe('http://10.114.10.223:8000/api');
  });

  it('laisse une adresse explicite l’emporter sur l’hôte Metro', () => {
    const env = loadEnv({
      hostUri: '192.168.1.16:8081',
      variables: {
        EXPO_PUBLIC_API_URL: 'https://staging.example.test/api',
        EXPO_PUBLIC_WEB_URL: 'https://staging.example.test',
      },
    });

    expect(env.apiUrl).toBe('https://staging.example.test/api');
    expect(env.webUrl).toBe('https://staging.example.test');
  });

  it('traite une ligne laissée vide dans .env comme absente', () => {
    // Témoin : une valeur non vide l'emporte (test précédent). Ici elle est vide.
    const env = loadEnv({
      hostUri: '192.168.1.16:8081',
      variables: { EXPO_PUBLIC_API_URL: '', EXPO_PUBLIC_WEB_URL: '' },
    });

    // Garde contre un vert trompeur : la variable doit vraiment valoir '' et non avoir disparu.
    expect(process.env['EXPO_PUBLIC_API_URL']).toBe('');
    expect(env.apiUrl).toBe('http://192.168.1.16:8000/api');
    expect(env.webUrl).toBe('http://192.168.1.16:8000');
  });

  it('ignore l’hôte Metro hors développement', () => {
    // Témoin : le même hôte est bien suivi en développement.
    expect(loadEnv({ hostUri: '192.168.1.16:8081', dev: true }).apiUrl).toBe('http://192.168.1.16:8000/api');

    const env = loadEnv({ hostUri: '192.168.1.16:8081', dev: false });

    expect(env.apiUrl).toBe('http://localhost:8000/api');
    expect(env.webUrl).toBe('http://localhost:8000');
  });

  it('garde les valeurs locales quand Metro ne fournit pas d’hôte', () => {
    const env = loadEnv({});

    expect(env.apiUrl).toBe('http://localhost:8000/api');
    expect(env.webUrl).toBe('http://localhost:8000');
  });

  it.each(['evil.test@192.168.1.16:8081', 'bad host:8081', 'http://192.168.1.16:8081', '[fe80::1]:8081'])(
    'écarte un hôte qui n’est ni un nom ni une IPv4 : %s',
    (hostUri) => {
      // Témoin : un hôte valide passe dans les mêmes conditions.
      expect(loadEnv({ hostUri: 'my-pc.local:8081' }).apiUrl).toBe('http://my-pc.local:8000/api');

      expect(loadEnv({ hostUri }).apiUrl).toBe('http://localhost:8000/api');
    },
  );
});
