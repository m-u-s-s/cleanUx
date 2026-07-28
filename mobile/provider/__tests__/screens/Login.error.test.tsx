/**
 * Failure-path tests for the provider LoginScreen.
 *
 * Regression: the catch block put EVERY non-validation failure into the email field
 * (`setErrors({ email: e.message })`). A network outage therefore rendered the raw axios
 * string "Network Error" under the email input, as if the address the user had typed were
 * malformed — pointing the blame at correct input and offering no way forward.
 *
 * Covers:
 *  - network failure (status 0) -> form-level network message, email field untouched
 *  - 401 -> form-level credentials message, email field untouched
 *  - 500 -> form-level generic message, email field untouched
 *  - 422 with field errors -> still mapped per field (no regression)
 *  - retry re-issues the login request
 *  - happy path authenticates and shows no error
 */
import React from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import MockAdapter from 'axios-mock-adapter';

// ── Module mocks ──────────────────────────────────────────────────────────────

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

// L'ecran anime son entree (halo, wordmark, apparitions echelonnees). Ces tests portent sur le
// comportement d'erreur, pas sur le mouvement : on rend les animations transparentes tout en
// gardant les composants montes, sinon rien ne serait interrogeable.
jest.mock('react-native-reanimated', () => {
  const { View, Text } = require('react-native');
  const ReactLocal = require('react');
  const Passthrough = ReactLocal.forwardRef(({ children, ...rest }: any, ref: any) => (
    <View ref={ref} {...rest}>{children}</View>
  ));
  const TextPassthrough = ReactLocal.forwardRef(({ children, ...rest }: any, ref: any) => (
    <Text ref={ref} {...rest}>{children}</Text>
  ));
  // Les descripteurs d'entree se chainent (.delay().duration().springify().damping()).
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

// The email error is exposed under its own testID so a test can prove the message is NOT
// attached to the field — the whole point of the regression.
jest.mock('@/ui', () => {
  const { View, Text, TouchableOpacity, TextInput: RNTextInput } = require('react-native');
  const ReactLocal = require('react');
  return {
    Button: ({ label, onPress, loading }: any) => (
      <TouchableOpacity onPress={onPress} accessibilityLabel={label} disabled={loading}>
        <Text>{label}</Text>
      </TouchableOpacity>
    ),
    TextInput: ReactLocal.forwardRef(({ label, value, onChangeText, error }: any, ref: any) => (
      <View>
        <RNTextInput
          ref={ref}
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
    // Pas de stub d'ErrorState ici : le bandeau d'erreur est un composant local à l'écran
    // (ErrorState est centré avec un titre « Oups ! », prévu pour une section entière en échec).
    // Ces tests exercent donc le vrai rendu, pas un doublon de test.
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

// ── Imports ───────────────────────────────────────────────────────────────────

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

async function submitLogin() {
  fireEvent.changeText(screen.getByLabelText('Email'), 'jean@exemple.be');
  fireEvent.changeText(screen.getByLabelText('Mot de passe'), 'motdepasse');
  fireEvent.press(screen.getByLabelText('Se connecter'));
}

beforeEach(() => {
  apiMock.reset();
  mockSetUser.mockClear();
});

// ── Tests ─────────────────────────────────────────────────────────────────────

describe('LoginScreen failure paths', () => {
  it('reports a network outage as a form-level problem, not as a bad email', async () => {
    apiMock.onPost('/auth/login').networkError();

    render(<LoginScreen />, { wrapper: makeWrapper() });
    await submitLogin();

    await waitFor(() => {
      expect(screen.getByTestId('login-form-error')).toBeTruthy();
    });
    expect(screen.getByText(/connexion internet/i)).toBeTruthy();
    // The regression: "Network Error" rendered under the email input.
    expect(screen.queryByTestId('input-error-Email')).toBeNull();
    expect(screen.queryByText(/network error/i)).toBeNull();
  });

  it('reports rejected credentials at form level without blaming the email field', async () => {
    apiMock.onPost('/auth/login').reply(401, {
      ok: false,
      error_code: 'invalid_credentials',
      message: 'These credentials do not match our records.',
    });

    render(<LoginScreen />, { wrapper: makeWrapper() });
    await submitLogin();

    await waitFor(() => {
      expect(screen.getByText(/identifiants/i)).toBeTruthy();
    });
    expect(screen.queryByTestId('input-error-Email')).toBeNull();
  });

  it('falls back to a generic message on a server error', async () => {
    apiMock.onPost('/auth/login').reply(500);

    render(<LoginScreen />, { wrapper: makeWrapper() });
    await submitLogin();

    await waitFor(() => {
      expect(screen.getByTestId('login-form-error')).toBeTruthy();
    });
    expect(screen.queryByTestId('input-error-Email')).toBeNull();
  });

  it('still attaches 422 validation errors to their own fields', async () => {
    apiMock.onPost('/auth/login').reply(422, {
      ok: false,
      error_code: 'validation_error',
      message: 'The given data was invalid.',
      errors: { email: ['Cet email est inconnu.'], password: ['Mot de passe trop court.'] },
    });

    render(<LoginScreen />, { wrapper: makeWrapper() });
    await submitLogin();

    await waitFor(() => {
      expect(screen.getByTestId('input-error-Email')).toBeTruthy();
    });
    expect(screen.getByText('Cet email est inconnu.')).toBeTruthy();
    expect(screen.getByText('Mot de passe trop court.')).toBeTruthy();
    // A field-level failure is not also a form-level one.
    expect(screen.queryByTestId('login-form-error')).toBeNull();
  });

  it('retries the login request when the user taps Réessayer', async () => {
    apiMock.onPost('/auth/login').networkError();

    render(<LoginScreen />, { wrapper: makeWrapper() });
    await submitLogin();

    await waitFor(() => screen.getByLabelText('Réessayer'));
    const before = apiMock.history['post']!.filter(c => c.url === '/auth/login').length;

    fireEvent.press(screen.getByLabelText('Réessayer'));

    await waitFor(() => {
      const after = apiMock.history['post']!.filter(c => c.url === '/auth/login').length;
      expect(after).toBeGreaterThan(before);
    });
  });

  it('authenticates and shows no error on the happy path', async () => {
    apiMock.onPost('/auth/login').reply(200, {
      token: 'tok_123',
      user: { id: 1, name: 'Jean', email: 'jean@exemple.be', role: 'employe' },
    });

    render(<LoginScreen />, { wrapper: makeWrapper() });
    await submitLogin();

    await waitFor(() => {
      expect(mockSetUser).toHaveBeenCalledWith(expect.objectContaining({ email: 'jean@exemple.be' }));
    });
    expect(screen.queryByTestId('login-form-error')).toBeNull();
    expect(screen.queryByTestId('input-error-Email')).toBeNull();
  });
});
