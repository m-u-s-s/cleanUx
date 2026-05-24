import React from 'react';
import { View, Text, Share, StyleSheet } from 'react-native';
import { Screen, Button } from '@/ui';
import { useAuth } from '@/auth';
import { colors, spacing, typography, radius, shadows } from '@/theme';

export function ReferralScreen() {
  const { user } = useAuth();
  const referralCode = `CLEANUX-${user?.id ?? ''}`;
  const referralLink = `https://app.cleanux.com/referral/${referralCode}`;

  const handleShare = () => {
    Share.share({
      message: `Rejoignez CleanUx avec mon code parrainage ${referralCode} ! ${referralLink}`,
      url: referralLink,
    });
  };

  return (
    <Screen>
      <Text style={styles.title}>Parrainage</Text>
      <Text style={styles.subtitle}>
        Partagez votre code et gagnez des points fidélité pour chaque filleul inscrit !
      </Text>
      <View style={styles.codeCard}>
        <Text style={styles.codeLabel}>Votre code</Text>
        <Text style={styles.code}>{referralCode}</Text>
      </View>
      <Button label="Partager mon lien" onPress={handleShare} fullWidth size="lg" />
    </Screen>
  );
}

const styles = StyleSheet.create({
  title: {
    fontSize: typography.fontSize['2xl'],
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
    marginTop: spacing.md,
    marginBottom: spacing.xs,
  },
  subtitle: {
    fontSize: typography.fontSize.sm,
    color: colors.surface[500],
    marginBottom: spacing.lg,
  },
  codeCard: {
    backgroundColor: colors.brand[50],
    borderRadius: radius.md,
    padding: spacing.lg,
    alignItems: 'center',
    marginBottom: spacing.lg,
    ...shadows.soft,
  },
  codeLabel: { fontSize: typography.fontSize.xs, color: colors.brand[600], marginBottom: spacing.xs },
  code: {
    fontSize: typography.fontSize['2xl'],
    fontWeight: typography.fontWeight.bold,
    color: colors.brand[700],
    letterSpacing: 2,
  },
});
