/**
 * Identité de marque et accessibilité du mouvement sur l'écran de connexion prestataire.
 *
 * Deux garanties distinctes :
 *  - le wordmark affiche « brio » (et plus « CleanUx ») ;
 *  - l'animation reste décorative : quand le système demande moins de mouvement, la page garde
 *    tout son contenu et ses actions. Une identité animée qui escamote le formulaire en mode
 *    « réduire les animations » serait un défaut d'accessibilité, pas un effet de style.
 */
import React from 'react';
import { render, screen } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

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

const mockReducedMotion = { current: false };
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
    Divider: () => <View />,
    Icon: () => <View />,
    useReducedMotion: () => mockReducedMotion.current,
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

import { LoginScreen } from '@/screens/LoginScreen';

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

beforeEach(() => {
  mockReducedMotion.current = false;
});

describe("LoginScreen — identité et mouvement", () => {
  it('affiche le wordmark brio et plus la marque précédente', () => {
    renderScreen();

    expect(screen.getByText('brio')).toBeTruthy();
    expect(screen.queryByText('CleanUx')).toBeNull();
  });

  it('pose le halo décoratif derrière le contenu', () => {
    renderScreen();

    expect(screen.getByTestId('login-halo')).toBeTruthy();
  });

  it('garde tout le contenu et les actions quand le système réduit les animations', () => {
    mockReducedMotion.current = true;

    renderScreen();

    expect(screen.getByText('brio')).toBeTruthy();
    expect(screen.getByText('Espace prestataire')).toBeTruthy();
    expect(screen.getByLabelText('Email')).toBeTruthy();
    expect(screen.getByLabelText('Mot de passe')).toBeTruthy();
    expect(screen.getByLabelText('Se connecter')).toBeTruthy();
    // Le décor reste en place, simplement immobile.
    expect(screen.getByTestId('login-halo')).toBeTruthy();
  });
});
