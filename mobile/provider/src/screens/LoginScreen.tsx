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
import Animated, {
  FadeIn,
  FadeInDown,
  FadeOut,
  Easing,
  useAnimatedStyle,
  useSharedValue,
  withDelay,
  withRepeat,
  withTiming,
} from 'react-native-reanimated';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Button, TextInput, Divider, Icon, TurnstileWidget, useReducedMotion } from '@/ui';
import { useLogin, useRegister, useAuth } from '@/auth';
import { useTrades, useTradeProviderFields, type ProviderField } from '@/trades';
import { ApiError } from '@/api';
import { colors, radius, shadows, spacing, typography } from '@/theme';
import type { RootStackParamList } from '@/navigation/types';

/**
 * Fond clair légèrement bleuté : le kit partagé (TextInput, Button, Divider) est entièrement
 * conçu pour une surface claire — champs en surface[50], texte en surface[900]. L'ancien fond
 * nuit était l'élément dissonant, il obligeait chaque composant à lutter contre sa propre palette.
 */
const CANVAS = '#F7F8FB';

/** Cadence des entrées en scène : chaque élément décale son apparition sur cette base. */
const STAGGER = 70;

/**
 * Traduit un échec d'authentification en message affichable.
 *
 * Ces écrans plaçaient auparavant TOUTE erreur non-validation dans le champ email
 * (`setErrors({ email: e.message })`). Une coupure réseau affichait donc la chaîne brute
 * d'axios, « Network Error », sous l'adresse saisie — accusant une saisie correcte, en anglais,
 * et sans indiquer quoi faire. Un problème qui ne vient pas d'un champ doit se dire au niveau
 * du formulaire.
 *
 * `status === 0` est la convention maison pour un échec réseau : l'intercepteur d'apiClient
 * construit l'ApiError sans réponse serveur (voir offlineAwareClient).
 */
function authErrorMessage(error: unknown, action: 'login' | 'register'): string {
  const fallback = action === 'login'
    ? 'Connexion impossible pour le moment.'
    : 'Création du compte impossible pour le moment.';

  if (!(error instanceof ApiError)) {
    return fallback;
  }

  if (error.status === 0) {
    return 'Impossible de joindre brio. Vérifiez votre connexion internet, puis réessayez.';
  }
  if (error.status === 429) {
    return 'Trop de tentatives. Patientez une minute avant de réessayer.';
  }
  if (error.status >= 500) {
    return 'Le service est momentanément indisponible. Réessayez dans un instant.';
  }
  if (action === 'login' && (error.status === 401 || error.status === 422)) {
    return 'Identifiants incorrects.';
  }

  return fallback;
}

/**
 * Halo diffus derrière le wordmark.
 *
 * Approximation d'un dégradé radial sans dépendance supplémentaire : `expo-linear-gradient`
 * n'est pas installé et l'ajouter imposerait de reconstruire le dev client. Empiler des cercles
 * concentriques de très faible opacité produit une décroissance douce équivalente, et reste
 * purement RN — donc valable en Expo Go comme en build autonome.
 */
function SoftBlob({ color, size, rings = 5 }: { color: string; size: number; rings?: number }) {
  return (
    <View style={[styles.blob, { width: size, height: size }]} pointerEvents="none">
      {Array.from({ length: rings }).map((_, i) => {
        const scale = 1 - i / (rings + 1);
        const dimension = size * scale;

        return (
          <View
            key={i}
            style={{
              position: 'absolute',
              width: dimension,
              height: dimension,
              borderRadius: dimension / 2,
              backgroundColor: color,
              opacity: 0.05,
            }}
          />
        );
      })}
    </View>
  );
}

/**
 * Respiration lente de l'arrière-plan. Coupée quand le système demande moins d'animations :
 * un mouvement permanent est précisément ce que ce réglage d'accessibilité vise.
 */
function AnimatedHalo() {
  const reducedMotion = useReducedMotion();
  const breath = useSharedValue(0);

  React.useEffect(() => {
    if (reducedMotion) return;
    breath.value = withRepeat(
      withTiming(1, { duration: 7000, easing: Easing.inOut(Easing.ease) }),
      -1,
      true,
    );
  }, [reducedMotion]);

  const amberStyle = useAnimatedStyle(() => ({
    transform: [
      { translateX: -40 + breath.value * 26 },
      { translateY: -30 + breath.value * 18 },
      { scale: 1 + breath.value * 0.12 },
    ],
  }));

  const cyanStyle = useAnimatedStyle(() => ({
    transform: [
      { translateX: 70 - breath.value * 30 },
      { translateY: 10 + breath.value * 26 },
      { scale: 1.1 - breath.value * 0.1 },
    ],
  }));

  const violetStyle = useAnimatedStyle(() => ({
    transform: [
      { translateX: 10 + breath.value * 18 },
      { translateY: 90 - breath.value * 20 },
      { scale: 0.95 + breath.value * 0.14 },
    ],
  }));

  return (
    <View style={styles.halo} pointerEvents="none" testID="login-halo">
      <Animated.View style={[styles.haloLayer, amberStyle]}>
        <SoftBlob color={colors.accent.amber} size={300} />
      </Animated.View>
      <Animated.View style={[styles.haloLayer, cyanStyle]}>
        <SoftBlob color={colors.accent.cyan} size={260} />
      </Animated.View>
      <Animated.View style={[styles.haloLayer, violetStyle]}>
        <SoftBlob color={colors.accent.violet} size={220} />
      </Animated.View>
    </View>
  );
}

/**
 * Wordmark « brio ». Les lettres se resserrent depuis un espacement large jusqu'à leur position
 * définitive — l'animation porte l'identité plutôt qu'un simple fondu.
 */
function Wordmark() {
  const reducedMotion = useReducedMotion();
  const spread = useSharedValue(reducedMotion ? 0 : 14);

  React.useEffect(() => {
    if (reducedMotion) {
      spread.value = 0;

      return;
    }
    spread.value = withDelay(120, withTiming(0, { duration: 900, easing: Easing.out(Easing.cubic) }));
  }, [reducedMotion]);

  const letterStyle = useAnimatedStyle(() => ({ letterSpacing: 2 + spread.value }));

  return (
    <View style={styles.wordmarkRow}>
      <Animated.Text
        entering={reducedMotion ? undefined : FadeIn.duration(700)}
        style={[styles.brand, letterStyle]}
        accessibilityRole="header"
      >
        brio
      </Animated.Text>
      <Animated.View
        entering={reducedMotion ? undefined : FadeIn.delay(700).duration(500)}
        style={styles.brandDot}
      />
    </View>
  );
}

/** Décale l'entrée d'un bloc de formulaire selon son rang, sans animer quand c'est refusé. */
function Stagger({ index, children }: { index: number; children: React.ReactNode }) {
  const reducedMotion = useReducedMotion();

  return (
    <Animated.View
      entering={reducedMotion ? undefined : FadeInDown.delay(index * STAGGER).duration(420).springify().damping(16)}
    >
      {children}
    </Animated.View>
  );
}

/**
 * Choix du type d'inscription. Deux cartes plutôt qu'une liste déroulante : c'est la première
 * décision du parcours, elle change la suite du formulaire, et elle doit se lire d'un coup d'œil.
 */
function KindChoice({
  value,
  onChange,
}: {
  value: 'independent' | 'company' | null;
  onChange: (kind: 'independent' | 'company') => void;
}) {
  // Une teinte par type, pour que les deux cases se distinguent au-delà de la seule bordure.
  // Les couleurs sont choisies sur leur contraste mesuré, pas à l'œil : accent.amber (1,74:1) et
  // accent.amberDeep (2,35:1) échouent au seuil de 3:1 exigé d'un élément d'interface sur fond
  // clair — ils seraient quasi invisibles comme indicateur de sélection. warning[700] (4,73:1)
  // et brand[600] (5,92:1) passent, y compris comme texte sur leur propre lavis.
  //
  // `as const` plutôt qu'une annotation : le prop `name` d'Icon attend une union de noms
  // Ionicons, qu'un `string` élargi ne satisfait pas.
  const options = [
    {
      kind: 'independent',
      title: 'Indépendant',
      hint: 'Je travaille seul',
      icon: 'person-outline',
      accent: colors.warning[700],
      wash: colors.warning[50],
    },
    {
      kind: 'company',
      title: 'Société',
      hint: "J'ai une équipe",
      icon: 'business-outline',
      accent: colors.brand[600],
      wash: colors.brand[50],
    },
  ] as const;

  return (
    <View style={styles.kindRow} accessibilityRole="radiogroup">
      {options.map(option => {
        const selected = value === option.kind;

        return (
          <TouchableOpacity
            key={option.kind}
            style={[
              styles.kindCard,
              selected && { borderColor: option.accent, backgroundColor: option.wash },
            ]}
            onPress={() => onChange(option.kind)}
            accessibilityRole="radio"
            accessibilityState={{ selected }}
            accessibilityLabel={`${option.title} — ${option.hint}`}
            testID={`register-kind-${option.kind}`}
          >
            <Icon name={option.icon} size={22} color={selected ? option.accent : colors.surface[400]} />
            <Text style={[styles.kindTitle, selected && { color: option.accent }]}>{option.title}</Text>
            <Text style={styles.kindHint}>{option.hint}</Text>
          </TouchableOpacity>
        );
      })}
    </View>
  );
}

/**
 * Choix du métier exercé. Le référentiel vient du serveur (GET /api/trades, public) plutôt que
 * d'une liste figée dans l'app : ajouter un métier ne doit pas demander de publier une version.
 */
function TradePicker({
  value,
  onChange,
}: {
  value: number | null;
  onChange: (id: number) => void;
}) {
  const { data: trades, isLoading, isError } = useTrades();

  if (isLoading) {
    return <Text style={styles.kindPrompt}>Chargement des métiers…</Text>;
  }

  if (isError || !trades?.length) {
    return <Text style={styles.fieldError}>Impossible de charger la liste des métiers.</Text>;
  }

  return (
    <View style={styles.tradeGrid} accessibilityRole="radiogroup">
      {trades.map(trade => {
        const selected = value === trade.id;

        return (
          <TouchableOpacity
            key={trade.id}
            style={[styles.tradeChip, selected && styles.tradeChipSelected]}
            onPress={() => onChange(trade.id)}
            accessibilityRole="radio"
            accessibilityState={{ selected }}
            accessibilityLabel={trade.name}
            testID={`register-trade-${trade.id}`}
          >
            <Text style={[styles.tradeChipText, selected && styles.tradeChipTextSelected]}>
              {trade.name}
            </Text>
          </TouchableOpacity>
        );
      })}
    </View>
  );
}

/**
 * Rend les questions propres au métier choisi (trades.provider_form_schema).
 *
 * Piloté par les données : les questions réglementaires découlent des exigences du métier côté
 * serveur — un électricien se voit demander sa certification, un babysitter non — et en ajouter
 * une ne demande aucune publication de l'app.
 */
function TradeQuestions({
  fields,
  answers,
  errors,
  onChange,
}: {
  fields: ProviderField[];
  answers: Record<string, string | boolean>;
  errors: Record<string, string>;
  onChange: (key: string, value: string | boolean) => void;
}) {
  return (
    <>
      {fields.map(field => {
        if (field.type === 'boolean') {
          const checked = answers[field.key] === true;

          return (
            <TouchableOpacity
              key={field.key}
              style={styles.termsRow}
              onPress={() => onChange(field.key, !checked)}
              accessibilityRole="checkbox"
              accessibilityState={{ checked }}
              accessibilityLabel={field.label}
            >
              <View style={[styles.checkbox, checked && styles.checkboxChecked]} />
              <Text style={styles.termsText}>{field.label}</Text>
            </TouchableOpacity>
          );
        }

        return (
          <View key={field.key}>
            <TextInput
              label={field.required ? `${field.label} *` : field.label}
              value={String(answers[field.key] ?? '')}
              onChangeText={(t) => onChange(field.key, t)}
              error={errors[field.key]}
              keyboardType={field.type === 'number' ? 'number-pad' : 'default'}
              returnKeyType="next"
            />
            {field.help ? <Text style={styles.fieldHelp}>{field.help}</Text> : null}
          </View>
        );
      })}
    </>
  );
}

/**
 * Bandeau d'erreur du formulaire.
 *
 * Local plutôt que le composant partagé `ErrorState`, dont la mise en page centrée avec titre
 * « Oups ! » est prévue pour une section entière en échec, pas pour un formulaire.
 */
function FormError({ message, onRetry, testID }: { message: string; onRetry: () => void; testID: string }) {
  const reducedMotion = useReducedMotion();

  return (
    <Animated.View
      entering={reducedMotion ? undefined : FadeInDown.duration(260)}
      style={styles.formError}
      testID={testID}
      accessibilityLiveRegion="polite"
    >
      <Icon name="alert-circle-outline" size={18} color={colors.danger[600]} />
      <View style={styles.formErrorBody}>
        <Text style={styles.formErrorText}>{message}</Text>
        <TouchableOpacity onPress={onRetry} accessibilityLabel="Réessayer" accessibilityRole="button">
          <Text style={styles.formErrorRetry}>Réessayer</Text>
        </TouchableOpacity>
      </View>
    </Animated.View>
  );
}

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
  // aucune saisie (réseau coupé, service indisponible, identifiants refusés).
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
    } catch (e: unknown) {
      const fieldErrors = e instanceof ApiError ? e.errors : undefined;
      if (fieldErrors) {
        setErrors({ email: fieldErrors.email?.[0], password: fieldErrors.password?.[0] });
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
      <Stagger index={2}>
        <TouchableOpacity onPress={() => navigation.navigate('ForgotPassword')} accessibilityRole="button">
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
  // null tant que rien n'est choisi : le formulaire ne se révèle qu'après la décision.
  const [providerKind, setProviderKind] = useState<'independent' | 'company' | null>(null);
  const [companyName, setCompanyName] = useState('');
  const [vatNumber, setVatNumber] = useState('');
  const [tradeId, setTradeId] = useState<number | null>(null);
  const [tradeAnswers, setTradeAnswers] = useState<Record<string, string | boolean>>({});
  const { data: tradeFields } = useTradeProviderFields(tradeId);
  const [errors, setErrors] = useState<{
    name?: string;
    companyName?: string;
    email?: string;
    password?: string;
    confirmPassword?: string;
    acceptTerms?: string;
    tradeId?: string;
    tradeAnswers?: Record<string, string>;
  }>({});
  const [formError, setFormError] = useState<string | null>(null);
  // null = pas encore résolu ; 'skipped' = captcha non configuré (dev), on soumet sans jeton.
  const [captchaToken, setCaptchaToken] = useState<string | null>(null);
  const [captchaSkipped, setCaptchaSkipped] = useState(false);
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
    // Le serveur refuse une société sans raison sociale (required_if) : autant le dire ici.
    if (providerKind === 'company' && !companyName.trim()) e.companyName = 'Nom de la société requis';
    if (!email) e.email = 'Email requis';
    else if (!email.includes('@')) e.email = 'Email invalide';
    if (!password) e.password = 'Mot de passe requis';
    else if (password.length < 8) e.password = 'Min. 8 caractères';
    if (!confirmPassword) e.confirmPassword = 'Confirmation requise';
    else if (password !== confirmPassword) e.confirmPassword = 'Les mots de passe ne correspondent pas';
    if (!tradeId) e.tradeId = 'Choisissez votre métier';
    // Les questions marquées obligatoires par le serveur le sont aussi ici : autant le dire
    // avant l'appel plutôt que de laisser partir une requête vouée à un 422.
    (tradeFields ?? []).forEach(field => {
      if (!field.required || field.type === 'boolean') return;
      const answer = tradeAnswers[field.key];
      if (answer === undefined || String(answer).trim() === '') {
        e.tradeAnswers = { ...(e.tradeAnswers ?? {}), [field.key]: 'Réponse requise' };
      }
    });
    if (!acceptTerms) e.acceptTerms = 'Vous devez accepter les CGU';
    setErrors(e);
    return Object.keys(e).length === 0;
  };

  const handleRegister = async () => {
    setFormError(null);
    // Le bouton n'existe pas avant le choix ; garde de sûreté si le rendu changeait.
    if (!providerKind) return;
    if (!validate()) return;
    // Le serveur refuse l'inscription sans jeton quand le captcha est actif : mieux vaut le dire
    // ici que laisser partir un appel voué à un 400.
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
        // Cette app inscrit des prestataires. Sans ce champ le serveur créait un compte client,
        // que le garde `role:employe` enfermait hors de tout — onboarding compris.
        accountType: 'provider',
        providerKind,
        companyName: providerKind === 'company' ? companyName : undefined,
        vatNumber: providerKind === 'company' && vatNumber ? vatNumber : undefined,
        tradeId: tradeId ?? undefined,
        tradeAnswers: Object.keys(tradeAnswers).length ? tradeAnswers : undefined,
        captchaToken,
      });
      setUser(result.user);
    } catch (e: unknown) {
      const fieldErrors = e instanceof ApiError ? e.errors : undefined;
      if (fieldErrors) {
        setErrors({
          name: fieldErrors.name?.[0],
          companyName: fieldErrors.company_name?.[0],
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
        <KindChoice value={providerKind} onChange={setProviderKind} />
      </Stagger>

      {/* Le formulaire ne se révèle qu'une fois le type choisi : les champs demandés diffèrent
          selon le cas, et une seule décision à la fois se lit mieux qu'un mur de champs. */}
      {providerKind === null ? (
        <Text style={styles.kindPrompt}>Choisissez le type de compte pour continuer.</Text>
      ) : null}

      {providerKind !== null ? (
        <>
      {providerKind === 'company' ? (
        <>
          <Stagger index={1}>
            <TextInput
              label="Nom de la société"
              value={companyName}
              onChangeText={(t) => { setCompanyName(t); setErrors(prev => ({ ...prev, companyName: undefined })); }}
              error={errors.companyName}
              placeholder="Nettoyage Dupont SPRL"
              returnKeyType="next"
            />
          </Stagger>
          <Stagger index={1}>
            <TextInput
              label="Numéro de TVA (optionnel)"
              value={vatNumber}
              onChangeText={setVatNumber}
              autoCapitalize="characters"
              placeholder="BE0123456789"
              returnKeyType="next"
            />
          </Stagger>
        </>
      ) : null}
      <Stagger index={1}>
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
        <View style={styles.tradeBlock}>
          <Text style={styles.sectionLabel}>Votre métier</Text>
          <TradePicker value={tradeId} onChange={(id) => { setTradeId(id); setErrors(prev => ({ ...prev, tradeId: undefined })); }} />
          {errors.tradeId ? <Text style={styles.fieldError}>{errors.tradeId}</Text> : null}
        </View>
      </Stagger>

      {tradeId && (tradeFields?.length ?? 0) > 0 ? (
        <Stagger index={5}>
          <View style={styles.tradeBlock}>
            <TradeQuestions
              fields={tradeFields ?? []}
              answers={tradeAnswers}
              errors={errors.tradeAnswers ?? {}}
              onChange={(key, value) => {
                setTradeAnswers(prev => ({ ...prev, [key]: value }));
                setErrors(prev => ({ ...prev, tradeAnswers: undefined }));
              }}
            />
          </View>
        </Stagger>
      ) : null}

      <Stagger index={6}>
        <TouchableOpacity
          style={styles.termsRow}
          onPress={() => { setAcceptTerms(v => !v); setErrors(prev => ({ ...prev, acceptTerms: undefined })); }}
          accessibilityRole="checkbox"
          accessibilityState={{ checked: acceptTerms }}
          // La case portait un rôle mais aucun nom accessible : un lecteur d'écran annonçait
          // « case à cocher » sans dire de quoi, alors que cocher est obligatoire pour s'inscrire.
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
      <Stagger index={7}>
        <Button label="Créer mon compte" onPress={handleRegister} fullWidth size="lg" loading={register.isPending} />
      </Stagger>
        </>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: CANVAS },
  flex: { flex: 1 },
  halo: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    alignItems: 'center',
    justifyContent: 'center',
    // Le contenu de la page est centré verticalement : on ancre le halo sur le même repère puis
    // on le remonte, pour qu'il se place derrière le wordmark plutôt qu'au-dessus. Un ancrage
    // en haut d'écran le décalait selon la hauteur de l'appareil.
    paddingBottom: 240,
  },
  haloLayer: { position: 'absolute' },
  blob: { alignItems: 'center', justifyContent: 'center' },
  scroll: { flexGrow: 1, justifyContent: 'center', paddingHorizontal: spacing.lg, paddingVertical: spacing.xl },
  header: { alignItems: 'center', marginBottom: spacing.xl },
  wordmarkRow: { flexDirection: 'row', alignItems: 'flex-end' },
  brand: {
    fontSize: typography.fontSize['4xl'],
    fontWeight: typography.fontWeight.extraBold,
    color: colors.mode.tool.ink,
  },
  brandDot: {
    width: 9,
    height: 9,
    borderRadius: 999,
    backgroundColor: colors.accent.amberDeep,
    marginBottom: 9,
    marginLeft: 3,
  },
  // tool.muted (#64748b) sur CANVAS (#F7F8FB) : ~4,5:1, au seuil AA pour ce corps de texte.
  subtitle: { fontSize: typography.fontSize.sm, color: colors.mode.tool.muted, marginTop: spacing.sm },
  card: {
    backgroundColor: '#ffffff',
    borderRadius: radius.lg,
    padding: spacing.lg,
    borderWidth: 1,
    borderColor: colors.surface[200],
    ...shadows.md,
  },
  form: { gap: spacing.md },
  footer: { gap: spacing.xs, marginTop: spacing.lg },
  switchText: {
    textAlign: 'center',
    color: colors.brand[600],
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.medium,
    paddingVertical: spacing.md,
  },
  forgotText: { color: colors.brand[600], fontSize: typography.fontSize.sm, textAlign: 'right' },
  termsRow: { flexDirection: 'row', alignItems: 'flex-start', gap: spacing.sm },
  checkbox: { width: 20, height: 20, borderRadius: 4, borderWidth: 2, borderColor: colors.surface[400], marginTop: 2, flexShrink: 0 },
  checkboxChecked: { backgroundColor: colors.brand[500], borderColor: colors.brand[500] },
  termsText: { flex: 1, fontSize: typography.fontSize.sm, color: colors.surface[700] },
  termsLink: { color: colors.brand[600], textDecorationLine: 'underline' },
  errorText: { fontSize: typography.fontSize.xs, color: colors.danger[600] },
  kindRow: { flexDirection: 'row', gap: spacing.sm },
  kindCard: {
    flex: 1,
    gap: 2,
    paddingVertical: spacing.md,
    paddingHorizontal: spacing.sm,
    borderRadius: radius.md,
    borderWidth: 1.5,
    borderColor: colors.surface[200],
    backgroundColor: '#ffffff',
  },
  kindTitle: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: colors.mode.tool.ink,
  },
  kindHint: { fontSize: typography.fontSize.xs, color: colors.surface[600] },
  kindPrompt: {
    fontSize: typography.fontSize.sm,
    color: colors.mode.tool.muted,
    textAlign: 'center',
    paddingVertical: spacing.sm,
  },
  tradeBlock: { gap: spacing.sm },
  sectionLabel: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: colors.mode.tool.ink,
  },
  tradeGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
  tradeChip: {
    paddingVertical: spacing.xs + 2,
    paddingHorizontal: spacing.sm + 2,
    borderRadius: radius.pill,
    borderWidth: 1,
    borderColor: colors.surface[200],
    backgroundColor: '#ffffff',
  },
  tradeChipSelected: { borderColor: colors.brand[600], backgroundColor: colors.brand[50] },
  tradeChipText: { fontSize: typography.fontSize.sm, color: colors.surface[600] },
  tradeChipTextSelected: { color: colors.brand[600], fontWeight: typography.fontWeight.semibold },
  fieldHelp: { fontSize: typography.fontSize.xs, color: colors.mode.tool.muted, marginTop: 2 },
  fieldError: { fontSize: typography.fontSize.xs, color: colors.danger[600] },
  formError: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: spacing.sm,
    padding: spacing.md,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.danger[500],
    backgroundColor: colors.danger[50],
  },
  formErrorBody: { flex: 1, gap: spacing.xs },
  // danger[700] sur danger[50] : contraste largement au-dessus du seuil AA.
  formErrorText: { fontSize: typography.fontSize.sm, color: colors.danger[700] },
  formErrorRetry: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: colors.brand[600],
  },
  passwordWrapper: { position: 'relative' },
  eyeButton: { position: 'absolute', right: 12, top: 32, zIndex: 1 },
});
