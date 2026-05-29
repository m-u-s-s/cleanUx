import React from 'react';
import { render, fireEvent } from '@testing-library/react-native';

jest.mock('react-native-reanimated', () => {
  const { View } = require('react-native');
  return {
    __esModule: true,
    default: { createAnimatedComponent: (c: any) => c, View },
    FadeIn: { duration: () => ({}) },
    FadeOut: { duration: () => ({}) },
  };
});

jest.mock('@react-navigation/native', () => ({
  useNavigation: () => ({ navigate: jest.fn() }),
}));

jest.mock('@/auth', () => ({
  useLogin: () => ({ mutateAsync: jest.fn(), isPending: false }),
  useRegister: () => ({ mutateAsync: jest.fn(), isPending: false }),
  useAuth: () => ({ isAuthenticated: false, isLoading: false }),
}));

jest.mock('@/theme', () => ({
  ...jest.requireActual('@/theme'),
  useThemeColors: () => ({ background: '#fff', text: '#000', card: '#fff', textMuted: '#64748b', textSecondary: '#94a3b8' }),
}));

jest.mock('@/ui', () => {
  const { View, Text, TextInput: RNInput } = require('react-native');
  return {
    Button: ({ label, onPress, loading }: any) => <Text onPress={onPress}>{label}</Text>,
    TextInput: ({ placeholder, onChangeText, value, ...props }: any) => (
      <RNInput placeholder={placeholder} onChangeText={onChangeText} value={value} {...props} />
    ),
    Divider: () => <View />,
    Icon: () => <View />,
    a11y: { pressable: (label: string) => ({ accessibilityLabel: label, accessibilityRole: 'button' }) },
  };
});

import { LoginScreen } from '../../src/screens/LoginScreen';

describe('LoginScreen', () => {
  it('renders without crashing', () => {
    const tree = render(<LoginScreen />);
    expect(tree.toJSON()).not.toBeNull();
  });

  it('shows CleanUx brand', () => {
    const { getByText } = render(<LoginScreen />);
    expect(getByText('CleanUx')).toBeTruthy();
  });

  it('renders email and password inputs', () => {
    const { getByPlaceholderText } = render(<LoginScreen />);
    expect(getByPlaceholderText('votre@email.com')).toBeTruthy();
    expect(getByPlaceholderText('••••••••')).toBeTruthy();
  });

  it('renders login button', () => {
    const { getByText } = render(<LoginScreen />);
    expect(getByText('Se connecter')).toBeTruthy();
  });

  it('shows register toggle', () => {
    const { getByText } = render(<LoginScreen />);
    expect(getByText(/S'inscrire/i)).toBeTruthy();
  });

  it('switches to register mode on toggle', () => {
    const { getByText } = render(<LoginScreen />);
    fireEvent.press(getByText(/S'inscrire/i));
    expect(getByText('Créer mon compte')).toBeTruthy();
  });

  it('shows forgot password link', () => {
    const { getByText } = render(<LoginScreen />);
    expect(getByText('Mot de passe oublié ?')).toBeTruthy();
  });
});
