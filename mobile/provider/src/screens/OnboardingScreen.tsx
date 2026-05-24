import React, { useState } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { useMutation } from '@tanstack/react-query';
import { Screen, Button, TextInput } from '@/ui';
import { apiClient } from '@/api';
import { colors, spacing, typography, radius, shadows } from '@/theme';

const STEPS = ['Profil', 'Documents', 'Confirmation'];

export function OnboardingScreen() {
  const [step, setStep] = useState(0);
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');

  const submit = useMutation({
    mutationFn: () => apiClient.post('/provider/onboarding/complete', { first_name: firstName, last_name: lastName }),
    onSuccess: () => setStep(2),
  });

  const progress = ((step + 1) / STEPS.length) * 100;

  return (
    <Screen scroll>
      <Text style={styles.title}>Onboarding</Text>

      {/* progress bar */}
      <View style={styles.progressTrack}>
        <View style={[styles.progressFill, { width: `${progress}%` as any }]} />
      </View>
      <View style={styles.stepsRow}>
        {STEPS.map((s, i) => (
          <Text key={s} style={[styles.stepLabel, i === step && styles.stepLabelActive]}>
            {s}
          </Text>
        ))}
      </View>

      {step === 0 && (
        <View style={styles.card}>
          <Text style={styles.sectionTitle}>Votre profil</Text>
          <TextInput label="Prénom" value={firstName} onChangeText={setFirstName} />
          <TextInput label="Nom" value={lastName} onChangeText={setLastName} />
          <Button
            label="Suivant"
            onPress={() => setStep(1)}
            disabled={!firstName.trim() || !lastName.trim()}
            fullWidth
          />
        </View>
      )}

      {step === 1 && (
        <View style={styles.card}>
          <Text style={styles.sectionTitle}>Documents requis</Text>
          <Text style={styles.info}>
            Uploadez votre pièce d'identité et votre attestation de compétences
            depuis votre profil ou via le module KYC.
          </Text>
          <Button label="Retour" variant="secondary" onPress={() => setStep(0)} fullWidth />
          <Button
            label="Soumettre"
            onPress={() => submit.mutate()}
            loading={submit.isPending}
            fullWidth
          />
        </View>
      )}

      {step === 2 && (
        <View style={styles.card}>
          <Text style={styles.successTitle}>Onboarding terminé !</Text>
          <Text style={styles.info}>
            Votre profil est en cours de vérification. Vous recevrez une
            notification dès que votre compte sera activé.
          </Text>
        </View>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
    marginBottom: spacing.md,
  },
  progressTrack: {
    height: 6,
    backgroundColor: colors.surface[200],
    borderRadius: radius.pill,
    marginBottom: spacing.xs,
    overflow: 'hidden',
  },
  progressFill: {
    height: 6,
    backgroundColor: colors.brand[500],
    borderRadius: radius.pill,
  },
  stepsRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: spacing.lg,
  },
  stepLabel: {
    fontSize: typography.fontSize.xs,
    color: colors.surface[400],
  },
  stepLabelActive: {
    color: colors.brand[600],
    fontWeight: typography.fontWeight.semibold,
  },
  card: {
    backgroundColor: '#fff',
    borderRadius: radius.md,
    padding: spacing.lg,
    ...shadows.soft,
    gap: spacing.md,
  },
  sectionTitle: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[900],
  },
  successTitle: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.bold,
    color: colors.success[500],
    textAlign: 'center',
  },
  info: {
    fontSize: typography.fontSize.sm,
    color: colors.surface[500],
    lineHeight: typography.fontSize.sm * typography.lineHeight.relaxed,
  },
});
