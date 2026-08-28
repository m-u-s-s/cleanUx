import React from 'react';
import { View, Text, StyleSheet, Alert } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Button, Divider, Screen } from '@/ui';
import { useAuth } from '@/auth';
import {spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';
import { useClientSpacePreference } from '@/company/useClientSpacePreference';
import { useTraduction } from '@/i18n';

export function ProfileScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const { user, logout } = useAuth();
  const { clear } = useClientSpacePreference();

  /*
   * LA PORTE VERS L'ESPACE SOCIÉTÉ CLIENTE.
   *
   * `is_entreprise` est LE drapeau qui convient ici, et c'est le seul : il est servi par
   * `User::isEntreprise()`, qui délègue à `isClientCompany()` — vrai pour une société cliente
   * (`client_company`) comme pour une organisation hybride, faux pour un particulier.
   *
   * On ne le combine PAS avec `organization_type`. C'est exactement l'erreur qui a rendu les cinq
   * écrans société de l'application prestataire inatteignables depuis leur livraison : la
   * conjonction `is_entreprise === true && organization_type === 'provider_company'` n'était
   * satisfiable par aucun compte, puisque le drapeau désigne précisément l'autre côté.
   */
  const appartientAUneSocieteCliente = user?.is_entreprise === true;

  return (
    /*
     * UN ÉCRAN QUI DÉFILE, ET QUI COMMENCE SOUS L'HORLOGE.
     *
     * Ce profil était le seul écran de l'application à se dessiner dans un `View` nu plutôt que
     * dans `Screen` : ni encart de sécurité, ni défilement. Or il aligne vingt et un boutons, bien
     * plus haut qu'un téléphone — et son conteneur les CENTRAIT (`justifyContent: 'center'`), ce
     * qui déborde des DEUX côtés à la fois.
     *
     * Relevé dans l'émulateur : le titre et les deux premiers boutons passaient derrière l'heure,
     * les trois derniers tombaient sous la barre d'onglets, et rien ne défilait. « Se déconnecter »
     * était l'un des trois : il n'existait aucun moyen de fermer sa session depuis l'application.
     */
    <Screen scroll>
      <Text style={styles.title}>{tr('profile.mon_profil')}</Text>
      <View style={styles.actions}>
        {/*
          ESPACE ENTREPRISE — en tête, parce qu'un responsable multi-sites ouvre son profil POUR
          cela. Les six modules `entreprise-client` étaient déclarés dans `config/parity.php`
          depuis longtemps sans qu'aucun soit joignable dans l'application.
        */}
        {appartientAUneSocieteCliente ? (
          <>
            {/*
              CHANGER D'ESPACE, ET NON « ALLER À ».

              L'espace société est désormais un espace à part entière — sa propre pile, ses propres
              onglets — choisi au démarrage et retenu. Ce bouton efface le choix pour reposer la
              question ; sans lui, un membre de société enfermé d'un côté n'aurait aucun retour.
              C'est le défaut que `clear()` a déjà corrigé deux fois dans ce dépôt : une fois pour
              la console d'administration, une fois pour l'espace société prestataire.
            */}
            <Button
              label={tr('profile.changer_despace')}
              onPress={() => void clear()}
              variant="primary"
              fullWidth
            />
            <Divider />
          </>
        ) : null}
        {/*
          LE RÉPERTOIRE, EN TÊTE. L'application exposait une poignée d'écrans et laissait le
          reste — 37 modules pour un client — inatteignable depuis le téléphone. Le catalogue vient
          du serveur, qui déduit le contexte du jeton : rien à conditionner ici.
        */}
        <Button
          label={tr('profile.modules')}
          onPress={() => navigation.navigate('Modules')}
          variant="secondary"
          fullWidth
        />
        <Button
          label={tr('profile.modifier_le_profil')}
          onPress={() => navigation.navigate('ProfileEdit')}
          variant="secondary"
          fullWidth
        />
        <Button
          label={tr('profile.mes_moyens_de_paiement')}
          onPress={() => navigation.navigate('SavedPaymentMethods')}
          variant="secondary"
          fullWidth
        />
        <Button
          label={tr('profile.messagerie')}
          onPress={() => navigation.navigate('ChatList')}
          variant="secondary"
          fullWidth
        />
        <Button
          label={tr('profile.notifications')}
          onPress={() => navigation.navigate('Notifications')}
          variant="secondary"
          fullWidth
        />
        {/*
          LES DEVIS REÇUS (E24). Une société qui envoie un devis à un client qui ne peut pas y
          répondre n'a rien envoyé : sans cette porte, l'écran serait orphelin — le mode d'échec
          documenté de ce dépôt.
        */}
        {/*
          LE CARNET DE LIEUX (E2). Sans cette porte, l'écran serait orphelin — le mode d'échec
          documenté de ce dépôt : `tsc` et Jest ne disent rien de la joignabilité d'un écran.
        */}
        <Button
          label={tr('profile.mes_lieux')}
          onPress={() => navigation.navigate('Places')}
          variant="secondary"
          fullWidth
        />
        <Button
          label={tr('profile.mon_budget')}
          onPress={() => navigation.navigate('Budget')}
          variant="secondary"
          fullWidth
        />
        <Button
          label={tr('profile.ma_protection')}
          onPress={() => navigation.navigate('Protection')}
          variant="secondary"
          fullWidth
        />
        <Button
          label={tr('profile.devis_recus')}
          onPress={() => navigation.navigate('ReceivedQuotes')}
          variant="secondary"
          fullWidth
        />
        <Button
          label={tr('profile.programme_fidelite')}
          onPress={() => navigation.navigate('Loyalty')}
          variant="secondary"
          fullWidth
        />
        <Button
          label={tr('profile.parrainage')}
          onPress={() => navigation.navigate('Referral')}
          variant="secondary"
          fullWidth
        />
        <Button
          label={tr('profile.devis_ia')}
          onPress={() => navigation.navigate('AiQuote')}
          variant="secondary"
          fullWidth
        />
        <Button
          label={tr('profile.mes_litiges')}
          onPress={() => navigation.navigate('Disputes')}
          variant="secondary"
          fullWidth
        />
        <Button
          label={tr('profile.mes_donnees_rgpd')}
          onPress={() => navigation.navigate('GDPR')}
          variant="secondary"
          fullWidth
        />
        <Button
          label={tr('profile.donner_mon_avis')}
          onPress={() => navigation.navigate('NPS')}
          variant="secondary"
          fullWidth
        />
        <Button
          label={tr('profile.preferences_notifications')}
          onPress={() => navigation.navigate('NotificationPreferences')}
          variant="secondary"
          fullWidth
        />
        <Button
          label={tr('profile.langue')}
          onPress={() => navigation.navigate('Language')}
          variant="secondary"
          fullWidth
        />
        <Button
          label={tr('profile.apparence')}
          onPress={() => navigation.navigate('Appearance')}
          variant="secondary"
          fullWidth
        />
        <Divider />
        <Button
          label="Conditions d'utilisation"
          onPress={() => navigation.navigate('Legal', { type: 'terms' })}
          variant="ghost"
          fullWidth
        />
        <Button
          label={tr('profile.politique_de_confidentialite')}
          onPress={() => navigation.navigate('Legal', { type: 'privacy' })}
          variant="ghost"
          fullWidth
        />
        <Divider />
        <Button
          label={tr('profile.se_deconnecter')}
          onPress={() =>
            Alert.alert(tr('profile.deconnexion'), tr('profile.voulez_vous_vous_deconnecter'), [
              { text: 'Annuler', style: 'cancel' },
              { text: tr('profile.deconnexion'), style: 'destructive', onPress: logout },
            ])
          }
          variant="danger"
          fullWidth
        />
      </View>
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
    marginTop: spacing.md,
    marginBottom: spacing.lg,
  },
  actions: {
    width: '100%',
    gap: spacing.sm,
  },
});
