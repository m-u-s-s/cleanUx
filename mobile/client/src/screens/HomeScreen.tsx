import React from 'react';
import { View, Text, TouchableOpacity, ScrollView, StyleSheet } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Screen, Button, KPICard, Avatar, Badge, Skeleton } from '@/ui';
import { useAuth } from '@/auth';
import { useBookings } from '@/booking';
import { colors, spacing, typography, radius, shadows } from '@/theme';
import type { RootStackParamList } from '@/navigation/types';

type Nav = NativeStackNavigationProp<RootStackParamList>;

export function HomeScreen() {
  const { user, logout } = useAuth();
  const { data: bookings, isLoading } = useBookings();
  const navigation = useNavigation<Nav>();

  const activeBookings = bookings?.filter(b => ['pending', 'confirmed', 'in_progress'].includes(b.status)) ?? [];
  const completedCount = bookings?.filter(b => b.status === 'completed').length ?? 0;

  return (
    <Screen scroll>
      {/* Hero */}
      <View style={styles.hero}>
        <View style={styles.heroLeft}>
          <Text style={styles.greeting}>Bonjour{user?.name ? `, ${user.name.split(' ')[0]}` : ''} 👋</Text>
          <Text style={styles.role}>{user?.email}</Text>
        </View>
        <Avatar name={user?.name ?? '?'} size={48} />
      </View>

      {/* KPIs */}
      <View style={styles.kpiRow}>
        {isLoading ? (
          <>
            <Skeleton width="48%" height={80} />
            <Skeleton width="48%" height={80} />
          </>
        ) : (
          <>
            <KPICard title="En cours" value={activeBookings.length} tone={activeBookings.length > 0 ? 'success' : 'neutral'} />
            <KPICard title="Terminées" value={completedCount} />
          </>
        )}
      </View>

      {/* CTA */}
      <Button label="Réserver un service" onPress={() => navigation.navigate('BookingWizard')} size="lg" fullWidth />

      {/* Quick actions */}
      <Text style={styles.sectionTitle}>Accès rapide</Text>
      <View style={styles.quickActions}>
        {[
          { label: 'Mes réservations', screen: 'MainTabs' },
          { label: 'Messagerie', screen: 'ChatList' },
          { label: 'Fidélité', screen: 'Loyalty' },
          { label: 'Devis IA', screen: 'AiQuote' },
        ].map(item => (
          <TouchableOpacity key={item.label} style={styles.quickCard} onPress={() => (navigation as any).navigate(item.screen)}>
            <Text style={styles.quickLabel}>{item.label}</Text>
          </TouchableOpacity>
        ))}
      </View>

      {/* Active bookings preview */}
      {activeBookings.length > 0 && (
        <>
          <Text style={styles.sectionTitle}>Réservations actives</Text>
          {activeBookings.slice(0, 3).map(b => (
            <TouchableOpacity key={b.id} style={styles.bookingCard} onPress={() => (navigation as any).navigate('BookingDetail', { bookingId: b.id })}>
              <View style={styles.bookingHeader}>
                <Text style={styles.bookingService}>{b.service_name}</Text>
                <Badge label={b.status} variant={b.status === 'in_progress' ? 'success' : 'brand'} />
              </View>
              <Text style={styles.bookingDate}>{b.scheduled_date} à {b.scheduled_time}</Text>
              <Text style={styles.bookingAddress}>{b.address}, {b.city}</Text>
            </TouchableOpacity>
          ))}
        </>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  hero: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: spacing.md, marginBottom: spacing.lg },
  heroLeft: { flex: 1 },
  greeting: { fontSize: typography.fontSize['2xl'], fontWeight: typography.fontWeight.bold, color: colors.surface[900] },
  role: { fontSize: typography.fontSize.sm, color: colors.surface[500], marginTop: 2 },
  kpiRow: { flexDirection: 'row', gap: spacing.sm, marginBottom: spacing.lg },
  sectionTitle: { fontSize: typography.fontSize.lg, fontWeight: typography.fontWeight.semibold, color: colors.surface[800], marginTop: spacing.xl, marginBottom: spacing.sm },
  quickActions: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  quickCard: { width: '48%', backgroundColor: '#fff', borderRadius: radius.md, padding: spacing.md, ...shadows.xs, alignItems: 'center' },
  quickLabel: { fontSize: typography.fontSize.sm, fontWeight: typography.fontWeight.medium, color: colors.brand[600] },
  bookingCard: { backgroundColor: '#fff', borderRadius: radius.md, padding: spacing.md, ...shadows.xs, marginBottom: spacing.sm },
  bookingHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  bookingService: { fontSize: typography.fontSize.base, fontWeight: typography.fontWeight.semibold, color: colors.surface[900] },
  bookingDate: { fontSize: typography.fontSize.sm, color: colors.surface[600], marginTop: spacing.xs },
  bookingAddress: { fontSize: typography.fontSize.xs, color: colors.surface[400], marginTop: 2 },
});
