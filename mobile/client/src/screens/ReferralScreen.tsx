import React from 'react';
import { View, Text, Share, Clipboard, StyleSheet, Alert } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { Screen, Button, KPICard, Skeleton } from '@/ui';
import { apiClient } from '@/api';
import { colors, spacing, typography, radius, shadows } from '@/theme';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useThemeColors } from '@/theme/useThemeColors';

// ── Types ──────────────────────────────────────────────────────────────────────

interface ReferralTier {
  name: string;
  min_referrals: number;
}

interface ReferralStats {
  total_referrals: number;
  total_earned: number;
  current_tier: ReferralTier | null;
  next_tier: ReferralTier | null;
}

interface ReferralShareData {
  code: string;
  message: string;
}

// ── API hooks ─────────────────────────────────────────────────────────────────

function useReferralStats() {
  return useQuery<ReferralStats>({
    queryKey: ['referral-stats'],
    queryFn: () => apiClient.get('/client/referral/stats').then(r => r.data.data as ReferralStats),
  });
}

function useReferralShareData() {
  return useQuery<ReferralShareData>({
    queryKey: ['referral-share'],
    queryFn: () => apiClient.get('/client/referral/my-code').then(r => r.data.data as ReferralShareData),
  });
}

// ── Sub-components ────────────────────────────────────────────────────────────

interface StepProps {
  number: number;
  text: string;
}

function Step({ number, text }: StepProps) {
  const styles = stylesFor(useThemeColors());

  return (
    <View style={styles.step} accessible accessibilityRole="text">
      <View style={styles.stepNumber}>
        <Text style={styles.stepNumberText}>{number}</Text>
      </View>
      <Text style={styles.stepText}>{text}</Text>
    </View>
  );
}

// ── Screen ────────────────────────────────────────────────────────────────────

export function ReferralScreen() {
  const styles = stylesFor(useThemeColors());

  const themeColors = useThemeColors();
  const { data: stats, isLoading: loadingStats } = useReferralStats();
  const { data: shareData, isLoading: loadingShare } = useReferralShareData();

  const handleShare = async () => {
    if (!shareData?.message) return;
    try {
      await Share.share({ message: shareData.message });
    } catch {
      // user dismissed — no-op
    }
  };

  const handleCopy = () => {
    if (!shareData?.code) return;
    Clipboard.setString(shareData.code);
    Alert.alert('Copié', 'Le code a été copié dans le presse-papier.');
  };

  const remainingForNext =
    stats?.next_tier != null && stats?.total_referrals != null
      ? stats.next_tier.min_referrals - stats.total_referrals
      : null;

  return (
    <Screen scroll>
      {/* Header */}
      <View style={styles.header}>
        <Text style={styles.title}>Parrainage</Text>
        <Text style={styles.subtitle}>Invitez vos amis et gagnez des récompenses</Text>
      </View>

      {/* Referral code card */}
      <View style={[styles.codeCard, { backgroundColor: themeColors.card }]}>
        <Text style={styles.codeLabel}>Votre code</Text>
        {loadingShare ? (
          <Skeleton width={160} height={32} />
        ) : (
          <Text style={styles.code} testID="referral-code">
            {shareData?.code ?? '—'}
          </Text>
        )}
        <View style={styles.codeActions}>
          <View style={styles.codeActionBtn}>
            <Button
              variant="amber"
              label="Partager"
              onPress={handleShare}
              disabled={loadingShare || !shareData?.code}
            />
          </View>
          <View style={styles.codeActionBtn}>
            <Button
              variant="outline"
              label="Copier"
              onPress={handleCopy}
              disabled={loadingShare || !shareData?.code}
            />
          </View>
        </View>
      </View>

      {/* Stats */}
      <View style={styles.statsGrid}>
        <KPICard
          title="Parrainages"
          value={stats?.total_referrals ?? 0}
          loading={loadingStats}
        />
        <KPICard
          title="Gains"
          value={`€${stats?.total_earned ?? 0}`}
          loading={loadingStats}
          tone="success"
        />
      </View>

      {/* Tier progress */}
      {stats?.current_tier != null && (
        <View style={[styles.tierCard, { backgroundColor: themeColors.card }]}>
          <Text style={styles.tierName}>{stats.current_tier.name}</Text>
          {remainingForNext != null && stats.next_tier != null && remainingForNext > 0 && (
            <Text style={styles.tierNext}>
              Encore {remainingForNext} parrainage(s) pour atteindre {stats.next_tier.name}
            </Text>
          )}
        </View>
      )}

      {/* How it works */}
      <View style={styles.howItWorks}>
        <Text style={styles.sectionTitle}>Comment ça marche</Text>
        <Step number={1} text="Partagez votre code avec vos amis" />
        <Step number={2} text="Ils obtiennent €15 de réduction sur leur 1er service" />
        <Step number={3} text="Vous recevez €10 de crédit quand ils réservent" />
      </View>
    </Screen>
  );
}

// ── Styles ────────────────────────────────────────────────────────────────────

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  header: {
    marginTop: spacing.md,
    marginBottom: spacing.lg,
  },
  title: {
    fontSize: typography.fontSize['2xl'],
    fontWeight: typography.fontWeight.bold,
    fontFamily: typography.fontFamily.display,
    color: t.text,
    marginBottom: spacing.xs,
  },
  subtitle: {
    fontSize: typography.fontSize.sm,
    color: t.textSecondary,
    fontFamily: typography.fontFamily.body,
    lineHeight: typography.fontSize.sm * typography.lineHeight.normal,
  },
  codeCard: {
    borderRadius: radius.lg,
    padding: spacing.lg,
    alignItems: 'center',
    marginBottom: spacing.md,
    ...shadows.soft,
  },
  codeLabel: {
    ...typography.preset.subhead,
    color: colors.brand[600],
    fontFamily: typography.fontFamily.bodyMedium,
    marginBottom: spacing.sm,
  },
  code: {
    fontSize: typography.fontSize['2xl'],
    fontWeight: typography.fontWeight.bold,
    fontFamily: typography.fontFamily.mono,
    color: colors.brand[700],
    letterSpacing: 3,
    marginBottom: spacing.md,
  },
  codeActions: {
    flexDirection: 'row',
    gap: spacing.sm,
    width: '100%',
  },
  codeActionBtn: {
    flex: 1,
  },
  statsGrid: {
    flexDirection: 'row',
    gap: spacing.sm,
    marginBottom: spacing.md,
  },
  tierCard: {
    borderRadius: radius.md,
    padding: spacing.md,
    marginBottom: spacing.md,
    ...shadows.xs,
    borderLeftWidth: 3,
    borderLeftColor: colors.accent.amber,
  },
  tierName: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    fontFamily: typography.fontFamily.bodySemiBold,
    color: t.text,
    marginBottom: spacing.xs,
  },
  tierNext: {
    fontSize: typography.fontSize.sm,
    color: t.textSecondary,
    fontFamily: typography.fontFamily.body,
  },
  howItWorks: {
    marginTop: spacing.md,
    marginBottom: spacing.xl,
  },
  sectionTitle: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.semibold,
    fontFamily: typography.fontFamily.bodySemiBold,
    color: t.text,
    marginBottom: spacing.md,
  },
  step: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    marginBottom: spacing.sm,
    gap: spacing.sm,
  },
  stepNumber: {
    width: 28,
    height: 28,
    borderRadius: radius.pill,
    backgroundColor: colors.brand[500],
    alignItems: 'center',
    justifyContent: 'center',
    flexShrink: 0,
  },
  stepNumberText: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.bold,
    fontFamily: typography.fontFamily.bodyBold,
    color: t.card,
  },
  stepText: {
    flex: 1,
    fontSize: typography.fontSize.sm,
    color: t.text,
    fontFamily: typography.fontFamily.body,
    lineHeight: typography.fontSize.sm * typography.lineHeight.relaxed,
    paddingTop: 4,
  },
});
