/**
 * Briques visuelles communes aux écrans d'authentification.
 *
 * Extraites de LoginScreen sans changement de rendu : la connexion et l'inscription partagent le
 * même fond, le même wordmark, la même mise en scène et la même feuille de style, et le wizard
 * d'inscription les réemploie plutôt que de les redéfinir. Rien ici n'est propre à un écran.
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
import { TextInput, Icon, useReducedMotion } from '@/ui';
// Le fond, le wordmark, la mise en scène et le bandeau d'erreur sont partagés avec
// l'application cliente : ils vivent dans @/ui/authShell. Ce fichier ne garde que ce qui est
// propre au prestataire — le choix indépendant/société et les métiers.
import { CANVAS } from '@/ui/authShell';

export { CANVAS, authErrorMessage, AnimatedHalo, Wordmark, Stagger, FormError } from '@/ui/authShell';
import { type ProviderField } from '@/trades';
import { useRegistrationOptions, zonesPourMetier, flattenTrades } from '@/catalog';
import { ApiError } from '@/api';
import { colors, radius, shadows, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

export function KindChoice({
  value,
  onChange,
}: {
  value: 'independent' | 'company' | null;
  onChange: (kind: 'independent' | 'company') => void;
}) {
  const styles = stylesFor(useThemeColors());

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
 * Choix du métier exercé — parmi ceux que la plateforme VEND RÉELLEMENT.
 *
 * La liste venait de `GET /api/trades`, c'est-à-dire de la table `trades` telle quelle : tous les
 * métiers actifs, y compris ceux qu'aucune zone n'ouvre. Un carreleur pouvait donc s'inscrire sur
 * un métier que personne ne peut commander, attendre des missions qui ne viendraient jamais, et
 * conclure que la plateforme est vide. Le formulaire web, lui, lisait déjà
 * `/api/catalog/registration-options` : les deux écrans proposaient des listes différentes et rien
 * ne disait laquelle faisait foi.
 */
export function TradePicker({
  value,
  onChange,
}: {
  value: number | null;
  onChange: (id: number) => void;
}) {
  const styles = stylesFor(useThemeColors());

  const { data: options, isLoading, isError } = useRegistrationOptions();
  const trades = flattenTrades(options);

  if (isLoading) {
    return <Text style={styles.kindPrompt}>Chargement des métiers…</Text>;
  }

  if (isError || trades.length === 0) {
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
 * OÙ LE PRESTATAIRE INTERVIENT — la moitié de la couverture qui manquait.
 *
 * L'inscription native ne demandait QUE le métier. `trade_user` était donc rempli et
 * `employee_zone_assignments` restait vide : pour le dispatch planifié, qui travaille sur les zones
 * DÉCLARÉES et non sur la position du jour, ce prestataire n'existait dans aucune zone. Il
 * terminait son inscription, passait la vérification, et ne recevait jamais un seul rendez-vous —
 * sans qu'aucun écran ne puisse lui dire pourquoi.
 *
 * LES ZONES SONT RESTREINTES AU MÉTIER CHOISI. Proposer une zone où ce métier n'est pas vendu
 * enregistrerait une couverture qui ne peut rien produire, et le prestataire l'aurait pourtant vue
 * cochée à l'écran.
 */
export function ZonePicker({
  tradeId,
  value,
  onChange,
}: {
  tradeId: number | null;
  value: number[];
  onChange: (ids: number[]) => void;
}) {
  const styles = stylesFor(useThemeColors());

  const { data: options, isLoading, isError } = useRegistrationOptions();
  const zones = zonesPourMetier(options, tradeId);

  if (isLoading) {
    return <Text style={styles.kindPrompt}>Chargement des zones…</Text>;
  }

  if (isError || zones.length === 0) {
    return <Text style={styles.fieldError}>Impossible de charger la liste des zones.</Text>;
  }

  return (
    <View style={styles.tradeGrid} accessibilityRole="radiogroup">
      {zones.map(zone => {
        const selected = value.includes(zone.id);

        return (
          <TouchableOpacity
            key={zone.id}
            style={[styles.tradeChip, selected && styles.tradeChipSelected]}
            onPress={() =>
              onChange(selected ? value.filter(id => id !== zone.id) : [...value, zone.id])
            }
            accessibilityRole="checkbox"
            accessibilityState={{ checked: selected }}
            accessibilityLabel={zone.name}
            testID={`register-zone-${zone.id}`}
          >
            <Text style={[styles.tradeChipText, selected && styles.tradeChipTextSelected]}>
              {zone.name}
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
export function TradeQuestions({
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
  const styles = stylesFor(useThemeColors());

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

export const stylesFor = (t: ThemeTokens) => StyleSheet.create({
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
    backgroundColor: t.card,
    borderRadius: radius.lg,
    padding: spacing.lg,
    borderWidth: 1,
    borderColor: t.border,
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
  checkbox: { width: 20, height: 20, borderRadius: 4, borderWidth: 2, borderColor: t.textMuted, marginTop: 2, flexShrink: 0 },
  checkboxChecked: { backgroundColor: colors.brand[500], borderColor: colors.brand[500] },
  termsText: { flex: 1, fontSize: typography.fontSize.sm, color: t.text },
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
    borderColor: t.border,
    backgroundColor: t.card,
  },
  kindTitle: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: colors.mode.tool.ink,
  },
  kindHint: { fontSize: typography.fontSize.xs, color: t.textSecondary },
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
    borderColor: t.border,
    backgroundColor: t.card,
  },
  tradeChipSelected: { borderColor: colors.brand[600], backgroundColor: t.tint.brand },
  tradeChipText: { fontSize: typography.fontSize.sm, color: t.textSecondary },
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
    backgroundColor: t.tint.danger,
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
