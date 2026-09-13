import React, { useCallback, useRef } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import type GorhomBottomSheet from '@gorhom/bottom-sheet';
import { Screen, Avatar, Button } from '@/ui';
import { useAuth } from '@/auth';
import { ProviderMap } from '@/screens/components/ProviderMap';
import { PresencePill } from '@/screens/components/PresencePill';
import { DashboardActionsSheet } from '@/screens/components/DashboardActionsSheet';
import { radius, spacing, typography } from '@/theme';
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
    // `toile` : le tableau de bord est une carte plein ecran, rien n'y est pose a nu.
    <Screen testID="dashboard-screen" toile>
      <View style={styles.hero}>
        <View style={styles.heroLeft}>
          <Text style={styles.greeting}>
            {user?.name
              ? tr('commun.bonjour_prenom', { prenom: user.name.split(' ')[0] ?? '' })
              : tr('commun.bonjour')}
          </Text>
        </View>
        <Avatar name={user?.name ?? '?'} size={48} accessibilityLabel={user?.name ?? tr('commun.profil')} />
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
  /* L'adresse e-mail occupait une ligne sous la salutation pour redire ce que l'onglet Profil
     dit deja. L'ecran est carte-first : la place rendue va a la carte. */
  mapWrap: { flex: 1, borderRadius: radius.lg, overflow: 'hidden' },
  floating: { position: 'absolute', left: spacing.md, right: spacing.md, bottom: spacing.lg, gap: spacing.sm },
});
