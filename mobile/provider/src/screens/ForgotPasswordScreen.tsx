import React, { useState } from 'react';
import { View, Text, Alert, StyleSheet } from 'react-native';
import { useMutation } from '@tanstack/react-query';
import { Button, TextInput } from '@/ui';
import { apiClient, ApiError } from '@/api';
import { colors, spacing, typography } from '@/theme';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';

type Props = NativeStackScreenProps<any, 'ForgotPassword'>;

export function ForgotPasswordScreen({ navigation }: Props) {
  const [email, setEmail] = useState('');
  const reset = useMutation<void, ApiError, string>({
    mutationFn: async (emailArg) => { await apiClient.post('/auth/forgot-password', { email: emailArg }); },
  });

  const handleSubmit = async () => {
    if (!email) { Alert.alert('Erreur', 'Veuillez entrer votre email.'); return; }
    try {
      await reset.mutateAsync(email);
      Alert.alert('Email envoyé', 'Si ce compte existe, un lien de réinitialisation a été envoyé.', [
        { text: 'OK', onPress: () => navigation.goBack() },
      ]);
    } catch (e: any) {
      Alert.alert('Erreur', e.message ?? "Impossible d'envoyer le lien.");
    }
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Mot de passe oublié</Text>
      <Text style={styles.subtitle}>Entrez votre email pour recevoir un lien de réinitialisation</Text>
      <TextInput label="Email" value={email} onChangeText={setEmail} keyboardType="email-address" autoCapitalize="none" placeholder="votre@email.com" autoFocus returnKeyType="done" onSubmitEditing={handleSubmit} />
      <Button label="Envoyer le lien" onPress={handleSubmit} fullWidth size="lg" loading={reset.isPending} />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, justifyContent: 'center', paddingHorizontal: spacing.lg, backgroundColor: colors.mode.showcase.night, gap: spacing.md },
  title: { fontSize: typography.fontSize.xl, fontWeight: typography.fontWeight.bold, color: colors.mode.showcase.text, textAlign: 'center' },
  subtitle: { fontSize: typography.fontSize.sm, color: colors.mode.showcase.muted, textAlign: 'center', marginBottom: spacing.md },
});
