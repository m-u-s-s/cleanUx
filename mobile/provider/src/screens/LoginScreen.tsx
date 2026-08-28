/**
 * Écran d'authentification prestataire : connexion, ou entrée dans le wizard d'inscription.
 *
 * Les briques visuelles (fond animé, wordmark, mise en scène, feuille de style) vivent dans
 * ./auth/kit — l'inscription les partage. Le long formulaire d'inscription qui se trouvait ici a
 * cédé la place à RegisterWizard, une question par écran.
 */
import React, { useState } from 'react';
import {
  View,
  Text,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
} from 'react-native';
import Animated, { FadeIn, FadeInDown, FadeOut } from 'react-native-reanimated';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Button, TextInput, Divider, Icon, useReducedMotion } from '@/ui';
import { useLogin, useAuth, SECOND_FACTEUR_REQUIS } from '@/auth';
import { ApiError } from '@/api';
import { colors } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';
import { AnimatedHalo, Wordmark, Stagger, FormError, authErrorMessage, stylesFor } from './auth/kit';
import { RegisterWizard } from './auth/RegisterWizard';
import { useTraduction } from '@/i18n';

export function LoginScreen() {
  const styles = stylesFor(useThemeColors());
  const [mode, setMode] = useState<'login' | 'register'>('login');
  const reducedMotion = useReducedMotion();

  return (
    <View style={styles.container}>
      <AnimatedHalo />
      <KeyboardAvoidingView style={styles.flex} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
        <ScrollView contentContainerStyle={styles.scroll} keyboardShouldPersistTaps="handled">
          <View style={styles.header}>
            <Wordmark />
            <Animated.Text
              entering={reducedMotion ? undefined : FadeInDown.delay(260).duration(500)}
              style={styles.subtitle}
            >
              {mode === 'login' ? 'Espace prestataire' : 'Rejoindre en tant que prestataire'}
            </Animated.Text>
          </View>

          <Animated.View
            entering={reducedMotion ? undefined : FadeInDown.delay(340).duration(520).springify().damping(18)}
            style={styles.card}
          >
            {mode === 'login' ? (
              <Animated.View
                entering={reducedMotion ? undefined : FadeIn.duration(220)}
                exiting={reducedMotion ? undefined : FadeOut.duration(140)}
                key="login"
              >
                <LoginForm />
              </Animated.View>
            ) : (
              <Animated.View
                entering={reducedMotion ? undefined : FadeIn.duration(220)}
                exiting={reducedMotion ? undefined : FadeOut.duration(140)}
                key="register"
              >
                <RegisterWizard />
              </Animated.View>
            )}
          </Animated.View>

          <Animated.View
            entering={reducedMotion ? undefined : FadeInDown.delay(460).duration(500)}
            style={styles.footer}
          >
            <Divider label="ou" />
            <TouchableOpacity
              onPress={() => setMode(mode === 'login' ? 'register' : 'login')}
              accessibilityRole="button"
            >
              <Text style={styles.switchText}>
                {mode === 'login' ? "Pas encore de compte ? S'inscrire" : 'Déjà un compte ? Se connecter'}
              </Text>
            </TouchableOpacity>
          </Animated.View>
        </ScrollView>
      </KeyboardAvoidingView>
    </View>
  );
}

function LoginForm() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [errors, setErrors] = useState<{ email?: string; password?: string; twoFactorCode?: string }>({});
  // Erreur du formulaire, distincte des erreurs de champ : elle porte ce qui ne se rattache à
  // aucune saisie (réseau coupé, service indisponible, identifiants refusés).
  const [formError, setFormError] = useState<string | null>(null);
  /*
   * LE SECOND FACTEUR N'EST DEMANDÉ QUE QUAND LE SERVEUR LE RÉCLAME.
   *
   * Le compte n'annonce pas sa 2FA avant que le mot de passe soit reconnu — sans quoi l'écran
   * dirait à un inconnu qu'une adresse existe et qu'elle est protégée. Le champ apparaît donc en
   * réponse à `two_factor_required`, sur la même saisie, sans faire recommencer.
   */
  const [secondFacteurAttendu, setSecondFacteurAttendu] = useState(false);
  const [twoFactorCode, setTwoFactorCode] = useState('');
  const login = useLogin();
  const { setUser } = useAuth();
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const passwordRef = React.useRef<any>(null);
  const codeRef = React.useRef<any>(null);

  const validate = () => {
    const e: typeof errors = {};
    if (!email) e.email = 'Email requis';
    else if (!email.includes('@')) e.email = 'Email invalide';
    if (!password) e.password = 'Mot de passe requis';
    else if (password.length < 6) e.password = 'Min. 6 caractères';
    if (secondFacteurAttendu && !twoFactorCode) e.twoFactorCode = 'Code requis';
    setErrors(e);
    return Object.keys(e).length === 0;
  };

  const handleLogin = async () => {
    setFormError(null);
    if (!validate()) return;
    try {
      const result = await login.mutateAsync({
        email,
        password,
        twoFactorCode: twoFactorCode || undefined,
      });
      setUser(result.user);
    } catch (e: unknown) {
      if (e instanceof ApiError && e.errorCode === SECOND_FACTEUR_REQUIS) {
        setSecondFacteurAttendu(true);
        setFormError(e.message);
        // Le clavier suit le champ qui vient d'apparaître, sinon il faut le chercher.
        setTimeout(() => codeRef.current?.focus(), 0);

        return;
      }

      const fieldErrors = e instanceof ApiError ? e.errors : undefined;
      if (fieldErrors) {
        setErrors({
          email: fieldErrors.email?.[0],
          password: fieldErrors.password?.[0],
          twoFactorCode: fieldErrors.two_factor_code?.[0] ?? fieldErrors.recovery_code?.[0],
        });
      } else {
        setFormError(authErrorMessage(e, 'login'));
      }
    }
  };

  return (
    <View style={styles.form}>
      <Stagger index={0}>
        <TextInput
          label="Email"
          value={email}
          onChangeText={(t) => { setEmail(t); setErrors(prev => ({ ...prev, email: undefined })); }}
          error={errors.email}
          keyboardType="email-address"
          autoCapitalize="none"
          autoComplete="email"
          placeholder="votre@email.com"
          autoFocus
          returnKeyType="next"
          onSubmitEditing={() => passwordRef.current?.focus()}
        />
      </Stagger>
      <Stagger index={1}>
        <View style={styles.passwordWrapper}>
          <TextInput
            ref={passwordRef}
            label="Mot de passe"
            value={password}
            onChangeText={(t) => { setPassword(t); setErrors(prev => ({ ...prev, password: undefined })); }}
            error={errors.password}
            secureTextEntry={!showPassword}
            placeholder="••••••••"
            returnKeyType="done"
            onSubmitEditing={handleLogin}
          />
          <TouchableOpacity
            onPress={() => setShowPassword(v => !v)}
            style={styles.eyeButton}
            accessibilityLabel={showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
          >
            <Icon name={showPassword ? 'eye-off-outline' : 'eye-outline'} size={20} color={colors.surface[400]} />
          </TouchableOpacity>
        </View>
      </Stagger>
      {secondFacteurAttendu ? (
        <Stagger index={2}>
          <TextInput
            ref={codeRef}
            label="Code d'authentification"
            value={twoFactorCode}
            onChangeText={(t) => { setTwoFactorCode(t); setErrors(prev => ({ ...prev, twoFactorCode: undefined })); }}
            error={errors.twoFactorCode}
            keyboardType="number-pad"
            autoComplete="one-time-code"
            placeholder="123456"
            returnKeyType="done"
            onSubmitEditing={handleLogin}
            testID="login-two-factor-code"
          />
        </Stagger>
      ) : null}
      <Stagger index={2}>
        <TouchableOpacity onPress={() => navigation.navigate('ForgotPassword')} accessibilityRole="button">
          <Text style={styles.forgotText}>{tr('login.mot_de_passe_oublie')}</Text>
        </TouchableOpacity>
      </Stagger>
      {formError ? <FormError message={formError} onRetry={handleLogin} testID="login-form-error" /> : null}
      <Stagger index={3}>
        <Button label="Se connecter" onPress={handleLogin} fullWidth size="lg" loading={login.isPending} />
      </Stagger>
    </View>
  );
}
