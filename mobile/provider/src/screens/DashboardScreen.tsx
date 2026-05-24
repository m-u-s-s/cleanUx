import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { Screen, KPICard } from '@/ui';
import { PresenceToggle } from '@/screens/components/PresenceToggle';
import { useAuth } from '@/auth';
import { colors, spacing, typography } from '@/theme';

export function DashboardScreen() {
  const { user } = useAuth();
  return (
    <Screen scroll>
      <Text style={styles.greeting}>Bonjour{user?.name ? `, ${user.name}` : ''}</Text>
      <PresenceToggle />
      <View style={styles.kpis}>
        <KPICard title="Missions aujourd'hui" value="—" />
        <KPICard title="Revenus du jour" value="—" />
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  greeting: {
    fontSize: typography.fontSize['2xl'],
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
    marginTop: spacing.md,
    marginBottom: spacing.md,
  },
  kpis: {
    flexDirection: 'row',
    gap: spacing.sm,
    marginTop: spacing.md,
  },
});
