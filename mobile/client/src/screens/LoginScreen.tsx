import React, { useState } from 'react';
import {
  View,
  Text,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  TouchableOpacity,
} from 'react-native';
import Animated, { FadeIn, FadeInDown, FadeOut } from 'react-native-reanimated';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import {
  Button,
  TextInput,
  Divider,
  Icon,
  TurnstileWidget,
  a11y,
  useReducedMotion,
} from '@/ui';
// Sous-chemin volontaire : le barrel @/ui est substitue par les tests d'ecran, ce qui rendrait
// ces composants indefinis. Les importer directement preserve leur rendu reel.
import {
  AnimatedHalo,
  Wordmark,
  Stagger,
  FormError,
  authErrorMessage,
  authStyles as styles,
} from '@/ui/authShell';
import { useLogin, useRegister, useAuth } from '@/auth';
import { ApiError } from '@/api';
import { colors } from '@/theme';
import type { RootStackParamList } from '@/navigation/types';

/**
 * Porte d'entrée de l'application cliente.
 *
 * Elle affichait « CleanUx » en texte ambre sur fond nuit, alors que le kit d'interface partagé —
 * champs, boutons, séparateurs — est entièrement conçu pour une surface claire : chaque composant
 * luttait contre sa propre palette. L'application prestataire avait été refondue ; celle-ci était
 * restée en arrière, donnant deux identités à un même produit.
 *
 * L'habillage vient désormais de `@/ui/authShell`, partagé par les deux applications : même fond,
 * même wordmark, même mise en scène. Ce qui diffère reste ce qui doit différer — un client n'a ni
 * métier à déclarer ni numéro d'entreprise, et son inscription tient sur un écran.
 */
export function LoginScreen() {
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
              {mode === 'login' ? 'Connectez-vous à votre compte' : 'Créez votre compte'}
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
                <RegisterForm />
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
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [errors, setErrors] = useState<{ email?: string; password?: string }>({});
  // Erreur du formulaire, distincte des erreurs de champ : elle porte ce qui ne se rattache à
  // aucune saisie. Sans elle, une coupure réseau affichait la chaîne brute d'axios — « Network
  // Error » — sous l'adresse email, accusant une saisie pourtant correcte.
  const [formError, setFormError] = useState<string | null>(null);
  const login = useLogin();
  const { setUser } = useAuth();
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const passwordRef = React.useRef<any>(null);

  const validate = () => {
    const e: typeof errors = {};
    if (!email) e.email = 'Email requis';
    else if (!email.includes('@')) e.email = 'Email invalide';
    if (!password) e.password = 'Mot de passe requis';
    else if (password.length < 6) e.password = 'Min. 6 caractères';
    setErrors(e);
    return Object.keys(e).length === 0;
  };

  const handleLogin = async () => {
    setFormError(null);
    if (!validate()) return;
    try {
      const result = await login.mutateAsync({ email, password });
      setUser(result.user);
      a11y.announce('Connexion réussie');
    } catch (e: unknown) {
      const fieldErrors = e instanceof ApiError ? e.errors : undefined;
      if (fieldErrors) {
        setErrors({ email: fieldErrors.email?.[0], password: fieldErrors.password?.[0] });
        a11y.announce(`Erreur : ${fieldErrors.email?.[0] ?? fieldErrors.password?.[0] ?? ''}`);
      } else {
        const message = authErrorMessage(e, 'login');
        setFormError(message);
        a11y.announce(`Erreur : ${message}`);
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
            hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}
            accessibilityLabel={showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
            accessibilityRole="button"
          >
            <Icon name={showPassword ? 'eye-off-outline' : 'eye-outline'} size={20} color={colors.surface[400]} />
          </TouchableOpacity>
        </View>
      </Stagger>
      <Stagger index={2}>
        <TouchableOpacity
          onPress={() => navigation.navigate('ForgotPassword')}
          accessibilityRole="button"
          accessibilityLabel="Mot de passe oublié ?"
        >
          <Text style={styles.forgotText}>Mot de passe oublié ?</Text>
        </TouchableOpacity>
      </Stagger>
      {formError ? <FormError message={formError} onRetry={handleLogin} testID="login-form-error" /> : null}
      <Stagger index={3}>
        <Button label="Se connecter" onPress={handleLogin} fullWidth size="lg" loading={login.isPending} />
      </Stagger>
    </View>
  );
}

function RegisterForm() {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [acceptTerms, setAcceptTerms] = useState(false);
  // Jeton captcha : l'endpoint /auth/register porte le middleware `turnstile` et refusait
  // l'inscription en production, aucune app n'envoyant de jeton. 'skipped' = captcha non
  // configuré (dev), le serveur laisse alors passer.
  const [captchaToken, setCaptchaToken] = useState<string | null>(null);
  const [captchaSkipped, setCaptchaSkipped] = useState(false);
  const [errors, setErrors] = useState<{
    name?: string;
    email?: string;
    password?: string;
    confirmPassword?: string;
    acceptTerms?: string;
  }>({});
  const [formError, setFormError] = useState<string | null>(null);
  const register = useRegister();
  const { setUser } = useAuth();
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const emailRef = React.useRef<any>(null);
  const phoneRef = React.useRef<any>(null);
  const passwordRef = React.useRef<any>(null);
  const confirmRef = React.useRef<any>(null);

  const validate = () => {
    const e: typeof errors = {};
    if (!name.trim()) e.name = 'Nom requis';
    if (!email) e.email = 'Email requis';
    else if (!email.includes('@')) e.email = 'Email invalide';
    if (!password) e.password = 'Mot de passe requis';
    else if (password.length < 8) e.password = 'Min. 8 caractères';
    if (!confirmPassword) e.confirmPassword = 'Confirmation requise';
    else if (password !== confirmPassword) e.confirmPassword = 'Les mots de passe ne correspondent pas';
    if (!acceptTerms) e.acceptTerms = 'Vous devez accepter les CGU';
    setErrors(e);
    return Object.keys(e).length === 0;
  };

  const handleRegister = async () => {
    setFormError(null);
    if (!validate()) return;
    if (!captchaSkipped && !captchaToken) {
      setFormError('Veuillez patienter, la vérification anti-robot est en cours.');

      return;
    }
    try {
      const result = await register.mutateAsync({
        name,
        email,
        password,
        passwordConfirmation: confirmPassword,
        phone: phone || undefined,
        acceptTerms: true,
        captchaToken,
      });
      setUser(result.user);
    } catch (e: unknown) {
      const fieldErrors = e instanceof ApiError ? e.errors : undefined;
      if (fieldErrors) {
        setErrors({
          name: fieldErrors.name?.[0],
          email: fieldErrors.email?.[0],
          password: fieldErrors.password?.[0],
          confirmPassword: fieldErrors.password_confirmation?.[0],
        });
      } else {
        setFormError(authErrorMessage(e, 'register'));
      }
    }
  };

  return (
    <View style={styles.form}>
      <Stagger index={0}>
        <TextInput
          label="Nom complet"
          value={name}
          onChangeText={(t) => { setName(t); setErrors(prev => ({ ...prev, name: undefined })); }}
          error={errors.name}
          autoComplete="name"
          placeholder="Jean Dupont"
          autoFocus
          returnKeyType="next"
          onSubmitEditing={() => emailRef.current?.focus()}
        />
      </Stagger>
      <Stagger index={1}>
        <TextInput
          ref={emailRef}
          label="Email"
          value={email}
          onChangeText={(t) => { setEmail(t); setErrors(prev => ({ ...prev, email: undefined })); }}
          error={errors.email}
          keyboardType="email-address"
          autoCapitalize="none"
          autoComplete="email"
          placeholder="votre@email.com"
          returnKeyType="next"
          onSubmitEditing={() => phoneRef.current?.focus()}
        />
      </Stagger>
      <Stagger index={2}>
        <TextInput
          ref={phoneRef}
          label="Téléphone (optionnel)"
          value={phone}
          onChangeText={setPhone}
          keyboardType="phone-pad"
          placeholder="+32 470 12 34 56"
          returnKeyType="next"
          onSubmitEditing={() => passwordRef.current?.focus()}
        />
      </Stagger>
      <Stagger index={3}>
        <TextInput
          ref={passwordRef}
          label="Mot de passe"
          value={password}
          onChangeText={(t) => { setPassword(t); setErrors(prev => ({ ...prev, password: undefined })); }}
          error={errors.password}
          secureTextEntry
          placeholder="Min. 8 caractères"
          returnKeyType="next"
          onSubmitEditing={() => confirmRef.current?.focus()}
        />
      </Stagger>
      <Stagger index={4}>
        <TextInput
          ref={confirmRef}
          label="Confirmer le mot de passe"
          value={confirmPassword}
          onChangeText={(t) => { setConfirmPassword(t); setErrors(prev => ({ ...prev, confirmPassword: undefined })); }}
          error={errors.confirmPassword}
          secureTextEntry
          placeholder="••••••••"
          returnKeyType="done"
          onSubmitEditing={handleRegister}
        />
      </Stagger>
      <Stagger index={5}>
        <TouchableOpacity
          style={styles.termsRow}
          onPress={() => { setAcceptTerms(v => !v); setErrors(prev => ({ ...prev, acceptTerms: undefined })); }}
          accessibilityRole="checkbox"
          accessibilityState={{ checked: acceptTerms }}
          accessibilityLabel="J'accepte les conditions d'utilisation et la politique de confidentialité"
        >
          <View style={[styles.checkbox, acceptTerms && styles.checkboxChecked]} />
          <Text style={styles.termsText}>
            J'accepte les{' '}
            <Text style={styles.termsLink} onPress={() => navigation.navigate('Legal', { type: 'terms' })}>
              Conditions d'utilisation
            </Text>
            {' '}et la{' '}
            <Text style={styles.termsLink} onPress={() => navigation.navigate('Legal', { type: 'privacy' })}>
              Politique de confidentialité
            </Text>
          </Text>
        </TouchableOpacity>
      </Stagger>
      {errors.acceptTerms ? <Text style={styles.errorText}>{errors.acceptTerms}</Text> : null}
      <TurnstileWidget
        onToken={setCaptchaToken}
        onSkipped={() => setCaptchaSkipped(true)}
        testID="register-captcha"
      />
      {formError ? <FormError message={formError} onRetry={handleRegister} testID="register-form-error" /> : null}
      <Stagger index={6}>
        <Button label="Créer mon compte" onPress={handleRegister} fullWidth size="lg" loading={register.isPending} />
      </Stagger>
    </View>
  );
}
