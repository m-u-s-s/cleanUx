import React from 'react';
import { View, Text, StyleSheet, Alert } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Button, Divider } from '@/ui';
import { useAuth } from '@/auth';
import {spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';
import { useClientSpacePreference } from '@/company/useClientSpacePreference';

export function ProfileScreen() {
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
    <View style={styles.container}>
      <Text style={styles.title}>Profile</Text>
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
              label="Changer d’espace"
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
          label="Modules"
          onPress={() => navigation.navigate('Modules')}
          variant="secondary"
          fullWidth
        />
        <Button
          label="Modifier le profil"
          onPress={() => navigation.navigate('ProfileEdit')}
          variant="secondary"
          fullWidth
        />
        <Button
          label="Mes moyens de paiement"
          onPress={() => navigation.navigate('SavedPaymentMethods')}
          variant="secondary"
          fullWidth
        />
        <Button
          label="Messagerie"
          onPress={() => navigation.navigate('ChatList')}
          variant="secondary"
          fullWidth
        />
        <Button
          label="Notifications"
          onPress={() => navigation.navigate('Notifications')}
          variant="secondary"
          fullWidth
        />
        <Button
          label="Programme fidélité"
          onPress={() => navigation.navigate('Loyalty')}
          variant="secondary"
          fullWidth
        />
        <Button
          label="Parrainage"
          onPress={() => navigation.navigate('Referral')}
          variant="secondary"
          fullWidth
        />
        <Button
          label="Devis IA"
          onPress={() => navigation.navigate('AiQuote')}
          variant="secondary"
          fullWidth
        />
        <Button
          label="Mes litiges"
          onPress={() => navigation.navigate('Disputes')}
          variant="secondary"
          fullWidth
        />
        <Button
          label="Mes données (RGPD)"
          onPress={() => navigation.navigate('GDPR')}
          variant="secondary"
          fullWidth
        />
        <Button
          label="Donner mon avis"
          onPress={() => navigation.navigate('NPS')}
          variant="secondary"
          fullWidth
        />
        <Button
          label="Préférences notifications"
          onPress={() => navigation.navigate('NotificationPreferences')}
          variant="secondary"
          fullWidth
        />
        <Button
          label="Langue"
          onPress={() => navigation.navigate('Language')}
          variant="secondary"
          fullWidth
        />
        <Button
          label="Apparence"
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
          label="Politique de confidentialité"
          onPress={() => navigation.navigate('Legal', { type: 'privacy' })}
          variant="ghost"
          fullWidth
        />
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
    </View>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  container: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: t.page,
    padding: spacing.lg,
  },
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
    marginBottom: spacing.xl,
  },
  actions: {
    width: '100%',
    gap: spacing.sm,
  },
});
