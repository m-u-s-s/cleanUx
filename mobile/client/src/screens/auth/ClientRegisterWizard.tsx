import React, { useMemo, useState } from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import Animated, { FadeIn, FadeInRight, FadeOut } from 'react-native-reanimated';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Button, TextInput, Icon, TurnstileWidget, ProgressBar, useReducedMotion } from '@/ui';
import {
  FormError,
  KindChoiceCards,
  authErrorMessage,
  authStyles as kit,
} from '@/ui/authShell';
import { useRegister, useAuth, isValidBusinessNumber } from '@/auth';
import { ApiError } from '@/api';
import { colors, radius, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';
import { useTraduction } from '@/i18n';

/**
 * Inscription cliente : une question par écran, comme côté prestataire.
 *
 * Le formulaire posait ses six champs d'un bloc et ne distinguait pas les particuliers des
 * sociétés — alors que `client_company` existe côté serveur et porte le multi-sites, les
 * contrats B2B et la facturation centralisée. Une société cliente n'avait donc aucun moyen de
 * s'inscrire depuis l'application.
 *
 * La mécanique est celle du parcours prestataire : progression visible, retour toujours possible,
 * étapes sans objet retirées du parcours. Les questions, elles, sont celles d'un client — pas de
 * téléphone vérifié par SMS avant création du compte, pas de métier : un client ne reçoit pas de
 * mission, il en commande.
 */
type StepId = 'kind' | 'company' | 'identity' | 'email' | 'password' | 'terms';

const PASSWORD_MIN = 8;

const KIND_OPTIONS = [
  { kind: 'individual' as const, title: 'Particulier', hint: 'Pour mon domicile', icon: 'person-outline' },
  { kind: 'company' as const, title: 'Société', hint: 'Pour mon entreprise', icon: 'business-outline' },
];

/** L'ancien contrôle était `email.includes('@')`, que « @ » satisfait. */
function isPlausibleEmail(value: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value.trim());
}

function passwordStrength(value: string): { score: 0 | 1 | 2 | 3; label: string; color: string } {
  if (value.length < PASSWORD_MIN) return { score: 0, label: 'Trop court', color: colors.danger[600] };

  const varieties = [/[a-z]/, /[A-Z]/, /\d/, /[^a-zA-Z0-9]/].filter(r => r.test(value)).length;

  if (varieties >= 3 && value.length >= 12) return { score: 3, label: 'Excellent', color: colors.success[600] };
  if (varieties >= 2) return { score: 2, label: 'Correct', color: colors.warning[700] };

  return { score: 1, label: 'Faible', color: colors.warning[700] };
}

export function ClientRegisterWizard() {
  const { t: tr } = useTraduction();
  const jetons = useThemeColors();
  const styles = stylesFor(jetons);

  const [stepIndex, setStepIndex] = useState(0);
  const [clientKind, setClientKind] = useState<'individual' | 'company' | null>(null);
  const [companyName, setCompanyName] = useState('');
  const [vatNumber, setVatNumber] = useState('');
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [acceptTerms, setAcceptTerms] = useState(false);
  const [fieldError, setFieldError] = useState<string | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const [captchaToken, setCaptchaToken] = useState<string | null>(null);
  const [captchaSkipped, setCaptchaSkipped] = useState(false);

  const register = useRegister();
  const { setUser } = useAuth();
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const reducedMotion = useReducedMotion();

  /** L'étape société sort du parcours pour un particulier : la barre compte ce qui reste. */
  const steps = useMemo<StepId[]>(() => {
    const list: StepId[] = ['kind'];
    if (clientKind === 'company') list.push('company');
    list.push('identity', 'email', 'password', 'terms');

    return list;
  }, [clientKind]);

  const step = steps[Math.min(stepIndex, steps.length - 1)]!;
  const isLast = step === 'terms';

  function validateStep(): string | null {
    switch (step) {
      case 'kind':
        return clientKind ? null : 'Choisissez le type de compte.';
      case 'company':
        if (!companyName.trim()) return 'La raison sociale est requise.';
        if (vatNumber.trim() && !isValidBusinessNumber(vatNumber)) {
          return "Numéro d'entreprise invalide. Exemples : BE0202239951, 44306184100047.";
        }
        return null;
      case 'identity':
        return name.trim() ? null : 'Votre nom est requis.';
      case 'email':
        return isPlausibleEmail(email) ? null : 'Adresse email invalide.';
      case 'password':
        return password.length >= PASSWORD_MIN
          ? null
          : `Le mot de passe doit compter au moins ${PASSWORD_MIN} caractères.`;
      case 'terms':
        return acceptTerms ? null : 'Vous devez accepter les conditions pour continuer.';
      default:
        return null;
    }
  }

  const goNext = () => {
    const error = validateStep();
    if (error) { setFieldError(error); return; }

    setFieldError(null);
    if (isLast) { void submit(); return; }
    setStepIndex(i => Math.min(i + 1, steps.length - 1));
  };

  const goBack = () => {
    setFieldError(null);
    setFormError(null);
    if (stepIndex === 0) { navigation.goBack(); return; }
    setStepIndex(i => i - 1);
  };

  const submit = async () => {
    setFormError(null);

    // Le serveur refuse l'inscription sans jeton quand le captcha est actif : mieux vaut le dire
    // ici que laisser partir un appel voué à un 400.
    if (!captchaSkipped && !captchaToken) {
      setFormError('Veuillez patienter, la vérification anti-robot est en cours.');

      return;
    }

    try {
      const result = await register.mutateAsync({
        name: name.trim(),
        email: email.trim(),
        password,
        passwordConfirmation: password,
        phone: phone || undefined,
        acceptTerms: true,
        clientKind: clientKind ?? 'individual',
        companyName: clientKind === 'company' ? companyName : undefined,
        vatNumber: clientKind === 'company' && vatNumber ? vatNumber : undefined,
        captchaToken,
      });
      setUser(result.user);
    } catch (e: unknown) {
      const fieldErrors = e instanceof ApiError ? e.errors : undefined;
      const firstFieldError = fieldErrors
        ? Object.values(fieldErrors).flat().filter(Boolean)[0]
        : undefined;

      // Le wizard n'affiche qu'un champ à la fois : une erreur portant sur un autre écran doit
      // rester lisible ici plutôt que d'être rattachée à un champ absent.
      setFormError(firstFieldError ?? authErrorMessage(e, 'register'));
    }
  };

  return (
    <View style={styles.wrapper}>
      <View style={styles.progressRow}>
        <TouchableOpacity
          onPress={goBack}
          style={styles.backButton}
          accessibilityRole="button"
          accessibilityLabel={tr('client_register_wizard.etape_precedente')}
          testID="client-register-back"
        >
          <Icon name="arrow-back" size={20} color={colors.mode.tool.ink} />
        </TouchableOpacity>
        <View style={styles.progressBarBox}>
          <ProgressBar step={stepIndex + 1} totalSteps={steps.length} />
        </View>
      </View>

      <Animated.View
        // La clé force le remontage : sans elle, React réutiliserait le champ précédent et la
        // transition ne se jouerait pas.
        key={step}
        entering={reducedMotion ? undefined : FadeInRight.duration(240)}
        exiting={reducedMotion ? undefined : FadeOut.duration(120)}
        style={styles.stepBody}
      >
        {renderStep()}
      </Animated.View>

      {fieldError ? (
        <Animated.Text
          entering={reducedMotion ? undefined : FadeIn.duration(180)}
          style={styles.fieldError}
          accessibilityLiveRegion="polite"
          testID="client-register-step-error"
        >
          {fieldError}
        </Animated.Text>
      ) : null}

      {formError ? (
        <FormError message={formError} onRetry={goNext} testID="client-register-form-error" />
      ) : null}

      {isLast ? (
        <TurnstileWidget
          onToken={setCaptchaToken}
          onSkipped={() => setCaptchaSkipped(true)}
          testID="register-captcha"
        />
      ) : null}

      <Button
        label={isLast ? 'Créer mon compte' : 'Continuer'}
        onPress={goNext}
        fullWidth
        size="lg"
        loading={register.isPending}
      />
    </View>
  );

  function renderStep(): React.ReactNode {
    switch (step) {
      case 'kind':
        return (
          <Question
            title={tr('client_register_wizard.vous_reservez_pour_qui')}
            hint="Ce choix détermine votre facturation et vos options de gestion."
          >
            <KindChoiceCards
              options={KIND_OPTIONS}
              value={clientKind}
              onChange={kind => { setClientKind(kind); setFieldError(null); }}
              testIdPrefix="client-register-kind"
            />
          </Question>
        );

      case 'company':
        return (
          <Question
            title={tr('client_register_wizard.votre_societe')}
            hint="Elle pourra gérer plusieurs sites et recevoir une facturation centralisée."
          >
            <TextInput
              label={tr('client_register_wizard.raison_sociale')}
              value={companyName}
              onChangeText={t => { setCompanyName(t); setFieldError(null); }}
              placeholder={tr('client_register_wizard.bureau_dupont_sprl')}
              autoFocus
              testID="client-register-company-name"
            />
            <TextInput
              label="Numéro d'entreprise (optionnel)"
              value={vatNumber}
              onChangeText={t => { setVatNumber(t); setFieldError(null); }}
              autoCapitalize="characters"
              placeholder="BE0202239951"
              testID="client-register-vat-number"
            />
          </Question>
        );

      case 'identity':
        return (
          <Question
            title={clientKind === 'company' ? tr('client_register_wizard.qui_vous_represente') : tr('client_register_wizard.comment_vous_appelez_vous')}
            hint="Ce nom apparaîtra sur vos réservations."
          >
            <TextInput
              label={tr('client_register_wizard.nom_complet')}
              value={name}
              onChangeText={t => { setName(t); setFieldError(null); }}
              autoComplete="name"
              placeholder={tr('client_register_wizard.jean_dupont')}
              autoFocus
              testID="client-register-name"
            />
            <TextInput
              label={tr('client_register_wizard.telephone_optionnel')}
              value={phone}
              onChangeText={setPhone}
              keyboardType="phone-pad"
              placeholder="+32 470 12 34 56"
              testID="client-register-phone"
            />
          </Question>
        );

      case 'email':
        return (
          <Question title={tr('client_register_wizard.votre_adresse_email')} hint="Elle sert à vous connecter et à recevoir vos factures.">
            <TextInput
              label={tr('client_register_wizard.email')}
              value={email}
              onChangeText={t => { setEmail(t); setFieldError(null); }}
              keyboardType="email-address"
              autoCapitalize="none"
              autoComplete="email"
              placeholder="votre@email.com"
              autoFocus
              testID="client-register-email"
            />
          </Question>
        );

      case 'password': {
        const strength = passwordStrength(password);

        return (
          <Question title={tr('client_register_wizard.choisissez_un_mot_de_passe')} hint={`${PASSWORD_MIN} caractères minimum.`}>
            <View style={kit.passwordWrapper}>
              <TextInput
                label={tr('client_register_wizard.mot_de_passe')}
                value={password}
                onChangeText={t => { setPassword(t); setFieldError(null); }}
                secureTextEntry={!showPassword}
                placeholder="••••••••"
                autoFocus
                testID="client-register-password"
              />
              <TouchableOpacity
                onPress={() => setShowPassword(v => !v)}
                style={kit.eyeButton}
                accessibilityRole="button"
                accessibilityLabel={showPassword ? tr('client_register_wizard.masquer_le_mot_de_passe') : tr('client_register_wizard.afficher_le_mot_de_passe')}
              >
                <Icon
                  name={showPassword ? 'eye-off-outline' : 'eye-outline'}
                  size={20}
                  color={colors.surface[400]}
                />
              </TouchableOpacity>
            </View>
            {password.length > 0 ? (
              <View style={styles.strengthRow}>
                <View style={styles.strengthTrack}>
                  <View
                    style={[
                      styles.strengthFill,
                      { width: `${(strength.score / 3) * 100}%`, backgroundColor: strength.color },
                    ]}
                  />
                </View>
                <Text style={[styles.strengthLabel, { color: strength.color }]}>{strength.label}</Text>
              </View>
            ) : null}
          </Question>
        );
      }

      case 'terms':
        return (
          <Question title={tr('client_register_wizard.derniere_etape')} hint="Vous pourrez réserver un service juste après.">
            <TouchableOpacity
              style={kit.termsRow}
              onPress={() => { setAcceptTerms(v => !v); setFieldError(null); }}
              accessibilityRole="checkbox"
              accessibilityState={{ checked: acceptTerms }}
              accessibilityLabel="J'accepte les conditions d'utilisation et la politique de confidentialité"
              testID="client-register-accept-terms"
            >
              <View style={[kit.checkbox, acceptTerms && kit.checkboxChecked]} />
              <Text style={kit.termsText}>
                J'accepte les{' '}
                <Text style={kit.termsLink} onPress={() => navigation.navigate('Legal', { type: 'terms' })}>
                  {tr('client_register_wizard.conditions_d_utilisation')}
                </Text>
                {' '}et la{' '}
                <Text style={kit.termsLink} onPress={() => navigation.navigate('Legal', { type: 'privacy' })}>
                  {tr('client_register_wizard.politique_de_confidentialite')}
                </Text>
              </Text>
            </TouchableOpacity>
          </Question>
        );

      default:
        return null;
    }
  }
}

function Question({
  title,
  hint,
  children,
}: {
  title: string;
  hint?: string;
  children: React.ReactNode;
}) {
  const styles = stylesFor(useThemeColors());

  return (
    <View style={styles.question}>
      {/* `header` plutôt que `text` : le lecteur d'écran annonce la question comme le titre de
          l'écran, et non comme un paragraphe parmi d'autres. */}
      <Text style={styles.questionTitle} accessibilityRole="header">{title}</Text>
      {hint ? <Text style={styles.questionHint}>{hint}</Text> : null}
      <View style={styles.questionFields}>{children}</View>
    </View>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  wrapper: { gap: spacing.md },
  progressRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  // 44 pt : cible tactile minimale, d'autant que le bouton est en coin d'écran.
  backButton: { width: 44, height: 44, alignItems: 'center', justifyContent: 'center', marginLeft: -spacing.sm },
  progressBarBox: { flex: 1 },
  stepBody: { minHeight: 210 },
  question: { gap: spacing.xs },
  questionTitle: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: colors.mode.tool.ink,
  },
  questionHint: { fontSize: typography.fontSize.sm, color: colors.mode.tool.muted },
  questionFields: { gap: spacing.md, marginTop: spacing.md },
  fieldError: { fontSize: typography.fontSize.sm, color: t.danger },
  strengthRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  strengthTrack: {
    flex: 1,
    height: 4,
    borderRadius: radius.pill,
    backgroundColor: t.border,
    overflow: 'hidden',
  },
  strengthFill: { height: 4, borderRadius: radius.pill },
  strengthLabel: { fontSize: typography.fontSize.xs, fontWeight: typography.fontWeight.medium },
});
