/**
 * Habillage commun aux écrans d'authentification des deux applications.
 *
 * L'application prestataire avait reçu une refonte — fond clair, wordmark « brio », halo animé,
 * entrées en scène décalées, bandeau d'erreur avec relance — que l'application cliente ignorait :
 * elle affichait encore « Brio » en texte brut sur fond neutre. Deux portes d'entrée pour un
 * même produit, avec deux identités.
 *
 * Ces briques vivaient dans le dossier de l'application prestataire. Les remonter ici évite d'en
 * maintenir deux copies qui divergeraient à la première retouche. Ce qui reste propre au
 * prestataire — choix indépendant/société, métiers — demeure chez lui.
 */
import React from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import Animated, {
  FadeIn,
  FadeInDown,
  Easing,
  useAnimatedStyle,
  useSharedValue,
  withDelay,
  withRepeat,
  withTiming,
} from 'react-native-reanimated';
import { Icon } from './Icon';
import { useReducedMotion } from './a11y';
import { colors, radius, shadows, spacing, typography } from '../theme';
import { ApiError } from '../api';

/**
 * Fond clair légèrement bleuté : le kit partagé (TextInput, Button, Divider) est entièrement
 * conçu pour une surface claire — champs en surface[50], texte en surface[900]. L'ancien fond
 * nuit était l'élément dissonant, il obligeait chaque composant à lutter contre sa propre palette.
 */
export const CANVAS = '#F7F8FB';

/** Cadence des entrées en scène : chaque élément décale son apparition sur cette base. */
const STAGGER = 70;

/** Cadence des entrées en scène : chaque élément décale son apparition sur cette base. */

/**

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

export function authErrorMessage(error: unknown, action: 'login' | 'register'): string {
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

  /*
   * 403 — le serveur refuse ce compte DANS CETTE APPLICATION, et dit vers laquelle aller.
   *
   * Son message passe intact : c'est lui qui évite l'appel au support. Traduit en « Connexion
   * impossible pour le moment. », il ferait retenter, douter de son mot de passe, puis croire son
   * compte cassé — alors que la seule chose à faire est d'ouvrir l'autre application.
   *
   * Les 401 restent volontairement vagues juste au-dessus : on ne dit pas si l'adresse existe.
   * Ici il n'y a rien à protéger — le mot de passe était bon.
   */
  if (error.status === 403 && error.message.trim() !== '') {
    return error.message;
  }

  return fallback;
}

/**
 * Halo diffus derrière le wordmark.
 *
 * Approximation d'un dégradé radial sans dépendance supplémentaire : `expo-linear-gradient`

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
export function AnimatedHalo() {
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
export function Wordmark() {
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
export function Stagger({ index, children }: { index: number; children: React.ReactNode }) {
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

 * Bandeau d'erreur du formulaire.
 *
 * Local plutôt que le composant partagé `ErrorState`, dont la mise en page centrée avec titre
 * « Oups ! » est prévue pour une section entière en échec, pas pour un formulaire.
 */
export function FormError({ message, onRetry, testID }: { message: string; onRetry: () => void; testID: string }) {
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

const styles = StyleSheet.create({
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
});

/**
 * Feuille de style partagée par les deux écrans d'authentification : carte, en-tête, formulaire,
 * pied. Exposée pour que chaque application compose sa page sans redéfinir la même chose.
 */
export const authStyles = StyleSheet.create({
  container: { flex: 1, backgroundColor: CANVAS },
  flex: { flex: 1 },
  scroll: { flexGrow: 1, justifyContent: 'center', paddingHorizontal: spacing.lg, paddingVertical: spacing.xl },
  header: { alignItems: 'center', marginBottom: spacing.xl },
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
  passwordWrapper: { position: 'relative' },
  eyeButton: { position: 'absolute', right: 12, top: 32, zIndex: 1 },
});

/**
 * Choix du type de compte : deux cartes plutôt qu'une liste déroulante.
 *
 * C'est la première décision du parcours, elle change la suite du formulaire, et elle doit se
 * lire d'un coup d'œil. Générique parce que les deux applications posent la même question sur des
 * vocabulaires différents — indépendant/société côté prestataire, particulier/société côté client.
 *
 * Les teintes sont choisies sur leur contraste mesuré, pas à l'œil : accent.amber (1,74:1) et
 * accent.amberDeep (2,35:1) échouent au seuil de 3:1 exigé d'un élément d'interface sur fond
 * clair — ils seraient quasi invisibles comme indicateur de sélection. warning[700] (4,73:1) et
 * brand[600] (5,92:1) passent, y compris comme texte sur leur propre lavis.
 */
export interface KindOption<T extends string> {
  kind: T;
  title: string;
  hint: string;
  /** Nom d'icône Ionicons. */
  icon: never | string;
}

export function KindChoiceCards<T extends string>({
  options,
  value,
  onChange,
  testIdPrefix = 'register-kind',
}: {
  options: readonly KindOption<T>[];
  value: T | null;
  onChange: (kind: T) => void;
  testIdPrefix?: string;
}) {
  // Deux teintes, dans l'ordre des options : la première distingue, la seconde souligne.
  const palette = [
    { accent: colors.warning[700], wash: colors.warning[50] },
    { accent: colors.brand[600], wash: colors.brand[50] },
  ];

  return (
    <View style={kindStyles.row} accessibilityRole="radiogroup">
      {options.map((option, index) => {
        const selected = value === option.kind;
        const tone = palette[index % palette.length]!;

        return (
          <TouchableOpacity
            key={option.kind}
            style={[
              kindStyles.card,
              selected && { borderColor: tone.accent, backgroundColor: tone.wash },
            ]}
            onPress={() => onChange(option.kind)}
            accessibilityRole="radio"
            accessibilityState={{ selected }}
            accessibilityLabel={`${option.title} — ${option.hint}`}
            testID={`${testIdPrefix}-${option.kind}`}
          >
            <Icon name={option.icon as never} size={22} color={selected ? tone.accent : colors.surface[400]} />
            <Text style={[kindStyles.title, selected && { color: tone.accent }]}>{option.title}</Text>
            <Text style={kindStyles.hint}>{option.hint}</Text>
          </TouchableOpacity>
        );
      })}
    </View>
  );
}

const kindStyles = StyleSheet.create({
  row: { flexDirection: 'row', gap: spacing.sm },
  card: {
    flex: 1,
    gap: 2,
    paddingVertical: spacing.md,
    paddingHorizontal: spacing.sm,
    borderRadius: radius.md,
    borderWidth: 1.5,
    borderColor: colors.surface[200],
    backgroundColor: '#ffffff',
  },
  title: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: colors.mode.tool.ink,
  },
  hint: { fontSize: typography.fontSize.xs, color: colors.surface[600] },
});
