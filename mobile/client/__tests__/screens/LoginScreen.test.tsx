import React from 'react';
import { render, fireEvent } from '@testing-library/react-native';

jest.mock('react-native-reanimated', () => {
  const { View, Text } = require('react-native');
  const ReactLocal = require('react');
  const Passthrough = ReactLocal.forwardRef(({ children, ...rest }: any, ref: any) => (
    <View ref={ref} {...rest}>{children}</View>
  ));
  const TextPassthrough = ReactLocal.forwardRef(({ children, ...rest }: any, ref: any) => (
    <Text ref={ref} {...rest}>{children}</Text>
  ));
  // Chaînable : l'habillage partagé enchaîne .delay().duration().springify().damping().
  // Le faux précédent ne rendait qu'un objet nu, et le premier maillon levait.
  const chainable: any = new Proxy(() => chainable, { get: () => () => chainable });
  return {
    __esModule: true,
    default: { View: Passthrough, Text: TextPassthrough, createAnimatedComponent: (c: any) => c },
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
    // Le widget captcha s'appuie sur react-native-webview, dont le module natif n'existe pas
    // sous Jest. On le stube, et on signale immédiatement « pas de captcha requis » comme le
    // fait le vrai composant quand aucune clé publique n'est configurée.
    TurnstileWidget: ({ onSkipped, testID }: any) => {
      const ReactLocal = require('react');
      ReactLocal.useEffect(() => { onSkipped?.(); }, []);

      return <View testID={testID} />;
    },
    a11y: {
      pressable: (label: string) => ({ accessibilityLabel: label, accessibilityRole: 'button' }),
      announce: () => undefined,
    },
    // L'ecran interroge desormais la preference systeme d'animation reduite : sans elle dans ce
    // faux, l'ecran leve avant meme d'etre rendu.
    useReducedMotion: () => false,
  };
});

import { LoginScreen } from '../../src/screens/LoginScreen';

describe('LoginScreen', () => {
  it('renders without crashing', () => {
    const tree = render(<LoginScreen />);
    expect(tree.toJSON()).not.toBeNull();
  });

  // La marque affichee est « brio », et le wordmark vient de @/ui/authShell — partage avec
  // l'application prestataire pour que les deux portes d'entree aient la meme identite.
  it('shows brio brand', () => {
    const { getByText } = render(<LoginScreen />);
    expect(getByText('brio')).toBeTruthy();
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
