import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { colors, spacing, typography, radius } from '@/theme';

interface ProgressBarProps { step: number; totalSteps: number; }

export function ProgressBar({ step, totalSteps }: ProgressBarProps) {
  const progress = (step / totalSteps) * 100;
  return (
    <View style={styles.container}>
      <View style={styles.barBg}>
        <View style={[styles.barFill, { width: `${progress}%` }]} />
      </View>
      <Text style={styles.label}>Étape {step} sur {totalSteps}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { paddingHorizontal: spacing.md, paddingTop: spacing.sm, paddingBottom: spacing.xs },
  barBg: { height: 4, backgroundColor: colors.surface[200], borderRadius: radius.pill, overflow: 'hidden' },
  barFill: { height: '100%', backgroundColor: colors.brand[500], borderRadius: radius.pill },
  label: { fontSize: typography.fontSize.xs, color: colors.surface[500], textAlign: 'center', marginTop: spacing.xs },
});
