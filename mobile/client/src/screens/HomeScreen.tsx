import React, { useCallback } from 'react';
import { View, Text, TouchableOpacity, ScrollView, StyleSheet } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Screen, Button, KPICard, Avatar, Badge, Skeleton, Icon } from '@/ui';
import { useAuth } from '@/auth';
import { useBookings } from '@/booking';
import { colors, spacing, typography, radius, shadows, useThemeColors } from '@/theme';
import type { RootStackParamList } from '@/navigation/types';

type Nav = NativeStackNavigationProp<RootStackParamList>;

const QUICK_ACTIONS = [
  { label: 'Mes réservations', screen: 'MainTabs', icon: 'calendar-outline' as const },
  { label: 'Messagerie', screen: 'ChatList', icon: 'chatbubble-outline' as const },
  { label: 'Fidélité', screen: 'Loyalty', icon: 'gift-outline' as const },
  { label: 'Devis IA', screen: 'AiQuote', icon: 'sparkles-outline' as const },
] as const;

export function HomeScreen() {
  const { user, logout } = useAuth();
  const { data: bookings, isLoading } = useBookings();
  const navigation = useNavigation<Nav>();
  const themeColors = useThemeColors();

  const activeBookings = bookings?.filter(b => ['pending', 'confirmed', 'in_progress'].includes(b.status)) ?? [];
  const completedCount = bookings?.filter(b => b.status === 'completed').length ?? 0;

  const isFirstTime = !isLoading && activeBookings.length === 0 && completedCount === 0;

  const handleNavigateBookingWizard = useCallback(() => navigation.navigate('BookingWizard'), [navigation]);
  const handleNavigateChatList = useCallback(() => navigation.navigate('ChatList' as any), [navigation]);
  const handleNavigateLoyalty = useCallback(() => navigation.navigate('Loyalty' as any), [navigation]);
  const handleNavigateAiQuote = useCallback(() => navigation.navigate('AiQuote' as any), [navigation]);

  return (
    <Screen scroll>
      {/* Hero */}
      <View style={styles.hero}>
        <View style={styles.heroLeft}>
          <Text style={[styles.greeting, { color: themeColors.text }]}>Bonjour{user?.name ? `, ${user.name.split(' ')[0]}` : ''} 👋</Text>
          <Text style={[styles.role, { color: themeColors.textMuted }]}>{user?.email}</Text>
        </View>
        <Avatar name={user?.name ?? '?'} size={48} accessibilityLabel={user?.name ?? 'Profil'} />
      </View>

      {/* KPIs or Welcome card */}
      {isLoading ? (
        <View style={styles.kpiRow}>
          <Skeleton width="48%" height={80} />
          <Skeleton width="48%" height={80} />
        </View>
      ) : isFirstTime ? (
        <View style={[styles.welcomeCard, { backgroundColor: themeColors.card }]}>
          <Icon name="home-outline" size={48} color={colors.brand[400]} style={styles.welcomeIcon} />
          <Text style={[styles.welcomeTitle, { color: themeColors.text }]}>Bienvenue sur CleanUx</Text>
          <Text style={[styles.welcomeText, { color: themeColors.textSecondary }]}>Réservez votre premier service et découvrez une nouvelle façon de gérer votre maison.</Text>
          <Button label="Réserver mon premier service" onPress={() => navigation.navigate('BookingWizard')} fullWidth size="lg" />
        </View>
      ) : (
        <View style={styles.kpiRow}>
          <KPICard title="En cours" value={activeBookings.length} tone={activeBookings.length > 0 ? 'success' : 'neutral'} />
          <KPICard title="Terminées" value={completedCount} />
        </View>
      )}

      {/* CTA */}
      {!isFirstTime && (
        <Button label="Réserver un service" onPress={() => navigation.navigate('BookingWizard')} size="lg" fullWidth />
      )}

      {/* Quick actions */}
      <Text style={[styles.sectionTitle, { color: themeColors.textSecondary }]} accessibilityRole="header">Accès rapide</Text>
      <View style={styles.quickActions}>
        {QUICK_ACTIONS.map(item => (
          <TouchableOpacity
            key={item.label}
            style={[styles.quickCard, { backgroundColor: themeColors.card }]}
            onPress={() => (navigation as any).navigate(item.screen)}
            accessibilityLabel={item.label}
            accessibilityRole="button"
          >
            <Icon name={item.icon} size={24} color={colors.brand[500]} />
            <Text style={styles.quickLabel}>{item.label}</Text>
          </TouchableOpacity>
        ))}
      </View>

      {/* Active bookings preview */}
      {activeBookings.length > 0 && (
        <>
          <Text style={[styles.sectionTitle, { color: themeColors.textSecondary }]} accessibilityRole="header">Réservations actives</Text>
          {activeBookings.slice(0, 3).map(b => (
            <TouchableOpacity key={b.id} style={[styles.bookingCard, { backgroundColor: themeColors.card }]} onPress={() => (navigation as any).navigate('BookingDetail', { bookingId: b.id })}>
              <View style={styles.bookingHeader}>
                <Text style={[styles.bookingService, { color: themeColors.text }]}>{b.service_name}</Text>
                <Badge label={b.status} variant={b.status === 'in_progress' ? 'success' : 'brand'} />
              </View>
              <Text style={[styles.bookingDate, { color: themeColors.textSecondary }]}>{b.scheduled_date} à {b.scheduled_time}</Text>
              <Text style={[styles.bookingAddress, { color: themeColors.textMuted }]}>{b.address}, {b.city}</Text>
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
  greeting: { fontSize: typography.fontSize['2xl'], fontWeight: typography.fontWeight.bold },
  role: { fontSize: typography.fontSize.sm, marginTop: 2 },
  kpiRow: { flexDirection: 'row', gap: spacing.sm, marginBottom: spacing.lg },
  welcomeCard: { borderRadius: radius.md, padding: spacing.lg, ...shadows.soft, marginBottom: spacing.lg, alignItems: 'center', gap: spacing.sm },
  welcomeIcon: { marginBottom: spacing.xs },
  welcomeTitle: { fontSize: typography.fontSize.xl, fontWeight: typography.fontWeight.bold, textAlign: 'center' },
  welcomeText: { fontSize: typography.fontSize.sm, textAlign: 'center', lineHeight: 20, marginBottom: spacing.sm },
  sectionTitle: { fontSize: typography.fontSize.lg, fontWeight: typography.fontWeight.semibold, marginTop: spacing.xl, marginBottom: spacing.sm },
  quickActions: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  quickCard: { width: '48%', borderRadius: radius.md, padding: spacing.md, ...shadows.xs, alignItems: 'center', gap: spacing.xs },
  quickLabel: { fontSize: typography.fontSize.sm, fontWeight: typography.fontWeight.medium, color: colors.brand[600] },
  bookingCard: { borderRadius: radius.md, padding: spacing.md, ...shadows.xs, marginBottom: spacing.sm },
  bookingHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  bookingService: { fontSize: typography.fontSize.base, fontWeight: typography.fontWeight.semibold },
  bookingDate: { fontSize: typography.fontSize.sm, marginTop: spacing.xs },
  bookingAddress: { fontSize: typography.fontSize.xs, marginTop: 2 },
});
