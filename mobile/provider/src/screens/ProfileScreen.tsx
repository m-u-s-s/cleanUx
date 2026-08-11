import React from 'react';
import { View, Text, ScrollView, StyleSheet, Alert } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Button, Divider } from '@/ui';
import { useAuth, can } from '@/auth';
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
    /*
     * LE RÉPERTOIRE, EN TÊTE. L'application exposait une poignée d'écrans et laissait le reste —
     * 29 modules pour un prestataire — inatteignable depuis le téléphone. Le catalogue vient du
     * serveur, qui déduit le contexte du jeton : cette entrée n'a donc pas à être conditionnée.
     */
    /*
     * LA SÉCURITÉ EN TÊTE, et pour tout le monde. Ce n'est pas un module d'espace société : un
     * indépendant seul chez un inconnu en a autant besoin qu'un salarié — davantage, même,
     * puisque personne ne l'attend au dépôt le soir.
     */
    { label: 'Sécurité', screen: 'Safety' },
    // E17 + E34 — elle se consulte le matin, en montant dans la voiture.
    { label: 'Ma journée', screen: 'DailyRoute' },
    { label: 'Modules', screen: 'Modules' },
    { label: 'Disponibilités', screen: 'Availability' },
    // Sans porte d'entrée, l'écran serait orphelin — le mode d'échec documenté de ce dépôt.
    { label: 'Métiers et zones', screen: 'TradesZones' },
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
   * Ces écrans consomment l'API `/provider/company/*`, créée avec eux : son absence était la vraie
   * raison de l'embarquement WebView, pas un choix d'interface.
   *
   * CHAQUE ENTRÉE PORTE LA CLÉ QUE SON API EXIGE. Appartenir à une société prestataire ne suffit
   * pas : `organization_type` vaut `provider_company` pour le nettoyeur comme pour le patron, si
   * bien que les six boutons s'affichaient à tout le monde — et quatre d'entre eux répondent 403
   * depuis que le lot 1 a posé les gardes. Les clés sont EXACTEMENT celles de `routes/api/provider.php`
   * et de `config/modules.php` : trois lectures de la même règle qui divergeraient rendraient le
   * menu menteur dans un sens ou dans l'autre.
   *
   * `permission: null` = ouvert à tout membre. Les tâches sont bornées dans la requête (chacun voit
   * les siennes) et les canaux par l'appartenance au canal : ce sont les deux écrans qu'un exécutant
   * ouvre légitimement.
   */
  const ECRANS_SOCIETE_NATIFS: Array<{
    label: string;
    screen: keyof RootStackParamList;
    permission: string | null;
  }> = [
    { label: 'Répartition', screen: 'CompanyDispatch', permission: 'missions.dispatch' },
    { label: 'Équipe', screen: 'CompanyMembers', permission: 'team.view' },
    { label: 'Équipes terrain', screen: 'CompanyFieldTeams', permission: 'team.view' },
    { label: 'Sites desservis', screen: 'CompanySites', permission: 'sites.view_all' },
    { label: 'Implantations', screen: 'CompanyAgencies', permission: 'agencies.view' },
    { label: 'Rôles et permissions', screen: 'CompanyRolePermissions', permission: 'members.manage_permissions' },
    { label: 'Planning et absences', screen: 'CompanyPlanning', permission: 'team.view' },
    { label: 'Heures et rentabilité', screen: 'CompanyTimesheets', permission: 'team.view' },
    /*
     * `inventory.view` VA JUSQU'AUX EXÉCUTANTS, et c'est délibéré : savoir ce qui reste avant de
     * partir n'est pas commander. Aligner cette entrée sur `inventory.manage` fermerait l'écran à
     * ceux qui en ont le plus besoin — ceux qui sont devant la camionnette.
     */
    { label: 'Consommables', screen: 'CompanyInventory', permission: 'inventory.view' },
    { label: 'Devis', screen: 'CompanyQuotes', permission: 'quotes.view' },
    { label: 'Recrutement', screen: 'CompanyRecruitment', permission: 'recruitment.view' },
    /*
     * DEUX PORTES POUR UN ÉCRAN. Il s'ouvre par `missions.quality` OU par `fleet.view` : le
     * répertoire annonce la seconde, la plus large des deux. Exiger les deux le fermerait à
     * chacun des deux rôles qu'il sert.
     */
    { label: 'Qualité et matériel', screen: 'CompanyQualityFleet', permission: 'fleet.view' },
    { label: 'Tâches', screen: 'CompanyTasks', permission: null },
    { label: 'Canaux', screen: 'CompanyChannels', permission: null },
  ];

  const ecransSocieteAutorises = ECRANS_SOCIETE_NATIFS.filter(
    ({ permission }) => permission === null || can(user, permission),
  );


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

            Deux conditions, et il en manquait une. Appartenir à une société prestataire ouvre la
            section ; c'est la PERMISSION de chaque entrée qui décide de son bouton. Sans elle, un
            nettoyeur voyait Répartition, Équipe, Équipes terrain et Sites desservis — quatre liens
            qui répondent 403 depuis le lot 1. Un bouton qui échoue est pire qu'un bouton absent.
          */}
          {estMembreSocietePrestataire && ecransSocieteAutorises.length > 0 && (
            <>
              <Divider />
              {ecransSocieteAutorises.map(({ label, screen }) => (
                <View key={screen} style={styles.buttonWrapper}>
                  <Button
                    label={label}
                    variant="secondary"
                    fullWidth
                    onPress={() => navigation.navigate(screen as any)}
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
