/**
 * Inscription prestataire : ce que la requête doit porter, et quand elle ne doit pas partir.
 *
 * Deux défauts corrigés ici, tous deux invisibles jusqu'en production :
 *  - la requête n'annonçait pas le type de compte, donc le serveur créait un CLIENT. Comme toute
 *    la surface prestataire est gardée par `role:employe` — routes d'onboarding comprises — le
 *    compte obtenu était définitivement enfermé hors de tout.
 *  - la requête ne portait aucun jeton captcha alors que /auth/register exige le middleware
 *    `turnstile`, ce qui refusait l'inscription en production.
 */
import React from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import MockAdapter from 'axios-mock-adapter';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

jest.mock('@react-native-community/netinfo', () => ({
  addEventListener: jest.fn(() => () => undefined),
  fetch: jest.fn().mockResolvedValue({ isConnected: true }),
  default: {
    addEventListener: jest.fn(() => () => undefined),
    fetch: jest.fn().mockResolvedValue({ isConnected: true }),
  },
}));

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: jest.fn() }),
}));

jest.mock('@/auth', () => {
  const actual = jest.requireActual('@/auth');
  return { ...actual, useAuth: () => ({ setUser: jest.fn() }) };
});

jest.mock('react-native-reanimated', () => {
  const { View, Text } = require('react-native');
  const ReactLocal = require('react');
  const Passthrough = ReactLocal.forwardRef(({ children, ...rest }: any, ref: any) => (
    <View ref={ref} {...rest}>{children}</View>
  ));
  const TextPassthrough = ReactLocal.forwardRef(({ children, ...rest }: any, ref: any) => (
    <Text ref={ref} {...rest}>{children}</Text>
  ));
  const chainable: any = new Proxy(() => chainable, { get: () => () => chainable });
  return {
    __esModule: true,
    default: { View: Passthrough, Text: TextPassthrough },
    FadeIn: chainable,
    FadeOut: chainable,
    FadeInDown: chainable,
    Easing: { inOut: () => undefined, out: () => undefined, ease: undefined, cubic: undefined },
    useSharedValue: (v: any) => ({ value: v }),
    useAnimatedStyle: () => ({}),
    withTiming: (v: any) => v,
    withRepeat: (v: any) => v,
    withDelay: (_d: any, v: any) => v,
  };
});

/**
 * Pilote le widget captcha depuis chaque test : 'skipped' reproduit l'absence de clé publique
 * (dev), 'token' une résolution réussie, 'pending' un widget qui n'a pas encore répondu.
 */
const mockCaptcha: { mode: 'skipped' | 'token' | 'pending' } = { mode: 'skipped' };

jest.mock('@/ui', () => {
  const { View, Text, TouchableOpacity, TextInput: RNTextInput } = require('react-native');
  const ReactLocal = require('react');
  return {
    Button: ({ label, onPress }: any) => (
      <TouchableOpacity onPress={onPress} accessibilityLabel={label}>
        <Text>{label}</Text>
      </TouchableOpacity>
    ),
    TextInput: ReactLocal.forwardRef(({ label, value, onChangeText, error }: any, ref: any) => (
      <View>
        <RNTextInput ref={ref} accessibilityLabel={label} value={value} onChangeText={onChangeText} />
        {error ? <Text testID={`input-error-${label}`}>{error}</Text> : null}
      </View>
    )),
    Divider: () => <View />,
    Icon: () => <View />,
    useReducedMotion: () => false,
    TurnstileWidget: ({ onToken, onSkipped, testID }: any) => {
      ReactLocal.useEffect(() => {
        if (mockCaptcha.mode === 'skipped') onSkipped?.();
        if (mockCaptcha.mode === 'token') onToken?.('jeton-turnstile-test');
      }, []);

      return <View testID={testID} />;
    },
  };
});

jest.mock('@/theme', () => ({
  colors: {
    brand: { 50: '#eef2ff', 500: '#6366f1', 600: '#4f46e5' },
    warning: { 50: '#fffbeb', 700: '#b45309' },
    accent: { amber: '#ffb648', amberDeep: '#ff8a3d', cyan: '#4fe3d6', violet: '#8b7bff' },
    surface: { 200: '#e5e5e5', 400: '#a3a3a3', 500: '#737373', 600: '#525252', 700: '#404040' },
    danger: { 50: '#fef2f2', 500: '#ef4444', 600: '#dc2626', 700: '#b91c1c' },
    mode: { tool: { ink: '#0f172a', muted: '#64748b' } },
  },
  radius: { md: 14, lg: 22, pill: 999 },
  shadows: { md: {} },
  spacing: { xs: 4, sm: 8, md: 16, lg: 24, xl: 32, '2xl': 40, '3xl': 48 },
  typography: {
    fontSize: { xs: 12, sm: 14, base: 16, lg: 18, '4xl': 36 },
    fontWeight: { medium: '500', semibold: '600', extraBold: '800' },
  },
}));

import { apiClient } from '@/api';
import { LoginScreen } from '@/screens/LoginScreen';

const apiMock = new MockAdapter(apiClient);

function renderScreen() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });
  return render(
    <QueryClientProvider client={client}>
      <LoginScreen />
    </QueryClientProvider>,
  );
}

/** Champs communs aux deux types d'inscription, métier compris. */
async function fillCommonFields() {
  await waitFor(() => screen.getByTestId(`register-trade-${TRADE_ID}`));
  fireEvent.press(screen.getByTestId(`register-trade-${TRADE_ID}`));
  fireEvent.changeText(screen.getByLabelText('Nom complet'), 'Jean Dupont');
  fireEvent.changeText(screen.getByLabelText('Email'), 'jean@exemple.be');
  fireEvent.changeText(screen.getByLabelText('Mot de passe'), 'motdepasse123');
  fireEvent.changeText(screen.getByLabelText('Confirmer le mot de passe'), 'motdepasse123');
  fireEvent.press(
    screen.getByLabelText("J'accepte les conditions d'utilisation et la politique de confidentialité"),
  );
}

/** Ouvre l'inscription et choisit un type — le formulaire n'existe pas avant ce choix. */
async function openRegister(kind: 'independent' | 'company' = 'independent') {
  fireEvent.press(screen.getByText("Pas encore de compte ? S'inscrire"));
  await waitFor(() => screen.getByTestId(`register-kind-${kind}`));
  fireEvent.press(screen.getByTestId(`register-kind-${kind}`));
  await waitFor(() => screen.getByLabelText('Nom complet'));
}

async function openRegisterAndSubmit() {
  await openRegister();
  await fillCommonFields();
  fireEvent.press(screen.getByLabelText('Créer mon compte'));
}

const TRADE_ID = 3;

beforeEach(() => {
  apiMock.reset();
  mockCaptcha.mode = 'skipped';
  // Référentiel métiers : endpoints PUBLICS, appelés par le formulaire avant que le compte
  // n'existe. Un métier sans question réglementaire, pour garder ces tests sur leur sujet.
  apiMock.onGet('/trades').reply(200, { data: [{ id: TRADE_ID, name: 'Jardinage', slug: 'jardinage' }] });
  apiMock.onGet(`/trades/${TRADE_ID}/provider-fields`).reply(200, { fields: [] });
  apiMock.onPost('/auth/register').reply(201, {
    token: 'tok_123',
    user: { id: 7, name: 'Jean Dupont', email: 'jean@exemple.be', role: 'employe' },
  });
});

describe("Inscription depuis l'app prestataire", () => {
  it('annonce au serveur qu\'il faut créer un compte prestataire', async () => {
    renderScreen();
    await openRegisterAndSubmit();

    await waitFor(() => {
      expect(apiMock.history['post']!.filter(c => c.url === '/auth/register')).toHaveLength(1);
    });
    const body = JSON.parse(apiMock.history['post']![0]!.data);
    expect(body.account_type).toBe('provider');
  });

  it('joint le jeton captcha quand le widget en a produit un', async () => {
    mockCaptcha.mode = 'token';

    renderScreen();
    await openRegisterAndSubmit();

    await waitFor(() => {
      expect(apiMock.history['post']!.filter(c => c.url === '/auth/register')).toHaveLength(1);
    });
    expect(apiMock.history['post']![0]!.headers?.['X-Turnstile-Token']).toBe('jeton-turnstile-test');
  });

  it("ne montre aucun champ tant que le type de compte n'est pas choisi", async () => {
    renderScreen();
    fireEvent.press(screen.getByText("Pas encore de compte ? S'inscrire"));

    await waitFor(() => screen.getByTestId('register-kind-independent'));
    // Les deux cases sont là, le formulaire non.
    expect(screen.getByTestId('register-kind-company')).toBeTruthy();
    expect(screen.queryByLabelText('Nom complet')).toBeNull();
    expect(screen.queryByLabelText('Nom de la société')).toBeNull();
    expect(screen.queryByLabelText('Créer mon compte')).toBeNull();
  });

  it("révèle le formulaire indépendant après le choix, sans champs de société", async () => {
    renderScreen();
    await openRegister('independent');

    expect(screen.queryByLabelText('Nom de la société')).toBeNull();

    await fillCommonFields();
    fireEvent.press(screen.getByLabelText('Créer mon compte'));

    await waitFor(() => {
      expect(apiMock.history['post']!.filter(c => c.url === '/auth/register')).toHaveLength(1);
    });
    const body = JSON.parse(apiMock.history['post']![0]!.data);
    expect(body.provider_kind).toBe('independent');
    expect(body.company_name).toBeUndefined();
  });

  it('transmet la raison sociale et la TVA quand on choisit société', async () => {
    renderScreen();
    await openRegister('company');
    await waitFor(() => screen.getByLabelText('Nom de la société'));

    fireEvent.changeText(screen.getByLabelText('Nom de la société'), 'Nettoyage Dupont SPRL');
    fireEvent.changeText(screen.getByLabelText('Numéro de TVA (optionnel)'), 'BE0123456789');
    await fillCommonFields();
    fireEvent.press(screen.getByLabelText('Créer mon compte'));

    await waitFor(() => {
      expect(apiMock.history['post']!.filter(c => c.url === '/auth/register')).toHaveLength(1);
    });
    const body = JSON.parse(apiMock.history['post']![0]!.data);
    expect(body.provider_kind).toBe('company');
    expect(body.company_name).toBe('Nettoyage Dupont SPRL');
    expect(body.vat_number).toBe('BE0123456789');
  });

  it('refuse une société sans raison sociale sans appeler le serveur', async () => {
    renderScreen();
    await openRegister('company');
    await fillCommonFields();
    fireEvent.press(screen.getByLabelText('Créer mon compte'));

    await waitFor(() => {
      expect(screen.getByTestId('input-error-Nom de la société')).toBeTruthy();
    });
    // Le serveur refuserait de toute façon (required_if) : inutile de l'appeler.
    expect(apiMock.history['post']!.filter(c => c.url === '/auth/register')).toHaveLength(0);
  });

  it('transmet le métier déclaré et les réponses à ses questions', async () => {
    // Un métier réglementé : le serveur ajoute ses questions selon ses propres exigences.
    apiMock.onGet(`/trades/${TRADE_ID}/provider-fields`).reply(200, {
      fields: [
        { key: 'experience_years', type: 'number', label: "Années d'expérience", required: true },
        { key: 'certification_reference', type: 'text', label: 'Référence de certification', required: true },
      ],
    });

    renderScreen();
    await openRegister();
    await fillCommonFields();

    await waitFor(() => screen.getByLabelText("Années d'expérience *"));
    fireEvent.changeText(screen.getByLabelText("Années d'expérience *"), '7');
    fireEvent.changeText(screen.getByLabelText('Référence de certification *'), 'RGIE-2024-118');
    fireEvent.press(screen.getByLabelText('Créer mon compte'));

    await waitFor(() => {
      expect(apiMock.history['post']!.filter(c => c.url === '/auth/register')).toHaveLength(1);
    });
    const body = JSON.parse(apiMock.history['post']![0]!.data);
    expect(body.trade_id).toBe(TRADE_ID);
    expect(body.trade_answers).toEqual({ experience_years: '7', certification_reference: 'RGIE-2024-118' });
  });

  it('refuse de soumettre sans métier choisi', async () => {
    renderScreen();
    await openRegister();

    // Tous les champs SAUF le métier.
    fireEvent.changeText(screen.getByLabelText('Nom complet'), 'Jean Dupont');
    fireEvent.changeText(screen.getByLabelText('Email'), 'jean@exemple.be');
    fireEvent.changeText(screen.getByLabelText('Mot de passe'), 'motdepasse123');
    fireEvent.changeText(screen.getByLabelText('Confirmer le mot de passe'), 'motdepasse123');
    fireEvent.press(
      screen.getByLabelText("J'accepte les conditions d'utilisation et la politique de confidentialité"),
    );
    fireEvent.press(screen.getByLabelText('Créer mon compte'));

    await waitFor(() => {
      expect(screen.getByText('Choisissez votre métier')).toBeTruthy();
    });
    // Sans métier, le prestataire ne recevrait aucune mission : inutile d'appeler le serveur.
    expect(apiMock.history['post']!.filter(c => c.url === '/auth/register')).toHaveLength(0);
  });

  it('retient la requête tant que le captcha n\'a pas répondu', async () => {
    mockCaptcha.mode = 'pending';

    renderScreen();
    await openRegisterAndSubmit();

    await waitFor(() => {
      expect(screen.getByTestId('register-form-error')).toBeTruthy();
    });
    expect(screen.getByText(/vérification anti-robot/i)).toBeTruthy();
    // Une requête vouée à un 400 ne doit pas partir.
    expect(apiMock.history['post']!.filter(c => c.url === '/auth/register')).toHaveLength(0);
  });
});
