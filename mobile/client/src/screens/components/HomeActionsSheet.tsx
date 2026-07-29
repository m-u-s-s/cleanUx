import React, { forwardRef } from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import type GorhomBottomSheet from '@gorhom/bottom-sheet';
import { useNavigation } from '@react-navigation/native';
import { BottomSheet, KPICard, Skeleton, Divider, Icon } from '@/ui';
import { useBookings } from '@/booking';
import { colors, spacing, typography, radius, useThemeColors } from '@/theme';

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
  const navigation = useNavigation<any>();
  const themeColors = useThemeColors();
  const { data: bookings, isLoading } = useBookings();

  const completed = bookings?.filter(b => b.status === 'completed').length ?? 0;
  const active = bookings?.filter(b => ['pending', 'confirmed', 'in_progress'].includes(b.status)).length ?? 0;

  return (
    <BottomSheet ref={ref} snapPoints={['55%']}>
      <View style={styles.body} testID="home-actions-sheet">
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

const styles = StyleSheet.create({
  body: { gap: spacing.md, paddingHorizontal: spacing.md, paddingBottom: spacing.lg },
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
