import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { Button } from './Button';
import { Icon } from './Icon';
import { colors, spacing, typography } from '@/theme';

interface ErrorScreenProps {
  title?: string;
  message?: string;
  onRetry?: () => void;
  retryLabel?: string;
}

export function ErrorScreen({
  title = 'Une erreur est survenue',
  message = 'Vérifiez votre connexion et réessayez.',
  onRetry,
  retryLabel = 'Réessayer',
}: ErrorScreenProps) {
  return (
    <View style={styles.container}>
      <Icon name="alert-circle-outline" size={48} color={colors.danger[500]} />
      <Text style={styles.title}>{title}</Text>
      <Text style={styles.message}>{message}</Text>
      {onRetry && (
        <Button label={retryLabel} onPress={onRetry} variant="secondary" />
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: spacing.xl,
    paddingVertical: spacing['3xl'],
  },
  title: {
    ...typography.preset.headline,
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[900],
    marginBottom: spacing.xs,
    marginTop: spacing.sm,
    textAlign: 'center',
  },
  message: {
    ...typography.preset.bodyReadable,
    fontSize: typography.fontSize.sm,
    color: colors.surface[500],
    textAlign: 'center',
    marginBottom: spacing.md,
  },
});
