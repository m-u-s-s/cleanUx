/**
 * LE SECOND FACTEUR, DEPUIS L'APPLICATION.
 *
 * Le serveur refuse désormais d'émettre un jeton sur le seul mot de passe quand le compte a activé
 * la 2FA (`error_code: two_factor_required`) — mesuré le 2026-08-16, il en émettait un, et la
 * console d'administration native contournait ainsi la 2FA obligatoire du web.
 *
 * Côté application, il ne suffit pas que le hook accepte un code : il faut qu'un CHAMP apparaisse
 * et que la seconde tentative parte avec. Ce test PRESSE le bouton et lit le rendu — un test qui
 * vérifierait seulement que `useLogin` transmet le champ laisserait passer un écran sans champ, et
 * c'est exactement le défaut que ce dépôt produit en série.
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

const mockSetUser = jest.fn();
jest.mock('@/auth', () => {
  const actual = jest.requireActual('@/auth');

  return { ...actual, useAuth: () => ({ setUser: mockSetUser }) };
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

jest.mock('@/ui', () => {
  const { View, Text, TouchableOpacity, TextInput: RNTextInput } = require('react-native');
  const ReactLocal = require('react');

  return {
    Button: ({ label, onPress, loading }: any) => (
      <TouchableOpacity onPress={onPress} accessibilityLabel={label} disabled={loading}>
        <Text>{label}</Text>
      </TouchableOpacity>
    ),
    /*
     * Le doublon RELAIE `testID`. Sans cela le champ existe, se remplit, envoie sa valeur — et
     * `getByTestId` ne le trouve pas : le test échoue en accusant l'écran alors que c'est le
     * doublon qui perd l'attribut.
     */
    TextInput: ReactLocal.forwardRef(({ label, value, onChangeText, error, testID }: any, ref: any) => (
      <View>
        <RNTextInput
          ref={ref}
          testID={testID}
          accessibilityLabel={label}
          value={value}
          onChangeText={onChangeText}
        />
        {error ? <Text testID={`input-error-${label}`}>{error}</Text> : null}
      </View>
    )),
    Divider: () => <View />,
    Icon: () => <View />,
    useReducedMotion: () => false,
  };
});

jest.mock('@/theme', () => ({
  colors: {
    brand: { 50: '#eef2ff', 500: '#6366f1', 600: '#4f46e5' },
    warning: { 50: '#fffbeb', 700: '#b45309' },
    success: { 50: '#ecfdf5', 600: '#059669', 700: '#047857' },
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

function makeWrapper() {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return function Wrapper({ children }: { children: React.ReactNode }) {
    return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
  };
}

function saisirIdentifiants() {
  fireEvent.changeText(screen.getByLabelText('Email'), 'admin@brio.test');
  fireEvent.changeText(screen.getByLabelText('Mot de passe'), 'motdepasse');
  fireEvent.press(screen.getByLabelText('Se connecter'));
}

const REFUS_SANS_CODE = {
  ok: false,
  error_code: 'two_factor_required',
  message: "Ce compte est protégé par l'authentification à deux facteurs. Saisissez le code affiché par votre application d'authentification.",
};

beforeEach(() => {
  apiMock.reset();
  mockSetUser.mockClear();
});

describe('Connexion avec second facteur', () => {
  it("ne demande pas de code tant que le serveur ne le réclame pas", () => {
    render(<LoginScreen />, { wrapper: makeWrapper() });

    expect(screen.queryByTestId('login-two-factor-code')).toBeNull();
  });

  it('affiche le champ du code, et la raison, quand le serveur le réclame', async () => {
    apiMock.onPost('/auth/login').reply(403, REFUS_SANS_CODE);

    render(<LoginScreen />, { wrapper: makeWrapper() });
    saisirIdentifiants();

    await waitFor(() => {
      expect(screen.getByTestId('login-two-factor-code')).toBeTruthy();
    });

    // Le message du serveur passe intact : c'est lui qui dit OÙ trouver le code.
    expect(screen.getByText(/authentification à deux facteurs/i)).toBeTruthy();
    expect(mockSetUser).not.toHaveBeenCalled();
  });

  it('renvoie le code saisi et ouvre la session', async () => {
    apiMock.onPost('/auth/login').reply((config) => {
      const corps = JSON.parse(config.data as string);

      if (!corps.two_factor_code) {
        return [403, REFUS_SANS_CODE];
      }

      if (corps.two_factor_code !== '123456') {
        return [422, { message: 'Code invalide.', errors: { two_factor_code: ["Ce code d'authentification est invalide."] } }];
      }

      return [200, { ok: true, token: '9|jeton', user: { id: 1, name: 'Admin', email: 'admin@brio.test' } }];
    });

    render(<LoginScreen />, { wrapper: makeWrapper() });
    saisirIdentifiants();

    await waitFor(() => expect(screen.getByTestId('login-two-factor-code')).toBeTruthy());

    fireEvent.changeText(screen.getByLabelText("Code d'authentification"), '123456');
    fireEvent.press(screen.getByLabelText('Se connecter'));

    await waitFor(() => expect(mockSetUser).toHaveBeenCalled());
  });

  it('affiche un code refusé sous le champ du code, pas sous le mot de passe', async () => {
    apiMock.onPost('/auth/login').reply((config) => {
      const corps = JSON.parse(config.data as string);

      return corps.two_factor_code
        ? [422, { message: 'Code invalide.', errors: { two_factor_code: ["Ce code d'authentification est invalide."] } }]
        : [403, REFUS_SANS_CODE];
    });

    render(<LoginScreen />, { wrapper: makeWrapper() });
    saisirIdentifiants();

    await waitFor(() => expect(screen.getByTestId('login-two-factor-code')).toBeTruthy());

    fireEvent.changeText(screen.getByLabelText("Code d'authentification"), '000000');
    fireEvent.press(screen.getByLabelText('Se connecter'));

    await waitFor(() => {
      expect(screen.getByTestId("input-error-Code d'authentification")).toBeTruthy();
    });
    expect(screen.queryByTestId('input-error-Mot de passe')).toBeNull();
  });
});
