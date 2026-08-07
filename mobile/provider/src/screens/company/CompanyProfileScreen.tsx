import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useAuth } from '@/auth';
import { Divider, Icon, Screen } from '@/ui';
import { colors, radius, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useSpacePreference } from '@/admin/useSpacePreference';

/**
 * Le profil du gérant DANS son espace société.
 *
 * POURQUOI UN ÉCRAN À PART, ET PAS `ProfileScreen`. La pile société ne monte que ses propres
 * écrans : le profil terrain ouvre les disponibilités, les badges, le KYC, les avis reçus — aucune
 * de ces routes n'existe ici, et la moitié d'entre elles ne concerne pas quelqu'un qui répartit
 * depuis son bureau. Le monter tel quel donnerait une page de boutons morts.
 *
 * IL PORTE LES SORTIES, et c'est sa raison d'être : la barre à cinq onglets était la seule surface
 * permanente de l'espace, et elle n'en offrait aucune. `RootNavigator` déclarait bien une route
 * `Profile`, mais aucun `navigate('Profile')` n'existait dans l'application — route montée,
 * joignable par personne. Un gérant entré ici ne pouvait plus se déconnecter.
 *
 * LA SORTIE VERS LE TERRAIN EST UN CHOIX EXPLICITE, PAS UN EFFACEMENT — et c'est la différence
 * avec `AdminProfileScreen`, dont cet écran reprend par ailleurs la forme. `resolveSpace` rend
 * `providerCompany` dès que `can_manage_company` est vrai et que le choix n'est pas `provider` :
 * un `clear()` copié de la console rendrait la main au même écran, en donnant l'illusion d'une
 * sortie. Il faut donc écrire « terrain », pas effacer.
 */
export function CompanyProfileScreen() {
  const theme = useThemeColors();
  const styles = stylesFor(theme);

  const { user, logout } = useAuth();
  const { choose, clear } = useSpacePreference();

  // Dans une petite société, la patronne nettoie souvent elle-même. Un gérant sans casquette
  // prestataire, lui, n'a rien à faire dans la pile terrain : chaque écran y appelle des routes
  // gardées `role:employe`.
  const intervientSurLeTerrain = user?.is_provider === true;

  // Le sélecteur d'espace n'est proposé qu'aux comptes administrateur (`resolveSpace`) : ailleurs,
  // effacer le choix ne mènerait nulle part.
  const passeParLeSelecteur = user?.is_admin === true;

  return (
    <Screen testID="profil-societe-prestataire">
      <View style={styles.identity}>
        <Text style={styles.name}>{user?.name ?? 'Mon compte'}</Text>
        <Text style={styles.email}>{user?.email}</Text>
      </View>

      <Divider />

      {intervientSurLeTerrain ? (
        <Row
          icon="construct-outline"
          label="Aller à l’espace terrain"
          hint="Mes missions, ma présence, mes revenus"
          onPress={() => void choose('provider')}
        />
      ) : null}

      {passeParLeSelecteur ? (
        <Row
          icon="swap-horizontal-outline"
          label="Changer d’espace"
          hint="Revenir au choix d’espace"
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
