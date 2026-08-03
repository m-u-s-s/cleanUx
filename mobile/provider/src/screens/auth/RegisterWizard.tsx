import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import Animated, { FadeIn, FadeInRight, FadeOut } from 'react-native-reanimated';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Button, TextInput, Icon, TurnstileWidget, ProgressBar, useReducedMotion } from '@/ui';
import {
  useRegister,
  useAuth,
  useRequestPhoneCode,
  useConfirmPhoneCode,
  toE164,
  isPlausibleE164,
  isValidBusinessNumber,
  useCompanyLookup,
} from '@/auth';
import { useTradeProviderFields } from '@/trades';
import { ApiError } from '@/api';
import { colors, radius, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';
import {
  authErrorMessage,
  FormError,
  KindChoice,
  TradePicker,
  TradeQuestions,
  stylesFor as kitStylesFor,
} from './kit';
import { clearDraft, emptyDraft, loadDraft, saveDraft, type RegisterDraft } from './draft';

/**
 * Inscription prestataire : une question par écran.
 *
 * Le formulaire précédent posait quinze champs d'un bloc — nom, société, TVA, email, téléphone,
 * mot de passe, confirmation, métier, questions du métier, CGU — sur une seule page défilante.
 * C'est le mur que tous les parcours d'inscription mobiles ont abandonné : on ne répond bien qu'à
 * une question à la fois, et un abandon au quinzième champ perd les quatorze premiers.
 *
 * Trois principes, repris d'Uber et de Heetch :
 *
 *  - **Le téléphone d'abord**, vérifié par SMS avant tout le reste : c'est l'identifiant
 *    opérationnel du prestataire, celui par lequel un client le joindra.
 *  - **Rien ne se perd** : chaque réponse est écrite localement dès sa saisie (voir draft.ts).
 *  - **Aucun écran ne bloque** : la progression est visible en permanence, le retour est toujours
 *    possible, et les étapes sans objet — la société pour un indépendant, les questions d'un
 *    métier qui n'en pose pas — disparaissent du parcours au lieu d'être affichées vides.
 */

type StepId =
  | 'phone'
  | 'otp'
  | 'identity'
  | 'email'
  | 'password'
  | 'kind'
  | 'company'
  | 'trade'
  | 'tradeQuestions'
  | 'terms';

const OTP_LENGTH = 6;

/** Le serveur exige 8 caractères ; en deçà l'inscription est refusée. */
const PASSWORD_MIN = 8;

/**
 * Validation d'email réellement utile : l'ancien contrôle était `email.includes('@')`, que
 * « @ » satisfait. On reste volontairement permissif sur le domaine — le serveur tranche, et un
 * client trop strict rejette des adresses valides.
 */
function isPlausibleEmail(value: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value.trim());
}

/** Trois niveaux suffisent à guider sans prétendre mesurer une entropie réelle. */
function passwordStrength(value: string): { score: 0 | 1 | 2 | 3; label: string; color: string } {
  if (value.length < PASSWORD_MIN) return { score: 0, label: 'Trop court', color: colors.danger[600] };

  const varieties = [/[a-z]/, /[A-Z]/, /\d/, /[^a-zA-Z0-9]/].filter(r => r.test(value)).length;

  if (varieties >= 3 && value.length >= 12) return { score: 3, label: 'Excellent', color: colors.success[600] };
  if (varieties >= 2) return { score: 2, label: 'Correct', color: colors.warning[700] };

  return { score: 1, label: 'Faible', color: colors.warning[700] };
}

export function RegisterWizard() {
  const kit = kitStylesFor(useThemeColors());
  const styles = stylesFor(useThemeColors());

  const [draft, setDraft] = useState<RegisterDraft>(emptyDraft);
  const [restored, setRestored] = useState(false);
  const [stepIndex, setStepIndex] = useState(0);
  // Ni le mot de passe ni le jeton du téléphone ne sont persistés : ils vivent hors du brouillon.
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [phoneToken, setPhoneToken] = useState<string | null>(null);
  const [otp, setOtp] = useState('');
  const [fieldError, setFieldError] = useState<string | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const [captchaToken, setCaptchaToken] = useState<string | null>(null);
  const [captchaSkipped, setCaptchaSkipped] = useState(false);

  const { data: tradeFields } = useTradeProviderFields(draft.tradeId);
  const lookup = useCompanyLookup();
  const requestCode = useRequestPhoneCode();
  const confirmCode = useConfirmPhoneCode();
  const register = useRegister();
  const { setUser } = useAuth();
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const reducedMotion = useReducedMotion();

  useEffect(() => {
    let cancelled = false;

    loadDraft().then(saved => {
      if (cancelled) return;
      if (saved) setDraft(saved);
      setRestored(true);
    });

    return () => { cancelled = true; };
  }, []);

  // Écriture après restauration seulement : sauvegarder avant écraserait le brouillon repris
  // par le brouillon vide de l'état initial.
  useEffect(() => {
    if (restored) void saveDraft(draft);
  }, [draft, restored]);

  const patch = useCallback((changes: Partial<RegisterDraft>) => {
    setFieldError(null);
    setDraft(prev => ({ ...prev, ...changes }));
  }, []);

  /**
   * Les étapes sans objet sortent du parcours : la barre de progression compte alors ce que
   * l'utilisateur aura réellement à remplir, et non un maximum théorique jamais atteint.
   */
  const steps = useMemo<StepId[]>(() => {
    const list: StepId[] = ['phone', 'otp', 'identity', 'email', 'password', 'kind'];

    if (draft.providerKind === 'company') list.push('company');
    list.push('trade');
    if (draft.tradeId && (tradeFields?.length ?? 0) > 0) list.push('tradeQuestions');
    list.push('terms');

    return list;
  }, [draft.providerKind, draft.tradeId, tradeFields]);

  const step = steps[Math.min(stepIndex, steps.length - 1)]!;
  const isLast = step === 'terms';

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

  function validateStep(): string | null {
    switch (step) {
      case 'phone':
        return isPlausibleE164(toE164(draft.phone))
          ? null
          : 'Numéro invalide. Exemple : 0470 12 34 56';
      case 'otp':
        return otp.length === OTP_LENGTH ? null : `Le code compte ${OTP_LENGTH} chiffres.`;
      case 'identity':
        if (!draft.firstName.trim()) return 'Votre prénom est requis.';
        return draft.lastName.trim() ? null : 'Votre nom est requis.';
      case 'email':
        return isPlausibleEmail(draft.email) ? null : 'Adresse email invalide.';
      case 'password':
        return password.length >= PASSWORD_MIN
          ? null
          : `Le mot de passe doit compter au moins ${PASSWORD_MIN} caractères.`;
      case 'kind':
        return draft.providerKind ? null : 'Choisissez le type de compte.';
      case 'company':
        if (!draft.companyName.trim()) return 'La raison sociale est requise.';
        // Facultatif, mais s'il est saisi il doit être juste : c'est ce numéro que la
        // vérification d'entreprise soumettra aux registres officiels.
        if (draft.vatNumber.trim() && !isValidBusinessNumber(draft.vatNumber)) {
          return "Numéro d'entreprise invalide. Exemples : BE0202239951, 44306184100047.";
        }
        return null;
      case 'trade':
        return draft.tradeId ? null : 'Choisissez votre métier.';
      case 'tradeQuestions': {
        const missing = (tradeFields ?? []).find(field => {
          if (!field.required || field.type === 'boolean') return false;
          const answer = draft.tradeAnswers[field.key];
          return answer === undefined || String(answer).trim() === '';
        });
        return missing ? `« ${missing.label} » est requis.` : null;
      }
      case 'terms':
        return draft.acceptTerms ? null : 'Vous devez accepter les conditions pour continuer.';
      default:
        return null;
    }
  }

  /**
   * Le pré-remplissage est une SUGGESTION : la raison sociale trouvée n'écrase jamais une saisie
   * déjà faite. Un prestataire ayant corrigé son nom commercial ne doit pas le voir remplacé.
   */
  const runLookup = async () => {
    setFormError(null);
    try {
      const found = await lookup.mutateAsync({ number: draft.vatNumber });
      if (found?.legal_name && !draft.companyName.trim()) {
        patch({ companyName: found.legal_name });
      }
    } catch {
      // Un registre injoignable ne bloque rien : la saisie manuelle reste ouverte.
      setFormError('Recherche impossible pour le moment. Saisissez votre raison sociale.');
    }
  };

  const sendCode = async () => {
    setFormError(null);
    const phone = toE164(draft.phone);

    if (!isPlausibleE164(phone)) {
      setFieldError('Numéro invalide. Exemple : 0470 12 34 56');
      return;
    }

    try {
      await requestCode.mutateAsync({ phone });
      patch({ phone });
      setStepIndex(i => i + 1);
    } catch (e: unknown) {
      setFormError(
        e instanceof ApiError && e.errors?.phone?.[0]
          ? e.errors.phone[0]
          : authErrorMessage(e, 'register'),
      );
    }
  };

  const verifyCode = async () => {
    setFormError(null);

    if (otp.length !== OTP_LENGTH) {
      setFieldError(`Le code compte ${OTP_LENGTH} chiffres.`);
      return;
    }

    try {
      const { token } = await confirmCode.mutateAsync({ phone: draft.phone, code: otp });
      setPhoneToken(token);
      patch({ phoneVerified: true });
      setStepIndex(i => i + 1);
    } catch (e: unknown) {
      setFormError(
        e instanceof ApiError && e.errors?.code?.[0]
          ? e.errors.code[0]
          : authErrorMessage(e, 'register'),
      );
    }
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
        name: `${draft.firstName.trim()} ${draft.lastName.trim()}`.trim(),
        email: draft.email.trim(),
        password,
        passwordConfirmation: password,
        phone: draft.phone || undefined,
        phoneVerificationToken: phoneToken ?? undefined,
        acceptTerms: true,
        // Cette app inscrit des prestataires. Sans ce champ le serveur créait un compte client,
        // que le garde `role:employe` enfermait hors de tout — onboarding compris.
        accountType: 'provider',
        providerKind: draft.providerKind ?? 'independent',
        companyName: draft.providerKind === 'company' ? draft.companyName : undefined,
        vatNumber:
          draft.providerKind === 'company' && draft.vatNumber ? draft.vatNumber : undefined,
        tradeId: draft.tradeId ?? undefined,
        tradeAnswers: Object.keys(draft.tradeAnswers).length ? draft.tradeAnswers : undefined,
        captchaToken,
      });

      // Le compte existe : le brouillon n'a plus de raison d'être, et le laisser ferait reprendre
      // l'inscription suivante sur les réponses de celle-ci.
      await clearDraft();
      setUser(result.user);
    } catch (e: unknown) {
      const fieldErrors = e instanceof ApiError ? e.errors : undefined;
      const firstFieldError = fieldErrors
        ? Object.values(fieldErrors).flat().filter(Boolean)[0]
        : undefined;

      // Le wizard n'affiche qu'un champ à la fois : une erreur serveur portant sur un autre
      // écran doit être lisible ici plutôt que silencieusement rattachée à un champ absent.
      setFormError(firstFieldError ?? authErrorMessage(e, 'register'));
    }
  };

  const pending =
    requestCode.isPending || confirmCode.isPending || register.isPending;

  return (
    <View style={styles.wrapper}>
      <View style={styles.progressRow}>
        <TouchableOpacity
          onPress={goBack}
          style={styles.backButton}
          accessibilityRole="button"
          accessibilityLabel="Étape précédente"
          testID="register-back"
        >
          <Icon name="arrow-back" size={20} color={colors.mode.tool.ink} />
        </TouchableOpacity>
        {/* ProgressBar annonce déjà « Étape X sur Y » sous la barre : pas de second compteur. */}
        <View style={styles.progressBarBox}>
          <ProgressBar step={stepIndex + 1} totalSteps={steps.length} />
        </View>
      </View>

      <Animated.View
        // La clé force le remontage à chaque étape : sans elle, React réutiliserait le champ de
        // l'écran précédent et la transition ne se jouerait pas.
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
          testID="register-step-error"
        >
          {fieldError}
        </Animated.Text>
      ) : null}

      {formError ? (
        <FormError message={formError} onRetry={goNext} testID="register-form-error" />
      ) : null}

      {isLast ? (
        <TurnstileWidget
          onToken={setCaptchaToken}
          onSkipped={() => setCaptchaSkipped(true)}
          testID="register-captcha"
        />
      ) : null}

      <Button
        label={primaryLabel()}
        onPress={primaryAction()}
        fullWidth
        size="lg"
        loading={pending}
      />
    </View>
  );

  function primaryLabel(): string {
    if (step === 'phone') return 'Recevoir le code';
    if (step === 'otp') return 'Vérifier';
    if (isLast) return 'Créer mon compte';

    return 'Continuer';
  }

  function primaryAction(): () => void {
    if (step === 'phone') return () => void sendCode();
    if (step === 'otp') return () => void verifyCode();

    return goNext;
  }

  function renderStep(): React.ReactNode {
    switch (step) {
      case 'phone':
        return (
          <Question
            title="Votre numéro de téléphone"
            hint="Nous vous envoyons un code par SMS. C'est par ce numéro que vos clients vous joindront."
          >
            <TextInput
              label="Téléphone"
              value={draft.phone}
              onChangeText={t => patch({ phone: t })}
              keyboardType="phone-pad"
              autoComplete="tel"
              placeholder="0470 12 34 56"
              autoFocus
              testID="register-phone"
            />
          </Question>
        );

      case 'otp':
        return (
          <Question
            title="Entrez le code reçu"
            hint={`Code à ${OTP_LENGTH} chiffres envoyé au ${draft.phone}.`}
          >
            <TextInput
              label="Code de vérification"
              value={otp}
              onChangeText={t => { setOtp(t.replace(/\D/g, '').slice(0, OTP_LENGTH)); setFieldError(null); }}
              keyboardType="number-pad"
              autoComplete="sms-otp"
              placeholder="123456"
              autoFocus
              testID="register-otp"
            />
            <TouchableOpacity
              onPress={() => void requestCode.mutateAsync({ phone: draft.phone }).catch(() => undefined)}
              accessibilityRole="button"
              testID="register-otp-resend"
            >
              <Text style={styles.link}>Renvoyer le code</Text>
            </TouchableOpacity>
          </Question>
        );

      case 'identity':
        return (
          <Question title="Comment vous appelez-vous ?" hint="Ce nom sera visible par vos clients.">
            <TextInput
              label="Prénom"
              value={draft.firstName}
              onChangeText={t => patch({ firstName: t })}
              autoComplete="given-name"
              placeholder="Jean"
              autoFocus
              testID="register-first-name"
            />
            <TextInput
              label="Nom"
              value={draft.lastName}
              onChangeText={t => patch({ lastName: t })}
              autoComplete="family-name"
              placeholder="Dupont"
              testID="register-last-name"
            />
          </Question>
        );

      case 'email':
        return (
          <Question title="Votre adresse email" hint="Elle sert à vous connecter et à recevoir vos documents.">
            <TextInput
              label="Email"
              value={draft.email}
              onChangeText={t => patch({ email: t })}
              keyboardType="email-address"
              autoCapitalize="none"
              autoComplete="email"
              placeholder="votre@email.com"
              autoFocus
              testID="register-email"
            />
          </Question>
        );

      case 'password': {
        const strength = passwordStrength(password);

        return (
          <Question title="Choisissez un mot de passe" hint={`${PASSWORD_MIN} caractères minimum.`}>
            <View style={kit.passwordWrapper}>
              <TextInput
                label="Mot de passe"
                value={password}
                onChangeText={t => { setPassword(t); setFieldError(null); }}
                secureTextEntry={!showPassword}
                placeholder="••••••••"
                autoFocus
                testID="register-password"
              />
              <TouchableOpacity
                onPress={() => setShowPassword(v => !v)}
                style={kit.eyeButton}
                accessibilityRole="button"
                accessibilityLabel={showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
                testID="register-password-toggle"
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

      case 'kind':
        return (
          <Question
            title="Vous travaillez seul ou en équipe ?"
            hint="Ce choix détermine les documents qui vous seront demandés."
          >
            <KindChoice value={draft.providerKind} onChange={kind => patch({ providerKind: kind })} />
          </Question>
        );

      case 'company':
        return (
          <Question
            title="Votre société"
            hint="Saisissez votre numéro d'entreprise : nous retrouvons votre raison sociale."
          >
            <TextInput
              label="Numéro d'entreprise"
              value={draft.vatNumber}
              onChangeText={t => { patch({ vatNumber: t }); lookup.reset(); }}
              autoCapitalize="characters"
              placeholder="BE0202239951"
              autoFocus
              testID="register-vat-number"
            />

            {/* La recherche ne part qu'une fois la clé du numéro vérifiée localement : inutile
                d'interroger un registre officiel avec un numéro qui ne peut pas exister. */}
            {isValidBusinessNumber(draft.vatNumber) && !lookup.data ? (
              <Button
                label="Retrouver ma société"
                onPress={() => void runLookup()}
                variant="secondary"
                fullWidth
                loading={lookup.isPending}
              />
            ) : null}

            {lookup.data?.legal_name ? (
              <View style={styles.suggestion} testID="register-company-suggestion">
                <Text style={styles.suggestionName}>{lookup.data.legal_name}</Text>
                {lookup.data.address ? (
                  <Text style={styles.suggestionAddress}>{lookup.data.address}</Text>
                ) : null}
                <Text style={styles.suggestionSource}>Trouvée au registre officiel</Text>
              </View>
            ) : null}

            {lookup.isSuccess && lookup.data === null ? (
              <Text style={styles.lookupMiss} testID="register-company-not-found">
                Société introuvable au registre. Saisissez sa raison sociale ci-dessous.
              </Text>
            ) : null}

            <TextInput
              label="Raison sociale"
              value={draft.companyName}
              onChangeText={t => patch({ companyName: t })}
              placeholder="Nettoyage Dupont SPRL"
              testID="register-company-name"
            />
          </Question>
        );

      case 'trade':
        return (
          <Question
            title="Quel métier exercez-vous ?"
            hint="Sans métier déclaré, aucune mission ne peut vous être proposée."
          >
            <TradePicker value={draft.tradeId} onChange={id => patch({ tradeId: id })} />
          </Question>
        );

      case 'tradeQuestions':
        return (
          <Question title="Quelques précisions sur votre métier" hint="Elles servent à vous proposer les bonnes missions.">
            <TradeQuestions
              fields={tradeFields ?? []}
              answers={draft.tradeAnswers}
              errors={{}}
              onChange={(key, value) =>
                patch({ tradeAnswers: { ...draft.tradeAnswers, [key]: value } })
              }
            />
          </Question>
        );

      case 'terms':
        return (
          <Question title="Dernière étape" hint="Votre dossier de vérification s'ouvrira juste après.">
            <TouchableOpacity
              style={kit.termsRow}
              onPress={() => patch({ acceptTerms: !draft.acceptTerms })}
              accessibilityRole="checkbox"
              accessibilityState={{ checked: draft.acceptTerms }}
              accessibilityLabel="J'accepte les conditions d'utilisation et la politique de confidentialité"
              testID="register-accept-terms"
            >
              <View style={[kit.checkbox, draft.acceptTerms && kit.checkboxChecked]} />
              <Text style={kit.termsText}>
                J'accepte les{' '}
                <Text style={kit.termsLink} onPress={() => navigation.navigate('Legal', { type: 'terms' })}>
                  Conditions d'utilisation
                </Text>
                {' '}et la{' '}
                <Text style={kit.termsLink} onPress={() => navigation.navigate('Legal', { type: 'privacy' })}>
                  Politique de confidentialité
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
      {/* `header` plutôt que `text` : le lecteur d'écran annonce alors la question comme le
          titre de l'écran, et non comme un paragraphe parmi d'autres. */}
      <Text style={styles.questionTitle} accessibilityRole="header">{title}</Text>
      {hint ? <Text style={styles.questionHint}>{hint}</Text> : null}
      <View style={styles.questionFields}>{children}</View>
    </View>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  wrapper: { gap: spacing.md },
  progressRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  // 44 pt : taille de cible minimale recommandée, sous laquelle le bouton devient difficile à
  // atteindre — d'autant qu'il est en coin d'écran.
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
  fieldError: { fontSize: typography.fontSize.sm, color: colors.danger[600] },
  link: {
    fontSize: typography.fontSize.sm,
    color: colors.brand[600],
    fontWeight: typography.fontWeight.medium,
    paddingVertical: spacing.xs,
  },
  strengthRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  strengthTrack: {
    flex: 1,
    height: 4,
    borderRadius: radius.pill,
    backgroundColor: t.border,
    overflow: 'hidden',
  },
  strengthFill: { height: 4, borderRadius: radius.pill },
  suggestion: {
    gap: 2,
    padding: spacing.md,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.success[600],
    backgroundColor: colors.success[50],
  },
  suggestionName: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: colors.mode.tool.ink,
  },
  suggestionAddress: { fontSize: typography.fontSize.sm, color: t.text },
  // success[700] sur success[50] : 5,26:1, au-dessus du seuil AA pour ce corps de texte.
  suggestionSource: { fontSize: typography.fontSize.xs, color: colors.success[700] },
  lookupMiss: { fontSize: typography.fontSize.sm, color: colors.mode.tool.muted },
  strengthLabel: { fontSize: typography.fontSize.xs, fontWeight: typography.fontWeight.medium },
});
