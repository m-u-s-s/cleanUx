import React, { useState } from 'react';
import { View, Text, Alert, StyleSheet } from 'react-native';
import { useMutation } from '@tanstack/react-query';
import { Button, TextInput } from '@/ui';
import { apiClient, ApiError } from '@/api';
import { spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { fondDAuthentification } from '@/ui/authShell';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { messageDErreur } from '@brio/shared/format';
import { useTraduction } from '@/i18n';

type Props = NativeStackScreenProps<any, 'ForgotPassword'>;

export function ForgotPasswordScreen({ navigation }: Props) {
  const { t: tr } = useTraduction();
  const styles = feuille(useThemeColors());
  const [email, setEmail] = useState('');
  const reset = useMutation<void, ApiError, string>({
    mutationFn: async (emailArg) => { await apiClient.post('/auth/forgot-password', { email: emailArg }); },
  });

  const handleSubmit = async () => {
    if (!email) { Alert.alert(tr('forgot_password.erreur'), tr('forgot_password.veuillez_entrer_votre_email')); return; }
    try {
      await reset.mutateAsync(email);
      Alert.alert(tr('forgot_password.email_envoye'), tr('forgot_password.si_ce_compte_existe_un'), [
        { text: 'OK', onPress: () => navigation.goBack() },
      ]);
    } catch (e: any) {
      Alert.alert(tr('forgot_password.erreur'), messageDErreur(e, tr('forgot_password.impossible_d_envoyer_le_lien_2')));
    }
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>{tr('forgot_password.mot_de_passe_oublie')}</Text>
      <Text style={styles.subtitle}>{tr('forgot_password.entrez_votre_email_pour_recevoir')}</Text>
      <TextInput label={tr('forgot_password.email')} value={email} onChangeText={setEmail} keyboardType="email-address" autoCapitalize="none" placeholder={tr('auth.exemple_email')} autoFocus returnKeyType="done" onSubmitEditing={handleSubmit} />
      <Button label={tr('forgot_password.envoyer_le_lien')} onPress={handleSubmit} fullWidth size="lg" loading={reset.isPending} />
    </View>
  );
}

/*
   LA TROISIEME PORTE D'ENTREE, ACCORDEE AUX DEUX AUTRES.

   Cet ecran peignait `showcase.night` — un quasi-noir FIXE — pendant que la connexion et
   l'inscription suivent le theme. En clair, on passait donc d'un ecran clair a un ecran noir puis
   a un ecran clair, pour un meme parcours.
*/
const feuille = (t: ThemeTokens) => StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    paddingHorizontal: spacing.lg,
    backgroundColor: fondDAuthentification(t.isDark),
    gap: spacing.md,
  },
  title: { fontSize: typography.fontSize.xl, fontWeight: typography.fontWeight.bold, color: t.text, textAlign: 'center' },
  subtitle: { fontSize: typography.fontSize.sm, color: t.textSecondary, textAlign: 'center', marginBottom: spacing.md },
});
