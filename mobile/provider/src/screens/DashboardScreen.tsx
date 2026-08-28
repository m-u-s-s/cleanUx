import React, { useCallback, useRef } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import type GorhomBottomSheet from '@gorhom/bottom-sheet';
import { Screen, Avatar, Button } from '@/ui';
import { useAuth } from '@/auth';
import { ProviderMap } from '@/screens/components/ProviderMap';
import { PresencePill } from '@/screens/components/PresencePill';
import { DashboardActionsSheet } from '@/screens/components/DashboardActionsSheet';
import {spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

export function DashboardScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  const { user } = useAuth();
  const sheetRef = useRef<GorhomBottomSheet>(null);

  const openSheet = useCallback(() => sheetRef.current?.expand(), []);

  return (
    <Screen testID="dashboard-screen">
      <View style={styles.hero}>
        <View style={styles.heroLeft}>
          <Text style={styles.greeting}>Bonjour{user?.name ? `, ${user.name.split(' ')[0]}` : ''}</Text>
          <Text style={styles.role}>{user?.email}</Text>
        </View>
        <Avatar name={user?.name ?? '?'} size={48} />
      </View>

      <View style={styles.mapWrap}>
        <ProviderMap />
      </View>

      <View style={styles.floating} pointerEvents="box-none">
        <PresencePill onPress={openSheet} />
        <Button label={tr('dashboard.actions')} onPress={openSheet} fullWidth size="lg" />
      </View>

      <DashboardActionsSheet ref={sheetRef} />
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  hero: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginVertical: spacing.md },
  heroLeft: { flex: 1 },
  greeting: { fontSize: typography.fontSize['2xl'], fontWeight: typography.fontWeight.bold, color: t.text },
  role: { fontSize: typography.fontSize.sm, color: t.textSecondary, marginTop: 2 },
  mapWrap: { flex: 1, borderRadius: 12, overflow: 'hidden' },
  floating: { position: 'absolute', left: spacing.md, right: spacing.md, bottom: spacing.lg, gap: spacing.sm },
});
