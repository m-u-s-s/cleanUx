import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { useAuth } from '@/auth';
import type { RootStackParamList } from '@/navigation/types';
import { Divider, Icon, Screen } from '@/ui';
import { colors, radius, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useClientSpacePreference } from '@/company/useClientSpacePreference';

/**
 * Le profil du responsable de sites DANS son espace société.
 *
 * POURQUOI UN ÉCRAN À PART, ET PAS `ProfileScreen`. La pile société ne monte que ses propres
 * écrans : le profil personnel ouvre le parrainage, la fidélité, les pourboires, les moyens de
 * paiement — aucune de ces routes n'existe ici. Le monter tel quel donnerait une page de boutons
 * dont la moitié ne mène nulle part, ce que ce dépôt a déjà produit trois fois.
 *
 * IL PORTE LES DEUX SORTIES, et c'est sa raison d'être : la barre d'onglets était la seule surface
 * permanente de l'espace, et elle n'en offrait aucune. `RootNavigator` déclarait bien une route
 * `Profile`, mais aucun `navigate('Profile')` n'existait dans l'application — route montée,
 * joignable par personne. Quelqu'un entré ici ne pouvait ni revenir chez lui, ni se déconnecter.
 *
 * Même forme que `AdminProfileScreen` côté prestataire, qui tient ce rôle depuis la console.
 */
export function CompanyProfileScreen() {
  const theme = useThemeColors();
  const styles = stylesFor(theme);

  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const { user, logout } = useAuth();
  const { clear } = useClientSpacePreference();

  return (
    <Screen testID="profil-societe-cliente">
      <View style={styles.identity}>
        <Text style={styles.name}>{user?.name ?? 'Mon compte'}</Text>
        <Text style={styles.email}>{user?.email}</Text>
      </View>

      <Divider />

      {/*
        CHANGER D'ESPACE, ET NON « ALLER À ».
        `resolveClientSpace` repose la question dès que le choix est effacé — l'organisation étant
        un rattachement du compte et non un compte distinct, la même personne commande aussi pour
        elle-même le samedi.
      */}
      <Row
        icon="swap-horizontal-outline"
        label="Changer d’espace"
        hint="Revenir à mon espace personnel"
        onPress={() => void clear()}
      />

      {/* Le répertoire complet de l'espace société, servi par le serveur. */}
      <Row
        icon="grid-outline"
        label="Modules"
        hint="Tout ce que cet espace sait faire"
        onPress={() => navigation.navigate('Modules')}
      />

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
  const theme = useThemeColors();
  const styles = stylesFor(theme);

  const color = tone === 'danger' ? colors.danger[500] : theme.text;

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
