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
          {doubleCasquette ? (
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
