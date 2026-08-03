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

export function ProfileScreen() {
  const styles = stylesFor(useThemeColors());

  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const { logout } = useAuth();

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
    backgroundColor: t.bg,
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
