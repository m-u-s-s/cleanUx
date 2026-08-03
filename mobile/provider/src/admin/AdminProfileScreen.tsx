import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useAuth } from '@/auth';
import { Divider, Icon, Screen } from '@/ui';
import { colors, radius, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useSpacePreference } from './useSpacePreference';

/**
 * Le profil de l'administrateur dans sa console.
 *
 * Il porte la SORTIE de l'espace admin. Retenir le choix d'espace sans porte de sortie
 * enfermerait dans l'autre sens : un compte à double casquette qui a choisi « administration » un
 * matin doit pouvoir retourner sur le terrain le lendemain sans se déconnecter.
 */
export function AdminProfileScreen() {
  const styles = stylesFor(useThemeColors());

  const { user, logout } = useAuth();
  const { clear } = useSpacePreference();

  const doubleCasquette = user?.is_admin === true && user?.is_provider === true;

  return (
    <Screen>
      <View style={styles.identity}>
        <Text style={styles.name}>{user?.name ?? 'Administrateur'}</Text>
        <Text style={styles.email}>{user?.email}</Text>
      </View>

      <Divider />

      {doubleCasquette ? (
        <Row
          icon="swap-horizontal-outline"
          label="Changer d’espace"
          hint="Revenir à l’espace prestataire"
          onPress={() => void clear()}
        />
      ) : null}

      <Row
        icon="log-out-outline"
        label="Se déconnecter"
        tone="danger"
        onPress={() => void logout()}
      />
    </Screen>
  );
}

function Row({
  icon,
  label,
  hint,
  tone = 'neutral',
  onPress,
}: {
  icon: string;
  label: string;
  hint?: string;
  tone?: 'neutral' | 'danger';
  onPress: () => void;
}) {
  const styles = stylesFor(useThemeColors());

  const color = tone === 'danger' ? colors.danger[500] : colors.surface[900];

  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={label}
      style={({ pressed }) => [styles.row, pressed && styles.rowPressed]}
    >
      <Icon name={icon as never} size={22} color={color} />
      <View style={{ flex: 1 }}>
        <Text style={[styles.rowLabel, { color }]}>{label}</Text>
        {hint ? <Text style={styles.rowHint}>{hint}</Text> : null}
      </View>
    </Pressable>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  identity: { paddingVertical: spacing.lg },
  name: { ...typography.preset.headline, color: t.text },
  email: { fontSize: typography.fontSize.sm, color: t.textSecondary, marginTop: spacing.xs },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    paddingVertical: spacing.md,
    borderRadius: radius.md,
  },
  rowPressed: { backgroundColor: t.inputBg },
  rowLabel: { fontSize: typography.fontSize.base },
  rowHint: { fontSize: typography.fontSize.xs, color: t.textSecondary, marginTop: 2 },
});
