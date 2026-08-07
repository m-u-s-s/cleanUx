import React from 'react';
import { View, Text, ScrollView, StyleSheet, Alert } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Button, Divider } from '@/ui';
import { useAuth } from '@/auth';
import {typography, spacing, radius, shadows } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';
import { useSpacePreference } from '@/admin/useSpacePreference';

export function ProfileScreen() {
  const styles = stylesFor(useThemeColors());

  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const { user, logout } = useAuth();
  const { clear } = useSpacePreference();

  /*
   * LA PORTE DE SORTIE VERS LA CONSOLE D'ADMINISTRATION.
   *
   * `useSpacePreference` écrit que « le choix reste réversible depuis le profil : le retenir sans
   * porte de sortie enfermerait dans l'autre sens ». L'intention était juste, l'implémentation ne
   * l'était qu'à moitié : `clear()` n'existait que dans `AdminProfileScreen`. Un compte à double
   * casquette qui choisissait « prestataire » une fois ne pouvait PLUS JAMAIS revenir à la console
   * — ni ses quatre onglets, ni ses écrans, hors réinstallation. Ce bouton referme la boucle.
   *
   * Même condition que côté admin : proposé aux seuls comptes qui ont réellement les deux rôles.
   */
  const doubleCasquette = user?.is_admin === true && user?.is_provider === true;

  /*
   * `is_entreprise` NE VEUT PAS DIRE « appartient à une société » (corrigé le 2026-08-06).
   *
   * Côté serveur, `User::isEntreprise()` retourne `isClientCompany()` : le drapeau désigne une
   * société CLIENTE. Ma condition d'origine exigeait `is_entreprise === true` ET
   * `organization_type === 'provider_company'` — soit être une société cliente et prestataire à la
   * fois. Mutuellement exclusif : la section n'a JAMAIS pu s'afficher, pour personne.
   *
   * Le critère juste est celui qu'applique la garde web `EnsureOrganizationType` : avoir une
   * organisation courante, et qu'elle soit de type prestataire. `organization_type` n'est renseigné
   * QUE depuis `currentOrganization`, donc il porte déjà les deux informations.
   *
   * `HYBRID` EST INCLUS PARCE QUE LA GARDE WEB L'INCLUT DÉJÀ.
   *
   * `EnsureOrganizationType` avec `provider` appelle `OrganizationType::isProvider()`, qui rend
   * vrai pour `PROVIDER_COMPANY`, `PROVIDER_SOLO` et `HYBRID`. S'arrêter à `provider_company` ici
   * rendait donc le mobile PLUS ÉTROIT que la surface dont il prétend suivre la règle : une
   * organisation hybride ouvre l'espace société sur le web et se le voyait refuser dans
   * l'application.
   *
   * `provider_solo` reste volontairement dehors : la garde web l'admet, mais un indépendant à
   * structure légale n'a ni équipe à répartir ni canaux d'équipe — lui proposer ces écrans
   * remplirait son profil de surfaces vides. C'est une divergence ASSUMÉE, pas un oubli ; si elle
   * doit disparaître, c'est en décidant ce qu'un solo voit, pas en recopiant la garde.
   */
  const estMembreSocietePrestataire =
    user?.organization_type === 'provider_company' || user?.organization_type === 'hybrid';

  /*
   * Le pilotage de société OUVRE UN TROISIÈME ESPACE, donc une troisième façon de s'enfermer.
   *
   * Un gérant qui choisit « terrain » au sélecteur — parce qu'il nettoie lui-même ce matin-là —
   * n'avait plus aucun chemin de retour vers l'espace société : le bouton plus bas ne s'affichait
   * qu'aux comptes administrateur ET prestataire. C'est le défaut que `clear()` avait justement
   * corrigé pour la console d'administration, et qu'un troisième espace rejouait aussitôt.
   *
   * `main` n'a pas d'équivalent — ce troisième espace n'existe que sur cette branche — donc rien
   * ici n'entre en concurrence avec sa version.
   */
  const peutPiloterLaSociete = user?.can_manage_company === true;
  const plusieursEspaces = doubleCasquette || peutPiloterLaSociete;

  const actions: Array<{ label: string; screen: keyof RootStackParamList }> = [
    { label: 'Disponibilités', screen: 'Availability' },
    { label: 'Badges', screen: 'Badges' },
    { label: 'Vérification KYC', screen: 'KYC' },
    { label: 'Litiges', screen: 'ProviderDisputes' },
    { label: 'Avis reçus', screen: 'ProviderRatings' },
    { label: 'Messagerie', screen: 'ProviderChatList' },
    { label: 'Notifications', screen: 'ProviderNotifications' },
    { label: 'Préférences notifications', screen: 'NotificationPreferences' },
    { label: 'Langue', screen: 'Language' },
    { label: 'Apparence', screen: 'Appearance' },
  ];

  /**
   * L'espace société, entièrement NATIF.
   *
   * Ces cinq écrans consomment l'API `/provider/company/*`, créée avec eux : son absence était la
   * vraie raison de l'embarquement WebView, pas un choix d'interface.
   */
  const ECRANS_SOCIETE_NATIFS = [
    { label: 'Répartition', screen: 'CompanyDispatch' as const },
    { label: 'Équipe', screen: 'CompanyMembers' as const },
    { label: 'Équipes terrain', screen: 'CompanyFieldTeams' as const },
    { label: 'Sites desservis', screen: 'CompanySites' as const },
    { label: 'Tâches', screen: 'CompanyTasks' as const },
    { label: 'Canaux', screen: 'CompanyChannels' as const },
  ];


  return (
    <SafeAreaView style={styles.container} testID="profile-screen">
      <ScrollView contentContainerStyle={styles.content}>
        <Text style={styles.title}>Profil</Text>
        <View style={styles.grid}>
          {actions.map(({ label, screen }) => (
            <View key={screen} style={styles.buttonWrapper}>
              <Button
                label={label}
                variant="secondary"
                fullWidth
                onPress={() => navigation.navigate(screen as any)}
              />
            </View>
          ))}
          {/*
            ESPACE SOCIÉTÉ — écrans natifs.

            On ne les affiche qu'aux membres d'une société prestataire : les proposer à un
            indépendant donnerait des liens qui répondent 403 à qui les ouvre.
          */}
          {estMembreSocietePrestataire && (
            <>
              <Divider />
              {ECRANS_SOCIETE_NATIFS.map(({ label, screen }) => (
                <View key={screen} style={styles.buttonWrapper}>
                  <Button
                    label={label}
                    variant="secondary"
                    fullWidth
                    onPress={() => navigation.navigate(screen)}
                  />
                </View>
              ))}
            </>
          )}
          <Divider />
          <Button
            label="Conditions d'utilisation"
            onPress={() => navigation.navigate('Legal', { type: 'terms' })}
            variant="ghost"
            fullWidth
          />
          <Button
            label="Politique de confidentialité"
            onPress={() => navigation.navigate('Legal', { type: 'privacy' })}
            variant="ghost"
            fullWidth
          />
          {plusieursEspaces ? (
            <>
              <Divider />
              <Button
                label="Changer d’espace"
                onPress={() => void clear()}
                variant="secondary"
                fullWidth
              />
            </>
          ) : null}
          <Divider />
          <Button
            label="Se déconnecter"
            onPress={() =>
              Alert.alert('Déconnexion', 'Voulez-vous vous déconnecter ?', [
                { text: 'Annuler', style: 'cancel' },
                { text: 'Déconnexion', style: 'destructive', onPress: logout },
              ])
            }
            variant="danger"
            fullWidth
          />
        </View>
      </ScrollView>
    </SafeAreaView>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: t.page,
  },
  content: {
    padding: spacing.md,
  },
  title: {
    fontSize: typography.fontSize['2xl'],
    fontWeight: typography.fontWeight.bold,
    color: t.text,
    marginBottom: spacing.lg,
  },
  grid: {
    gap: spacing.sm,
  },
  buttonWrapper: {
    width: '100%',
  },
});
