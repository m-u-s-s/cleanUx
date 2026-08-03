import React, { forwardRef } from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import type GorhomBottomSheet from '@gorhom/bottom-sheet';
import { useNavigation } from '@react-navigation/native';
import { BottomSheet, KPICard, Skeleton, Divider, Icon } from '@/ui';
import { useBookings } from '@/booking';
import { colors, spacing, typography, radius, useThemeColors } from '@/theme';
import type { ThemeTokens } from '@/theme/useThemeColors';

/**
 * Actions secondaires de l'accueil client, sur le modèle du tableau de bord prestataire.
 *
 * L'accueil empilait quatre pavés d'accès rapide au milieu de l'écran, si bien que les
 * réservations en cours — ce qu'un client vient réellement voir — se retrouvaient poussées tout
 * en bas. Le tableau de bord prestataire résout le même problème en logeant ses actions dans une
 * feuille, ne laissant à l'écran que l'essentiel.
 *
 * `useBookings` sert déjà l'accueil : la feuille partage son cache React Query et ne déclenche
 * donc aucune requête supplémentaire. Le gating sur l'ouverture qu'emploie la feuille prestataire
 * n'a pas lieu d'être ici — il y protège une interrogation périodique, absente de ce côté.
 */
/**
 * Les trois façons de commander un service.
 *
 * Elles ouvrent toutes LE MÊME parcours — le moteur de commande, servi par la vue embarquée —
 * avec leur intention dans l'URL. L'ancien assistant natif en cinq étapes ne connaît ni secteur,
 * ni question propre au métier, ni instantané de réponse : deux parcours écrivant la même table
 * par des chemins différents produiraient des devis explicables ou non selon la porte empruntée.
 *
 * Le paramètre `mode` compte : sans lui, les trois cartes arriveraient sur le même écran planifié
 * et le choix d'entrée serait décoratif — le client demanderait « immédiat », puis devrait le
 * redemander. Un métier qui n'accepte pas le mode demandé le dit à l'écran plutôt que de basculer
 * en silence.
 */
type BookingMode = {
  key: string;
  title: string;
  hint: string;
  icon: 'flash-outline' | 'calendar-outline' | 'layers-outline';
  navigate: (navigate: (screen: string, params?: object) => void) => void;
};

const BOOKING_MODES: BookingMode[] = [
  {
    key: 'asap',
    title: 'Intervention immédiate',
    hint: 'Un prestataire disponible maintenant',
    icon: 'flash-outline',
    navigate: go => go('EmbeddedModule', {
      path: '/commander?mode=asap',
      title: 'Intervention immédiate',
    }),
  },
  {
    key: 'scheduled',
    title: 'Prendre rendez-vous',
    hint: 'Choisissez votre date et votre heure',
    icon: 'calendar-outline',
    navigate: go => go('EmbeddedModule', {
      path: '/commander?mode=scheduled',
      title: 'Prendre rendez-vous',
    }),
  },
  {
    key: 'bundle',
    title: 'Plusieurs services',
    hint: 'Un chantier regroupant plusieurs métiers',
    icon: 'layers-outline',
    navigate: go => go('EmbeddedModule', {
      path: '/commander?mode=bundle',
      title: 'Chantier multi-services',
    }),
  },
];

type QuickAction = {
  label: string;
  screen: string;
  params?: object;
  icon: 'calendar-outline' | 'chatbubble-outline' | 'gift-outline' | 'sparkles-outline' | 'receipt-outline';
};

const QUICK_ACTIONS: QuickAction[] = [
  // L'onglet Réservations vit dans MainTabs : sans le paramètre imbriqué, le tap ne fait rien —
  // c'est exactement le défaut qu'avait rencontré le tableau de bord prestataire sur son onglet
  // Revenus, et que son commentaire signale.
  { label: 'Mes réservations', screen: 'MainTabs', params: { screen: 'Bookings' }, icon: 'calendar-outline' },
  { label: 'Messagerie', screen: 'ChatList', icon: 'chatbubble-outline' },
  { label: 'Fidélité', screen: 'Loyalty', icon: 'gift-outline' },
  { label: 'Devis IA', screen: 'AiQuote', icon: 'sparkles-outline' },
  { label: 'Mes factures', screen: 'Invoices', icon: 'receipt-outline' },
];

export const HomeActionsSheet = forwardRef<GorhomBottomSheet>((_props, ref) => {
  const styles = stylesFor(useThemeColors());
  const navigation = useNavigation<any>();
  const themeColors = useThemeColors();
  const { data: bookings, isLoading } = useBookings();

  // Même règle que l'accueil : c'est l'état normalisé qui fait foi, pas le statut brut.
  const stateOf = (b: { state?: string; status: string }) => b.state ?? b.status;
  const completed = bookings?.filter(b => stateOf(b) === 'completed').length ?? 0;
  const active = bookings?.filter(b => ['pending', 'confirmed', 'in_progress'].includes(stateOf(b))).length ?? 0;

  return (
    <BottomSheet ref={ref} snapPoints={['55%']}>
      <View style={styles.body} testID="home-actions-sheet">
        <Text style={styles.sectionTitle} accessibilityRole="header">Quel type de mission ?</Text>

        <View style={styles.modes}>
          {BOOKING_MODES.map(mode => (
            <TouchableOpacity
              key={mode.key}
              style={[styles.modeCard, { backgroundColor: themeColors.card }]}
              onPress={() => mode.navigate((screen, params) => navigation.navigate(screen, params))}
              accessibilityRole="button"
              accessibilityLabel={`${mode.title} — ${mode.hint}`}
              testID={`booking-mode-${mode.key}`}
            >
              <View style={styles.modeIcon}>
                <Icon name={mode.icon} size={22} color={colors.brand[600]} />
              </View>
              <View style={styles.modeBody}>
                <Text style={[styles.modeTitle, { color: themeColors.text }]}>{mode.title}</Text>
                <Text style={styles.modeHint}>{mode.hint}</Text>
              </View>
              <Icon name="chevron-forward" size={18} color={colors.surface[400]} />
            </TouchableOpacity>
          ))}
        </View>

        <Divider />

        {isLoading ? (
          <View style={styles.kpiRow}>
            <Skeleton width="48%" height={80} />
            <Skeleton width="48%" height={80} />
          </View>
        ) : (
          <View style={styles.kpiRow}>
            <KPICard title="En cours" value={active} tone={active > 0 ? 'success' : 'neutral'} />
            <KPICard title="Terminées" value={completed} />
          </View>
        )}

        <Divider />

        <View style={styles.actions}>
          {QUICK_ACTIONS.map(action => (
            <TouchableOpacity
              key={action.label}
              style={[styles.actionCard, { backgroundColor: themeColors.card }]}
              onPress={() => navigation.navigate(action.screen, action.params)}
              accessibilityRole="button"
              accessibilityLabel={action.label}
              testID={`home-action-${action.screen}`}
            >
              <Icon name={action.icon} size={22} color={colors.brand[500]} />
              <Text style={styles.actionLabel}>{action.label}</Text>
            </TouchableOpacity>
          ))}
        </View>
      </View>
    </BottomSheet>
  );
});

HomeActionsSheet.displayName = 'HomeActionsSheet';

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  body: { gap: spacing.md, paddingHorizontal: spacing.md, paddingBottom: spacing.lg },
  sectionTitle: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
  },
  modes: { gap: spacing.sm },
  // La carte entière est la cible tactile, bien au-delà des 44 pt recommandés.
  modeCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    padding: spacing.md,
    borderRadius: radius.md,
    minHeight: 64,
  },
  modeIcon: {
    width: 40,
    height: 40,
    borderRadius: radius.md,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: t.tint.brand,
  },
  modeBody: { flex: 1, gap: 2 },
  modeTitle: { fontSize: typography.fontSize.base, fontWeight: typography.fontWeight.semibold },
  modeHint: { fontSize: typography.fontSize.xs, color: t.textSecondary },
  kpiRow: { flexDirection: 'row', gap: spacing.sm },
  actions: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  // Deux par ligne, comme la grille d'origine : la disposition est conservée, seule sa place
  // change. 88 pt de hauteur utile, bien au-delà de la cible tactile minimale.
  actionCard: {
    width: '48%',
    borderRadius: radius.md,
    padding: spacing.md,
    alignItems: 'center',
    gap: spacing.xs,
    minHeight: 88,
    justifyContent: 'center',
  },
  actionLabel: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.medium,
    color: colors.brand[600],
    textAlign: 'center',
  },
});
