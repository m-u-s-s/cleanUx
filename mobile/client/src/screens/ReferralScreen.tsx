import React from 'react';
/* Chemin direct plutot que le baril : des suites mockent les barils a la main. */
import { formatMontant } from '@/format/money';
import { View, Text, Share, Clipboard, StyleSheet, Alert } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { Screen, Button, KPICard, Skeleton } from '@/ui';
import { apiClient } from '@/api';
import { colors, spacing, typography, radius, shadows } from '@/theme';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useThemeColors } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

// ── Types ──────────────────────────────────────────────────────────────────────

/*
 * LE CONTRAT DE CET ECRAN N'A JAMAIS CORRESPONDU A LA CHARGE UTILE.
 *
 * Six cles etaient inventees : `total_referrals`, `min_referrals`, un `stats` a plat, un
 * `code` et un `message`. Aucune n'existe. Les deux compteurs restaient a zero, le palier
 * ne s'affichait pas, la ligne de progression etait morte, le partage n'ouvrait rien et le
 * code ne se copiait pas — c'est-a-dire tout l'ecran sauf son titre.
 *
 * Les noms ci-dessous sont ceux de `ReferralService::getShareData()`.
 */
interface ReferralTier {
  name: string;
  /** Le nombre de filleuls QUALIFIES a partir duquel le palier est atteint. */
  threshold: number;
}

interface ReferralStats {
  referral_code: string;
  invite_url: string;
  /** Les montants promis viennent du serveur : ils etaient ecrits en dur dans le texte. */
  rewards: { referrer_amount: number; referee_amount: number; currency: string } | null;
  stats: {
    total_invited: number;
    total_signed_up: number;
    total_qualified: number;
    total_rewarded: number;
    total_earned: number;
  } | null;
  current_tier: ReferralTier | null;
  next_tier: ReferralTier | null;
}

interface ReferralShareData {
  referral_code: string;
  share_message: string;
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
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  const themeColors = useThemeColors();
  const { data: stats, isLoading: loadingStats } = useReferralStats();
  const { data: shareData, isLoading: loadingShare } = useReferralShareData();

  const handleShare = async () => {
    if (!shareData?.share_message) return;
    try {
      await Share.share({ message: shareData.share_message });
    } catch {
      // user dismissed — no-op
    }
  };

  const handleCopy = () => {
    if (!shareData?.referral_code) return;
    Clipboard.setString(shareData.referral_code);
    Alert.alert('Copié', 'Le code a été copié dans le presse-papier.');
  };

  /*
   * Le palier se decide sur les filleuls QUALIFIES — c'est le compte que le serveur compare
   * au seuil. Le prendre sur les invitations aurait promis un palier qu'on n'atteindrait pas.
   */
  const remainingForNext =
    stats?.next_tier != null && stats?.stats != null
      ? stats.next_tier.threshold - stats.stats.total_qualified
      : null;

  return (
    <Screen scroll>
      {/* Header */}
      <View style={styles.header}>
        <Text style={styles.title}>{tr('referral.parrainage')}</Text>
        <Text style={styles.subtitle}>{tr('referral.invitez_vos_amis_et_gagnez')}</Text>
      </View>

      {/* Referral code card */}
      <View style={[styles.codeCard, { backgroundColor: themeColors.card }]}>
        <Text style={styles.codeLabel}>{tr('referral.votre_code')}</Text>
        {loadingShare ? (
          <Skeleton width={160} height={32} />
        ) : (
          <Text style={styles.code} testID="referral-code">
            {shareData?.referral_code ?? '—'}
          </Text>
        )}
        <View style={styles.codeActions}>
          <View style={styles.codeActionBtn}>
            <Button
              variant="amber"
              label={tr('referral.partager')}
              onPress={handleShare}
              disabled={loadingShare || !shareData?.referral_code}
            />
          </View>
          <View style={styles.codeActionBtn}>
            <Button
              variant="outline"
              label={tr('referral.copier')}
              onPress={handleCopy}
              disabled={loadingShare || !shareData?.referral_code}
            />
          </View>
        </View>
      </View>

      {/* Stats */}
      <View style={styles.statsGrid}>
        <KPICard
          title={tr('referral.parrainages')}
          value={stats?.stats?.total_invited ?? 0}
          loading={loadingStats}
        />
        <KPICard
          title={tr('referral.gains')}
          value={formatMontant(stats?.stats?.total_earned ?? 0, stats?.rewards?.currency)}
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
        <Text style={styles.sectionTitle}>{tr('referral.comment_ca_marche')}</Text>
        <Step number={1} text="Partagez votre code avec vos amis" />
        {/*
          LES MONTANTS PROMIS VIENNENT DU SERVEUR.

          Ils etaient ecrits ici : « €15 », « €10 » — symbole devant, a l'anglaise, et surtout
          figes. Un administrateur qui change la recompense laissait l'application promettre
          l'ancienne. `rewards` porte les deux montants ET leur devise depuis toujours.
        */}
        <Step
          number={2}
          text={`Ils obtiennent ${recompense(stats?.rewards?.referee_amount, stats?.rewards?.currency)} de réduction sur leur 1er service`}
        />
        <Step
          number={3}
          text={`Vous recevez ${recompense(stats?.rewards?.referrer_amount, stats?.rewards?.currency)} de crédit quand ils réservent`}
        />
      </View>
    </Screen>
  );
}

/**
 * Un montant de recompense, ou une formule qui n'engage a rien.
 *
 * Tant que le serveur n'a pas repondu, promettre un chiffre reviendrait a reecrire en dur
 * ce qu'on vient d'en sortir.
 */
function recompense(montant?: number | null, devise?: string | null): string {
  return typeof montant === 'number' ? formatMontant(montant, devise) : 'une remise';
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
    color: t.brandText,
    fontFamily: typography.fontFamily.bodyMedium,
    marginBottom: spacing.sm,
  },
  code: {
    fontSize: typography.fontSize['2xl'],
    fontWeight: typography.fontWeight.bold,
    fontFamily: typography.fontFamily.mono,
    color: t.brandText,
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
