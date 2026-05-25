import React, { useState } from 'react';
import {
  View,
  Text,
  Alert,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  TouchableOpacity,
  StyleSheet,
} from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Button, TextInput, Divider } from '@/ui';
import { useLogin, useRegister, useAuth } from '@/auth';
import { colors, spacing, typography } from '@/theme';
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
  const [errors, setErrors] = useState<{ email?: string; password?: string }>({});
  const login = useLogin();
  const { setUser } = useAuth();
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();

  const validate = () => {
    const e: typeof errors = {};
    if (!email) e.email = 'Email requis';
    else if (!email.includes('@')) e.email = 'Email invalide';
    if (!password) e.password = 'Mot de passe requis';
    else if (password.length < 6) e.password = 'Min. 6 caractères';
    setErrors(e);
    return Object.keys(e).length === 0;
  };

  const handleLogin = async () => {
    if (!validate()) return;
    try {
      const result = await login.mutateAsync({ email, password });
      setUser(result.user);
    } catch (e: any) {
      if (e.errors) {
        setErrors({ email: e.errors.email?.[0], password: e.errors.password?.[0] });
      } else {
        setErrors({ email: e.message ?? 'Identifiants incorrects.' });
      }
    }
  };

  return (
    <View style={styles.form}>
      <TextInput
        label="Email"
        value={email}
        onChangeText={(t) => { setEmail(t); setErrors(prev => ({ ...prev, email: undefined })); }}
        error={errors.email}
        keyboardType="email-address"
        autoCapitalize="none"
        autoComplete="email"
        placeholder="votre@email.com"
      />
      <TextInput
        label="Mot de passe"
        value={password}
        onChangeText={(t) => { setPassword(t); setErrors(prev => ({ ...prev, password: undefined })); }}
        error={errors.password}
        secureTextEntry
        placeholder="••••••••"
      />
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
  const [acceptTerms, setAcceptTerms] = useState(false);
  const [errors, setErrors] = useState<{
    name?: string;
    email?: string;
    password?: string;
    confirmPassword?: string;
    acceptTerms?: string;
  }>({});
  const register = useRegister();
  const { setUser } = useAuth();
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();

  const validate = () => {
    const e: typeof errors = {};
    if (!name.trim()) e.name = 'Nom requis';
    if (!email) e.email = 'Email requis';
    else if (!email.includes('@')) e.email = 'Email invalide';
    if (!password) e.password = 'Mot de passe requis';
    else if (password.length < 8) e.password = 'Min. 8 caractères';
    if (!confirmPassword) e.confirmPassword = 'Confirmation requise';
    else if (password !== confirmPassword) e.confirmPassword = 'Les mots de passe ne correspondent pas';
    if (!acceptTerms) e.acceptTerms = 'Vous devez accepter les CGU';
    setErrors(e);
    return Object.keys(e).length === 0;
  };

  const handleRegister = async () => {
    if (!validate()) return;
    try {
      const result = await register.mutateAsync({
        name,
        email,
        password,
        passwordConfirmation: confirmPassword,
        phone: phone || undefined,
        acceptTerms: true,
      });
      setUser(result.user);
    } catch (e: any) {
      if (e.errors) {
        setErrors({
          name: e.errors.name?.[0],
          email: e.errors.email?.[0],
          password: e.errors.password?.[0],
          confirmPassword: e.errors.password_confirmation?.[0],
        });
      } else {
        setErrors({ email: e.message ?? "Impossible de créer le compte." });
      }
    }
  };

  return (
    <View style={styles.form}>
      <TextInput
        label="Nom complet"
        value={name}
        onChangeText={(t) => { setName(t); setErrors(prev => ({ ...prev, name: undefined })); }}
        error={errors.name}
        autoComplete="name"
        placeholder="Jean Dupont"
      />
      <TextInput
        label="Email"
        value={email}
        onChangeText={(t) => { setEmail(t); setErrors(prev => ({ ...prev, email: undefined })); }}
        error={errors.email}
        keyboardType="email-address"
        autoCapitalize="none"
        autoComplete="email"
        placeholder="votre@email.com"
      />
      <TextInput
        label="Téléphone (optionnel)"
        value={phone}
        onChangeText={setPhone}
        keyboardType="phone-pad"
        placeholder="+32 470 12 34 56"
      />
      <TextInput
        label="Mot de passe"
        value={password}
        onChangeText={(t) => { setPassword(t); setErrors(prev => ({ ...prev, password: undefined })); }}
        error={errors.password}
        secureTextEntry
        placeholder="Min. 8 caractères"
      />
      <TextInput
        label="Confirmer le mot de passe"
        value={confirmPassword}
        onChangeText={(t) => { setConfirmPassword(t); setErrors(prev => ({ ...prev, confirmPassword: undefined })); }}
        error={errors.confirmPassword}
        secureTextEntry
        placeholder="••••••••"
      />
      <TouchableOpacity
        style={styles.termsRow}
        onPress={() => { setAcceptTerms(v => !v); setErrors(prev => ({ ...prev, acceptTerms: undefined })); }}
      >
        <View style={[styles.checkbox, acceptTerms && styles.checkboxChecked]} />
        <Text style={styles.termsText}>
          J'accepte les{' '}
          <Text style={styles.termsLink} onPress={() => navigation.navigate('Legal', { type: 'terms' })}>
            Conditions d'utilisation
          </Text>
          {' '}et la{' '}
          <Text style={styles.termsLink} onPress={() => navigation.navigate('Legal', { type: 'privacy' })}>
            Politique de confidentialité
          </Text>
        </Text>
      </TouchableOpacity>
      {errors.acceptTerms ? <Text style={styles.errorText}>{errors.acceptTerms}</Text> : null}
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
  termsRow: { flexDirection: 'row', alignItems: 'flex-start', gap: spacing.sm },
  checkbox: { width: 20, height: 20, borderRadius: 4, borderWidth: 2, borderColor: colors.surface[400], marginTop: 2, flexShrink: 0 },
  checkboxChecked: { backgroundColor: colors.brand[500], borderColor: colors.brand[500] },
  termsText: { flex: 1, fontSize: typography.fontSize.sm, color: colors.surface[700] },
  termsLink: { color: colors.brand[500], textDecorationLine: 'underline' },
  errorText: { fontSize: typography.fontSize.xs, color: colors.danger[500], marginTop: -spacing.xs },
});
