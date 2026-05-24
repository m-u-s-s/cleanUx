import React from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { Screen, KPICard, Avatar, Badge, Button, Skeleton } from '@/ui';
import { PresenceToggle } from '@/screens/components/PresenceToggle';
import { useAuth } from '@/auth';
import { useMissionInbox } from '@/missions';
import { useWalletBalance } from '@/earnings';
import { colors, spacing, typography, radius, shadows } from '@/theme';

export function DashboardScreen() {
  const { user } = useAuth();
  const navigation = useNavigation<any>();
  const { data: assignments, isLoading: loadingMissions } = useMissionInbox();
  const { data: wallet, isLoading: loadingWallet } = useWalletBalance();

  const pendingCount = assignments?.length ?? 0;

  return (
    <Screen scroll>
      {/* Hero */}
      <View style={styles.hero}>
        <View style={styles.heroLeft}>
          <Text style={styles.greeting}>Bonjour{user?.name ? `, ${user.name.split(' ')[0]}` : ''}</Text>
          <Text style={styles.role}>{user?.email}</Text>
        </View>
        <Avatar name={user?.name ?? '?'} size={48} />
      </View>

      {/* Presence */}
      <PresenceToggle />

      {/* KPIs */}
      <View style={styles.kpiRow}>
        {loadingMissions || loadingWallet ? (
          <>
            <Skeleton width="48%" height={80} />
            <Skeleton width="48%" height={80} />
          </>
        ) : (
          <>
            <KPICard title="Missions en attente" value={pendingCount} tone={pendingCount > 0 ? 'warning' : 'neutral'} />
            <KPICard title="Solde disponible" value={wallet ? `${wallet.available.toFixed(0)} ${wallet.currency}` : '—'} tone="success" />
          </>
        )}
      </View>

      {/* Pending missions preview */}
      {pendingCount > 0 && (
        <>
          <Text style={styles.sectionTitle}>Nouvelles missions</Text>
          {assignments!.slice(0, 2).map(a => (
            <TouchableOpacity key={a.id} style={styles.missionCard} onPress={() => navigation.navigate('MissionDetail', { missionId: a.booking_id })}>
              <Text style={styles.missionService}>{a.service_name}</Text>
              <Text style={styles.missionClient}>{a.client_name} — {a.city}</Text>
              <View style={styles.missionMeta}>
                <Text style={styles.missionDate}>{a.scheduled_date} à {a.scheduled_time}</Text>
                {a.distance_km != null && <Badge label={`${a.distance_km.toFixed(1)} km`} variant="brand" />}
              </View>
            </TouchableOpacity>
          ))}
          <Button label="Voir toutes les missions" onPress={() => navigation.navigate('MainTabs', { screen: 'Missions' })} variant="secondary" fullWidth />
        </>
      )}

      {/* Quick actions */}
      <Text style={styles.sectionTitle}>Accès rapide</Text>
      <View style={styles.quickActions}>
        {[
          { label: 'Disponibilités', screen: 'Availability' },
          { label: 'Badges', screen: 'Badges' },
          { label: 'Revenus', screen: 'MainTabs' },
          { label: 'Messagerie', screen: 'ProviderChatList' },
        ].map(item => (
          <TouchableOpacity key={item.label} style={styles.quickCard} onPress={() => navigation.navigate(item.screen)}>
            <Text style={styles.quickLabel}>{item.label}</Text>
          </TouchableOpacity>
        ))}
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  hero: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: spacing.md, marginBottom: spacing.md },
  heroLeft: { flex: 1 },
  greeting: { fontSize: typography.fontSize['2xl'], fontWeight: typography.fontWeight.bold, color: colors.surface[900] },
  role: { fontSize: typography.fontSize.sm, color: colors.surface[500], marginTop: 2 },
  kpiRow: { flexDirection: 'row', gap: spacing.sm, marginTop: spacing.md, marginBottom: spacing.md },
  sectionTitle: { fontSize: typography.fontSize.lg, fontWeight: typography.fontWeight.semibold, color: colors.surface[800], marginTop: spacing.lg, marginBottom: spacing.sm },
  missionCard: { backgroundColor: '#fff', borderRadius: radius.md, padding: spacing.md, ...shadows.xs, marginBottom: spacing.sm },
  missionService: { fontSize: typography.fontSize.base, fontWeight: typography.fontWeight.semibold, color: colors.surface[900] },
  missionClient: { fontSize: typography.fontSize.sm, color: colors.surface[600], marginTop: 2 },
  missionMeta: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: spacing.xs },
  missionDate: { fontSize: typography.fontSize.xs, color: colors.brand[600] },
  quickActions: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  quickCard: { width: '48%', backgroundColor: '#fff', borderRadius: radius.md, padding: spacing.md, ...shadows.xs, alignItems: 'center' },
  quickLabel: { fontSize: typography.fontSize.sm, fontWeight: typography.fontWeight.medium, color: colors.brand[600] },
});
