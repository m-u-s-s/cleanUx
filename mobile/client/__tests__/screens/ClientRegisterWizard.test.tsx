/**
 * Inscription cliente : particulier ou société, une question par écran.
 *
 * Le formulaire posait ses six champs d'un bloc et ne distinguait pas les deux publics — alors
 * que `client_company` existe côté serveur et porte le multi-sites, les contrats B2B et la
 * facturation centralisée. Une société cliente n'avait aucun moyen de s'inscrire depuis
 * l'application.
 *
 * Ce que ces tests verrouillent : le type est réellement transmis au serveur, l'étape société
 * n'existe que pour une société, et les contrôles locaux évitent des requêtes vouées à un 422.
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
  useNavigation: () => ({ navigate: jest.fn(), goBack: jest.fn() }),
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
    FadeInRight: chainable,
    Easing: { inOut: () => undefined, out: () => undefined },
    useSharedValue: (v: any) => ({ value: v }),
    useAnimatedStyle: () => ({}),
    withTiming: (v: any) => v,
    withRepeat: (v: any) => v,
    withDelay: (_d: any, v: any) => v,
  };
});

const mockCaptcha: { mode: 'skipped' | 'pending' } = { mode: 'skipped' };

jest.mock('@/ui', () => {
  const { View, Text, TouchableOpacity, TextInput: RNTextInput } = require('react-native');
  const ReactLocal = require('react');
  return {
    Button: ({ label, onPress }: any) => (
      <TouchableOpacity onPress={onPress} accessibilityLabel={label}>
        <Text>{label}</Text>
      </TouchableOpacity>
    ),
    TextInput: ReactLocal.forwardRef(({ label, value, onChangeText }: any, ref: any) => (
      <RNTextInput ref={ref} accessibilityLabel={label} value={value} onChangeText={onChangeText} />
    )),
    Icon: () => <View />,
    ProgressBar: ({ step, totalSteps }: any) => <Text>{`Étape ${step} sur ${totalSteps}`}</Text>,
    useReducedMotion: () => false,
    TurnstileWidget: ({ onToken, onSkipped, testID }: any) => {
      ReactLocal.useEffect(() => {
        if (mockCaptcha.mode === 'skipped') onSkipped?.();
      }, []);

      return <View testID={testID} />;
    },
  };
});

jest.mock('@/theme', () => ({
  colors: {
    brand: { 50: '#eef2ff', 500: '#6366f1', 600: '#4f46e5' },
    warning: { 50: '#fffbeb', 700: '#b45309' },
    success: { 600: '#059669' },
    surface: { 200: '#e5e5e5', 400: '#a3a3a3', 600: '#525252', 700: '#404040' },
    danger: { 50: '#fef2f2', 500: '#ef4444', 600: '#dc2626', 700: '#b91c1c' },
    accent: { amber: '#ffb648', amberDeep: '#ff8a3d', cyan: '#4fe3d6', violet: '#8b7bff' },
    mode: { tool: { ink: '#0f172a', muted: '#64748b' } },
  },
  radius: { md: 14, lg: 22, pill: 999 },
  shadows: { md: {}, xs: {}, soft: {} },
  spacing: { xs: 4, sm: 8, md: 16, lg: 24, xl: 32 },
  typography: {
    fontSize: { xs: 12, sm: 14, base: 16, lg: 18, xl: 20, '4xl': 36 },
    fontWeight: { medium: '500', semibold: '600', bold: '700', extraBold: '800' },
  },
}));

import { apiClient } from '@/api';
import { ClientRegisterWizard } from '@/screens/auth/ClientRegisterWizard';

const apiMock = new MockAdapter(apiClient);

function renderWizard() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(
    <QueryClientProvider client={client}>
      <ClientRegisterWizard />
    </QueryClientProvider>,
  );
}

function registerCalls() {
  return apiMock.history['post']!.filter(c => c.url === '/auth/register');
}

/** Nom, email, mot de passe puis conditions : les quatre écrans communs aux deux types. */
async function fillCommonSteps() {
  await waitFor(() => screen.getByLabelText('Nom complet'));
  fireEvent.changeText(screen.getByLabelText('Nom complet'), 'Jean Client');
  fireEvent.press(screen.getByLabelText('Continuer'));

  await waitFor(() => screen.getByLabelText('Email'));
  fireEvent.changeText(screen.getByLabelText('Email'), 'jean@client.be');
  fireEvent.press(screen.getByLabelText('Continuer'));

  await waitFor(() => screen.getByLabelText('Mot de passe'));
  fireEvent.changeText(screen.getByLabelText('Mot de passe'), 'motdepasse123');
  fireEvent.press(screen.getByLabelText('Continuer'));

  await waitFor(() => screen.getByTestId('client-register-accept-terms'));
  fireEvent.press(screen.getByTestId('client-register-accept-terms'));
  fireEvent.press(screen.getByLabelText('Créer mon compte'));
}

beforeEach(() => {
  apiMock.reset();
  mockCaptcha.mode = 'skipped';
  apiMock.onPost('/auth/register').reply(201, {
    token: 'tok_1',
    user: { id: 1, name: 'Jean Client', email: 'jean@client.be', role: 'client' },
  });
});

describe('Inscription cliente', () => {
  it('propose les deux types de compte en premier', async () => {
    renderWizard();

    await waitFor(() => expect(screen.getByTestId('client-register-kind-individual')).toBeTruthy());
    expect(screen.getByTestId('client-register-kind-company')).toBeTruthy();
    // Une seule question à la fois : rien d'autre n'est demandé à ce stade.
    expect(screen.queryByLabelText('Email')).toBeNull();
  });

  it("n'avance pas tant qu'aucun type n'est choisi", async () => {
    renderWizard();

    await waitFor(() => screen.getByTestId('client-register-kind-individual'));
    fireEvent.press(screen.getByLabelText('Continuer'));

    await waitFor(() => expect(screen.getByTestId('client-register-step-error')).toBeTruthy());
    expect(screen.queryByLabelText('Nom complet')).toBeNull();
  });

  it('transmet le type particulier au serveur', async () => {
    renderWizard();

    await waitFor(() => screen.getByTestId('client-register-kind-individual'));
    fireEvent.press(screen.getByTestId('client-register-kind-individual'));
    fireEvent.press(screen.getByLabelText('Continuer'));

    await fillCommonSteps();

    await waitFor(() => expect(registerCalls()).toHaveLength(1));
    const body = JSON.parse(registerCalls()[0]!.data);
    expect(body.client_kind).toBe('individual');
    expect(body.company_name).toBeUndefined();
  });

  /** Un particulier n'a pas de société : l'étape correspondante sort du parcours. */
  it("ne demande rien sur la société à un particulier", async () => {
    renderWizard();

    await waitFor(() => screen.getByTestId('client-register-kind-individual'));
    fireEvent.press(screen.getByTestId('client-register-kind-individual'));
    fireEvent.press(screen.getByLabelText('Continuer'));

    await waitFor(() => expect(screen.getByLabelText('Nom complet')).toBeTruthy());
    expect(screen.queryByLabelText('Raison sociale')).toBeNull();
  });

  it('transmet la raison sociale et le numéro pour une société', async () => {
    renderWizard();

    await waitFor(() => screen.getByTestId('client-register-kind-company'));
    fireEvent.press(screen.getByTestId('client-register-kind-company'));
    fireEvent.press(screen.getByLabelText('Continuer'));

    await waitFor(() => screen.getByLabelText('Raison sociale'));
    fireEvent.changeText(screen.getByLabelText('Raison sociale'), 'Bureau Dupont SPRL');
    fireEvent.changeText(screen.getByLabelText("Numéro d'entreprise (optionnel)"), 'BE0202239951');
    fireEvent.press(screen.getByLabelText('Continuer'));

    await fillCommonSteps();

    await waitFor(() => expect(registerCalls()).toHaveLength(1));
    const body = JSON.parse(registerCalls()[0]!.data);
    expect(body.client_kind).toBe('company');
    expect(body.company_name).toBe('Bureau Dupont SPRL');
    expect(body.vat_number).toBe('BE0202239951');
  });

  it('refuse une société sans raison sociale sans appeler le serveur', async () => {
    renderWizard();

    await waitFor(() => screen.getByTestId('client-register-kind-company'));
    fireEvent.press(screen.getByTestId('client-register-kind-company'));
    fireEvent.press(screen.getByLabelText('Continuer'));

    await waitFor(() => screen.getByLabelText('Raison sociale'));
    fireEvent.press(screen.getByLabelText('Continuer'));

    await waitFor(() => expect(screen.getByTestId('client-register-step-error')).toBeTruthy());
    expect(registerCalls()).toHaveLength(0);
  });

  /**
   * Contrôle de clé côté client, miroir de la classe PHP : ce numéro part ensuite aux registres
   * officiels, le signaler pendant la frappe évite un rejet plusieurs jours plus tard.
   */
  it("refuse un numéro d'entreprise dont la clé est fausse", async () => {
    renderWizard();

    await waitFor(() => screen.getByTestId('client-register-kind-company'));
    fireEvent.press(screen.getByTestId('client-register-kind-company'));
    fireEvent.press(screen.getByLabelText('Continuer'));

    await waitFor(() => screen.getByLabelText('Raison sociale'));
    fireEvent.changeText(screen.getByLabelText('Raison sociale'), 'Bureau Dupont SPRL');
    fireEvent.changeText(screen.getByLabelText("Numéro d'entreprise (optionnel)"), 'BE0000000000');
    fireEvent.press(screen.getByLabelText('Continuer'));

    await waitFor(() => expect(screen.getByTestId('client-register-step-error')).toBeTruthy());
    expect(screen.queryByLabelText('Nom complet')).toBeNull();
  });

  it('refuse une adresse email sans domaine', async () => {
    renderWizard();

    await waitFor(() => screen.getByTestId('client-register-kind-individual'));
    fireEvent.press(screen.getByTestId('client-register-kind-individual'));
    fireEvent.press(screen.getByLabelText('Continuer'));

    await waitFor(() => screen.getByLabelText('Nom complet'));
    fireEvent.changeText(screen.getByLabelText('Nom complet'), 'Jean Client');
    fireEvent.press(screen.getByLabelText('Continuer'));

    await waitFor(() => screen.getByLabelText('Email'));
    // `includes('@')`, l'ancien contrôle, acceptait cette saisie.
    fireEvent.changeText(screen.getByLabelText('Email'), 'jean@');
    fireEvent.press(screen.getByLabelText('Continuer'));

    await waitFor(() => expect(screen.getByTestId('client-register-step-error')).toBeTruthy());
    expect(screen.queryByLabelText('Mot de passe')).toBeNull();
  });

  it("retient la requête tant que le captcha n'a pas répondu", async () => {
    mockCaptcha.mode = 'pending';

    renderWizard();

    await waitFor(() => screen.getByTestId('client-register-kind-individual'));
    fireEvent.press(screen.getByTestId('client-register-kind-individual'));
    fireEvent.press(screen.getByLabelText('Continuer'));

    await fillCommonSteps();

    await waitFor(() => expect(screen.getByTestId('client-register-form-error')).toBeTruthy());
    expect(registerCalls()).toHaveLength(0);
  });
});
