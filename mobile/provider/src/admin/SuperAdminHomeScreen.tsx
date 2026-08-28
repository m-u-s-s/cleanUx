import React from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { useAuth } from '@/auth';
import { Divider, Icon, Screen } from '@/ui';
import { colors, radius, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';
import { useSpacePreference } from './useSpacePreference';
import { useTraduction } from '@/i18n';

/**
 * L'accueil du SUPER ADMINISTRATEUR — le sixième rôle, et le seul qui n'avait pas d'espace.
 *
 * POURQUOI IL NE RESSEMBLE PAS À LA CONSOLE. `AdminHomeScreen` montre sept nombres
 * d'exploitation : rendez-vous du jour, missions en retard, litiges ouverts. Celui-ci regarde la
 * PLATEFORME — les six rôles et ce qu'ils recouvrent, les onze sous-rôles de la société
 * prestataire, et les portes vers les deux autres espaces du compte.
 *
 * IL N'APPELLE AUCUN POINT D'API, ET C'EST DÉLIBÉRÉ POUR CETTE PREMIÈRE VERSION. Ce qu'il rend est
 * la STRUCTURE des rôles, connue du client — pas des chiffres, qui demanderaient un point
 * d'agrégat côté serveur. Mieux vaut un écran honnête et complet qu'un écran de compteurs qui
 * affichent « — » faute de données : ce dépôt tient déjà la règle qu'un compteur non mesurable ne
 * doit jamais s'afficher en « 0 ».
 *
 * L'idiome visuel est celui des autres espaces natifs : `Screen`, cartes à `radius.md`, libellés
 * en petites capitales, couleurs prises dans les jetons de thème — jamais en dur.
 */
const ROLES = [
  { cle: 'super_admin', label: 'Super administrateur', icone: 'shield-checkmark-outline', sousRoles: 0 },
  { cle: 'admin', label: 'Administrateur', icone: 'speedometer-outline', sousRoles: 0 },
  { cle: 'client_individuelle', label: 'Client particulier', icone: 'person-outline', sousRoles: 0 },
  { cle: 'client_societe', label: 'Client société', icone: 'business-outline', sousRoles: 0 },
  { cle: 'provider_individuelle', label: 'Prestataire indépendant', icone: 'construct-outline', sousRoles: 0 },
  { cle: 'provider_societe', label: 'Société prestataire', icone: 'people-outline', sousRoles: 11 },
] as const;

/** Les onze rôles d'organisation, avec leur rang d'autorité — `canManage()` compare ces nombres. */
const SOUS_ROLES = [
  { label: 'Propriétaire', rang: 100 },
  { label: 'Directeur opérations', rang: 80 },
  { label: 'Gestionnaire', rang: 80 },
  { label: 'Coordinateur', rang: 60 },
  { label: 'Responsable de site', rang: 60 },
  { label: 'Responsable qualité', rang: 50 },
  { label: 'Finance', rang: 50 },
  { label: 'Chef d’équipe', rang: 40 },
  { label: 'Demandeur', rang: 20 },
  { label: 'Nettoyeur', rang: 20 },
  { label: 'Lecteur', rang: 10 },
] as const;

export function SuperAdminHomeScreen() {
  const { t: tr } = useTraduction();
  const theme = useThemeColors();
  const styles = stylesFor(theme);

  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const { user, logout } = useAuth();
  const { choose } = useSpacePreference();

  return (
    <Screen testID="super-admin-home">
      <ScrollView showsVerticalScrollIndicator={false} contentContainerStyle={styles.contenu}>
        <View>
          <Text style={styles.surtitre}>{tr('super_admin_home.super_administration')}</Text>
          <Text style={styles.titre}>{tr('super_admin_home.la_plateforme_brio')}</Text>
          <Text style={styles.sousTitre}>{user?.name ?? 'Compte'}</Text>
        </View>

        <View>
          <Text style={styles.sectionTitre}>{tr('super_admin_home.les_six_roles')}</Text>

          <View style={styles.grille}>
            {ROLES.map((role) => (
              <View key={role.cle} style={styles.carte}>
                <Icon name={role.icone as never} size={20} color={colors.brand[500]} />
                <Text style={styles.carteLabel}>{role.label}</Text>
                <Text style={styles.carteNote}>
                  {role.sousRoles > 0 ? `${role.sousRoles} sous-rôles` : 'Pas de sous-rôle'}
                </Text>
              </View>
            ))}
          </View>
        </View>

        <View>
          <Text style={styles.sectionTitre}>{tr('super_admin_home.sous_roles_societe_prestataire')}</Text>

          <View style={styles.puces}>
            {SOUS_ROLES.map((sousRole) => (
              <View key={sousRole.label} style={styles.puce}>
                <Text style={styles.puceLabel}>{sousRole.label}</Text>
                <Text style={styles.puceRang}>rang {sousRole.rang}</Text>
              </View>
            ))}
          </View>
        </View>

        <Divider />

        {/*
          LES DEUX AUTRES ESPACES DU COMPTE.

          Un super administrateur est aussi administrateur, et parfois prestataire. Sans ces
          portes, il serait enfermé dans son espace — le défaut que `clear()` a déjà corrigé deux
          fois dans ce dépôt. On ÉCRIT le choix plutôt que de l'effacer : `resolveSpace` rend
          `superAdmin` dès que le drapeau est vrai et que le choix n'est pas explicite, si bien
          qu'effacer rendrait la main au même écran.
        */}
        <Ligne
          icone="speedometer-outline"
          label={tr('super_admin_home.console_dadministration')}
          indice="Exploitation : missions, zones, litiges"
          onPress={() => void choose('admin')}
        />

        {user?.is_provider === true ? (
          <Ligne
            icone="construct-outline"
            label={tr('super_admin_home.espace_terrain')}
            indice="Mes missions, ma présence"
            onPress={() => void choose('provider')}
          />
        ) : null}

        {/* Le répertoire complet — 90 modules pour ce rôle, servis par le serveur. */}
        <Ligne
          icone="grid-outline"
          label={tr('super_admin_home.modules')}
          indice="Tout ce que cet espace sait faire"
          onPress={() => navigation.navigate('Modules')}
        />

        <Ligne
          icone="settings-outline"
          label={tr('super_admin_home.reglages')}
          onPress={() => navigation.navigate('Appearance')}
        />

        <Ligne icone="log-out-outline" label={tr('super_admin_home.se_deconnecter')} ton="danger" onPress={() => void logout()} />
      </ScrollView>
    </Screen>
  );
}

function Ligne({
  icone,
  label,
  indice,
  ton = 'neutre',
  onPress,
}: {
  icone: string;
  label: string;
  indice?: string;
  ton?: 'neutre' | 'danger';
  onPress: () => void;
}) {
  const theme = useThemeColors();
  const styles = stylesFor(theme);

  const couleur = ton === 'danger' ? colors.danger[500] : theme.text;

  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={label}
      style={({ pressed }) => [styles.ligne, pressed && styles.lignePressee]}
    >
      <Icon name={icone as never} size={22} color={couleur} />
      <View style={{ flex: 1 }}>
        <Text style={[styles.ligneLabel, { color: couleur }]}>{label}</Text>
        {indice ? <Text style={styles.ligneIndice}>{indice}</Text> : null}
      </View>
    </Pressable>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  contenu: { gap: spacing.lg, paddingBottom: spacing.xl },
  surtitre: {
    fontSize: typography.fontSize.xs,
    fontWeight: '700',
    letterSpacing: 1,
    textTransform: 'uppercase',
    color: t.textMuted,
  },
  titre: { ...typography.preset.headline, color: t.text, marginTop: spacing.xs },
  sousTitre: { fontSize: typography.fontSize.sm, color: t.textSecondary, marginTop: 2 },
  sectionTitre: {
    fontSize: typography.fontSize.xs,
    fontWeight: '700',
    letterSpacing: 0.8,
    textTransform: 'uppercase',
    color: t.textMuted,
    marginBottom: spacing.sm,
  },
  grille: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  carte: {
    flexGrow: 1,
    flexBasis: '45%',
    gap: spacing.xs,
    padding: spacing.md,
    borderRadius: radius.md,
    backgroundColor: t.inputBg,
  },
  carteLabel: { fontSize: typography.fontSize.sm, fontWeight: '600', color: t.text },
  carteNote: { fontSize: typography.fontSize.xs, color: t.textSecondary },
  puces: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
  puce: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.xs,
    paddingVertical: spacing.xs,
    paddingHorizontal: spacing.sm,
    borderRadius: radius.sm,
    backgroundColor: t.inputBg,
  },
  puceLabel: { fontSize: typography.fontSize.xs, fontWeight: '600', color: t.text },
  puceRang: { fontSize: typography.fontSize.xs, color: t.textMuted },
  ligne: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    paddingVertical: spacing.md,
    borderRadius: radius.md,
  },
  lignePressee: { backgroundColor: t.inputBg },
  ligneLabel: { fontSize: typography.fontSize.base },
  ligneIndice: { fontSize: typography.fontSize.xs, color: t.textSecondary, marginTop: 2 },
});
