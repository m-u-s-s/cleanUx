import React, { forwardRef } from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import type GorhomBottomSheet from '@gorhom/bottom-sheet';
import { useNavigation } from '@react-navigation/native';
import { BottomSheet, KPICard, Skeleton, Button, Divider } from '@/ui';
import { PresenceToggle } from '@/screens/components/PresenceToggle';
import { useMissionInbox } from '@/missions';
import { useWalletBalance } from '@/earnings';
import { colors, spacing, typography, radius, shadows } from '@/theme';

type QuickAction = { label: string; screen: string; params?: object };

const QUICK_ACTIONS: QuickAction[] = [
  { label: 'Disponibilités', screen: 'Availability' },
  { label: 'Badges', screen: 'Badges' },
  // L'onglet Revenus vit dans MainTabs : sans le param imbriqué, le tap ne fait rien.
  { label: 'Revenus', screen: 'MainTabs', params: { screen: 'Earnings' } },
  { label: 'Messagerie', screen: 'ProviderChatList' },
];

export const DashboardActionsSheet = forwardRef<GorhomBottomSheet>((_props, ref) => {
  const navigation = useNavigation<any>();
  const { data: assignments, isLoading: loadingMissions } = useMissionInbox();
  const { data: wallet, isLoading: loadingWallet } = useWalletBalance();

  const go = (action: QuickAction) => {
    if (action.params) navigation.navigate(action.screen, action.params);
    else navigation.navigate(action.screen);
  };

  return (
    <BottomSheet ref={ref} snapPoints={['60%', '90%']}>
      <Text style={styles.sectionTitle} accessibilityRole="header">Statut</Text>
      <PresenceToggle />

      <Divider />

      <View style={styles.kpiRow}>
        {loadingMissions || loadingWallet ? (
          <>
            <Skeleton width="48%" height={80} />
            <Skeleton width="48%" height={80} />
          </>
        ) : (
          <>
            <KPICard
              title="Missions en attente"
              value={assignments?.length ?? 0}
              tone={(assignments?.length ?? 0) > 0 ? 'warning' : 'neutral'}
            />
            <KPICard
              title="Solde disponible"
              value={wallet && wallet.available != null ? `${wallet.available.toFixed(0)} ${wallet.currency ?? ''}`.trim() : '—'}
              tone="success"
            />
          </>
        )}
      </View>

      <Text style={styles.sectionTitle} accessibilityRole="header">Accès rapide</Text>
      <View style={styles.quickActions}>
        {QUICK_ACTIONS.map(action => (
          <TouchableOpacity key={action.label} style={styles.quickCard} onPress={() => go(action)}>
            <Text style={styles.quickLabel}>{action.label}</Text>
          </TouchableOpacity>
        ))}
      </View>

      <Button
        label="Voir toutes les missions"
        onPress={() => navigation.navigate('MainTabs', { screen: 'Missions' })}
        variant="secondary"
        fullWidth
      />
    </BottomSheet>
  );
});

DashboardActionsSheet.displayName = 'DashboardActionsSheet';

const styles = StyleSheet.create({
  sectionTitle: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[800],
    marginTop: spacing.md,
    marginBottom: spacing.sm,
  },
  kpiRow: { flexDirection: 'row', gap: spacing.sm, marginVertical: spacing.md },
  quickActions: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm, marginBottom: spacing.md },
  quickCard: { width: '48%', backgroundColor: '#fff', borderRadius: radius.md, padding: spacing.md, ...shadows.xs, alignItems: 'center' },
  quickLabel: { fontSize: typography.fontSize.sm, fontWeight: typography.fontWeight.medium, color: colors.brand[600] },
});
