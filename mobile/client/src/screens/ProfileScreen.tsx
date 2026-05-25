import React from 'react';
import { View, Text, StyleSheet, Alert } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Button, Divider } from '@/ui';
import { useAuth } from '@/auth';
import { colors, spacing, typography } from '@/theme';
import type { RootStackParamList } from '@/navigation/types';

export function ProfileScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const { logout } = useAuth();

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

const styles = StyleSheet.create({
  container: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.surface[50],
    padding: spacing.lg,
  },
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[900],
    marginBottom: spacing.xl,
  },
  actions: {
    width: '100%',
    gap: spacing.sm,
  },
});
