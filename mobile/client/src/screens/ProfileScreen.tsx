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

export function ProfileScreen() {
  const styles = stylesFor(useThemeColors());

  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const { user, logout } = useAuth();

  /*
   * LA PORTE DE L'ESPACE SOCIÉTÉ CLIENTE.
   *
   * On teste `organization_type`, PAS `is_entreprise`. Côté serveur, `User::isEntreprise()`
   * retourne `isClientCompany()` — un nom qui trompe : il décrit le type de COMPTE, pas
   * l'appartenance à une société. C'est en le prenant pour « appartient à une société » que j'ai
   * rendu la section prestataire invisible pour tout le monde.
   *
   * `organization_type` n'est renseigné QUE depuis `currentOrganization` : il porte donc à la fois
   * l'appartenance et le type, exactement comme la garde web `EnsureOrganizationType`.
   */
  const estMembreSocieteCliente = user?.organization_type === 'client_company';

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Profile</Text>
      <View style={styles.actions}>
        <Button
          label="Modifier le profil"
          onPress={() => navigation.navigate('ProfileEdit')}
          variant="secondary"
          fullWidth
        />
        {estMembreSocieteCliente ? (
          <>
            <Divider />
            <Button
              label="Mes locaux"
              onPress={() => navigation.navigate('CompanySites')}
              variant="secondary"
              fullWidth
            />
            <Button
              label="Demande multi-locaux"
              onPress={() => navigation.navigate('CompanyMultiSiteRequest')}
              variant="secondary"
              fullWidth
            />
            <Button
              label="Signatures sur place"
              onPress={() => navigation.navigate('CompanySigningAppointments')}
              variant="secondary"
              fullWidth
            />
            <Divider />
          </>
        ) : null}
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
