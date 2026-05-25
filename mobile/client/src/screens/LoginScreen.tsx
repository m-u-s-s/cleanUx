import React, { useState } from 'react';
import { View, Text, Alert, KeyboardAvoidingView, Platform, ScrollView, TouchableOpacity, StyleSheet } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Button, TextInput, Divider } from '@/ui';
import { useLogin, useRegister, useAuth } from '@/auth';
import { colors, spacing, typography, radius } from '@/theme';
import type { RootStackParamList } from '@/navigation/types';

export function LoginScreen() {
  const [mode, setMode] = useState<'login' | 'register'>('login');

  return (
    <KeyboardAvoidingView style={styles.container} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={styles.scroll} keyboardShouldPersistTaps="handled">
        <View style={styles.header}>
          <Text style={styles.brand}>CleanUx</Text>
          <Text style={styles.subtitle}>{mode === 'login' ? 'Connectez-vous à votre compte' : 'Créez votre compte'}</Text>
        </View>

        {mode === 'login' ? <LoginForm /> : <RegisterForm />}

        <Divider label="ou" />

        <TouchableOpacity onPress={() => setMode(mode === 'login' ? 'register' : 'login')}>
          <Text style={styles.switchText}>
            {mode === 'login' ? "Pas encore de compte ? S'inscrire" : 'Déjà un compte ? Se connecter'}
          </Text>
        </TouchableOpacity>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

function LoginForm() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const login = useLogin();
  const { setUser } = useAuth();
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();

  const handleLogin = async () => {
    if (!email || !password) { Alert.alert('Erreur', 'Veuillez remplir tous les champs.'); return; }
    try {
      const result = await login.mutateAsync({ email, password });
      setUser(result.user);
    } catch (e: any) {
      Alert.alert('Erreur de connexion', e.errors?.email?.[0] ?? e.message ?? 'Identifiants incorrects.');
    }
  };

  return (
    <View style={styles.form}>
      <TextInput label="Email" value={email} onChangeText={setEmail} keyboardType="email-address" autoCapitalize="none" autoComplete="email" placeholder="votre@email.com" />
      <TextInput label="Mot de passe" value={password} onChangeText={setPassword} secureTextEntry placeholder="••••••••" />
      <TouchableOpacity onPress={() => navigation.navigate('ForgotPassword')}>
        <Text style={styles.forgotText}>Mot de passe oublié ?</Text>
      </TouchableOpacity>
      <Button label="Se connecter" onPress={handleLogin} fullWidth size="lg" loading={login.isPending} />
    </View>
  );
}

function RegisterForm() {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const register = useRegister();
  const { setUser } = useAuth();

  const handleRegister = async () => {
    if (!name || !email || !password) { Alert.alert('Erreur', 'Veuillez remplir les champs obligatoires.'); return; }
    if (password !== confirmPassword) { Alert.alert('Erreur', 'Les mots de passe ne correspondent pas.'); return; }
    try {
      const result = await register.mutateAsync({
        name, email, password, passwordConfirmation: confirmPassword,
        phone: phone || undefined, acceptTerms: true,
      });
      setUser(result.user);
    } catch (e: any) {
      const msg = e.errors ? Object.values(e.errors).flat().join('\n') : e.message;
      Alert.alert('Erreur', msg ?? "Impossible de créer le compte.");
    }
  };

  return (
    <View style={styles.form}>
      <TextInput label="Nom complet" value={name} onChangeText={setName} autoComplete="name" placeholder="Jean Dupont" />
      <TextInput label="Email" value={email} onChangeText={setEmail} keyboardType="email-address" autoCapitalize="none" autoComplete="email" placeholder="votre@email.com" />
      <TextInput label="Téléphone (optionnel)" value={phone} onChangeText={setPhone} keyboardType="phone-pad" placeholder="+32 470 12 34 56" />
      <TextInput label="Mot de passe" value={password} onChangeText={setPassword} secureTextEntry placeholder="Min. 8 caractères" />
      <TextInput label="Confirmer le mot de passe" value={confirmPassword} onChangeText={setConfirmPassword} secureTextEntry placeholder="••••••••" />
      <Button label="Créer mon compte" onPress={handleRegister} fullWidth size="lg" loading={register.isPending} />
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: colors.mode.showcase.night },
  scroll: { flexGrow: 1, justifyContent: 'center', paddingHorizontal: spacing.lg, paddingVertical: spacing.xl },
  header: { alignItems: 'center', marginBottom: spacing['2xl'] },
  brand: { fontSize: 36, fontWeight: '800' as const, color: colors.accent.amber, letterSpacing: 2 },
  subtitle: { fontSize: typography.fontSize.sm, color: colors.mode.showcase.muted, marginTop: spacing.sm },
  form: { gap: spacing.md, marginBottom: spacing.lg },
  switchText: { textAlign: 'center', color: colors.accent.amber, fontSize: typography.fontSize.sm, fontWeight: typography.fontWeight.medium, paddingVertical: spacing.md },
  forgotText: { color: colors.accent.cyan, fontSize: typography.fontSize.sm, textAlign: 'right', marginTop: -spacing.xs },
});
