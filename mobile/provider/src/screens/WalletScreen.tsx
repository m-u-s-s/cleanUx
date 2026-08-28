import React, { useState } from 'react';
/* Chemin direct plutot que le baril : des suites mockent les barils a la main. */
import { formatMontant } from '@/format/money';
import { View, Text, FlatList, StyleSheet, Alert, TextInput as RNTextInput } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Screen, KPICard, Badge, Button, Skeleton, Divider, EmptyState, ErrorState } from '@/ui';
import { useWalletBalance, useWalletTransactions, useWithdraw, useStripeConnectStatus } from '@/earnings';
import { ApiError } from '@/api';
import { colors, spacing, typography, radius, shadows, useThemeColors } from '@/theme';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';
import { useTraduction } from '@/i18n';

/**
 * CE QU'ON DIT QUAND LE PORTEFEUILLE NE RÉPOND PAS.
 *
 * Repris de `EarningsScreen`, que cet écran remplace dans l'onglet « Revenus ». Les endpoints
 * portefeuille refusent 403 quand le compte n'a pas de ligne `provider_profiles` — un `employe`
 * peut exister sans. Le dire vaut mieux qu'un échec générique, et surtout mieux que ce que cet
 * écran-ci faisait : afficher « 0.00 EUR » et « Aucune transaction » comme si la donnée était
 * vraie. Sur un écran d'argent, un zéro inventé est pire qu'une erreur.
 */
function messageDErreurPortefeuille(error: unknown): string {
  if (error instanceof ApiError && error.status === 403) {
    return "Ce compte n'a pas de profil prestataire, aucun portefeuille n'y est rattaché.";
  }

  return 'Impossible de charger vos revenus.';
}

export function WalletScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const themeColors = useThemeColors();
  const balanceQuery = useWalletBalance();
  const txQuery = useWalletTransactions();
  const { data: balance, isLoading: loadingBalance } = balanceQuery;
  const { data: transactions, isLoading: loadingTx } = txQuery;

  const reessayerLePortefeuille = React.useCallback(() => {
    void balanceQuery.refetch();
    void txQuery.refetch();
  }, [balanceQuery, txQuery]);
  const { data: stripe } = useStripeConnectStatus();
  const withdraw = useWithdraw();
  const [withdrawAmount, setWithdrawAmount] = useState('');
  const [showWithdraw, setShowWithdraw] = useState(false);

  const handleWithdraw = () => {
    const amount = parseFloat(withdrawAmount);
    if (isNaN(amount) || amount <= 0) {
      Alert.alert('Montant invalide', 'Veuillez saisir un montant valide.');
      return;
    }
    if (balance && amount > balance.available) {
      Alert.alert('Solde insuffisant', `Vous ne pouvez retirer que ${formatMontant(balance.available, balance.currency)}.`);
      return;
    }
    Alert.alert(
      'Confirmer le versement',
      `Virer ${formatMontant(amount, balance?.currency)} vers votre compte bancaire ?`,
      [
        { text: 'Annuler', style: 'cancel' },
        {
          text: 'Confirmer',
          onPress: () =>
            withdraw.mutate(
              { amount },
              {
                onSuccess: () => {
                  setWithdrawAmount('');
                  setShowWithdraw(false);
                  Alert.alert('Versement demandé', 'Votre demande a été soumise avec succès.');
                },
                onError: () => Alert.alert('Erreur', 'Impossible de traiter le versement.'),
              },
            ),
        },
      ],
    );
  };

  /*
   * Les deux requêtes passent la MÊME garde côté serveur : quand les deux tombent, le portefeuille
   * entier est indisponible et il ne reste rien d'honnête à afficher.
   */
  if (balanceQuery.isError && txQuery.isError) {
    return (
      <Screen testID="wallet-screen">
        <Text style={styles.title} accessibilityRole="header">
          Portefeuille
        </Text>
        <ErrorState
          message={messageDErreurPortefeuille(balanceQuery.error)}
          onRetry={reessayerLePortefeuille}
        />
      </Screen>
    );
  }

  return (
    <Screen scroll testID="wallet-screen">
      <Text style={styles.title} accessibilityRole="header">
        Portefeuille
      </Text>

      {!stripe?.onboarded && (
        <View style={styles.stripeBanner}>
          <Text style={styles.stripeBannerText}>
            {tr('wallet.configurez_stripe_connect_pour_recevoir')}
          </Text>
          <Button
            label="Configurer"
            onPress={() => navigation.navigate('StripeOnboarding')}
            size="sm"
          />
        </View>
      )}

      {/* Balance cards */}
      {loadingBalance ? (
        <View style={styles.kpiRow}>
          <Skeleton width="48%" height={80} />
          <Skeleton width="48%" height={80} />
        </View>
      ) : balanceQuery.isError ? (
        <ErrorState
          compact
          message="Solde indisponible."
          onRetry={() => void balanceQuery.refetch()}
        />
      ) : balance ? (
        <View style={styles.kpiRow}>
          <KPICard
            title="Disponible"
            value={`${balance.available.toFixed(2)} ${balance.currency}`}
            tone="success"
          />
          <KPICard
            title="En attente"
            value={`${balance.pending.toFixed(2)} ${balance.currency}`}
            tone="warning"
          />
        </View>
      ) : null}

      {/* Withdraw */}
      {stripe?.onboarded && (
        <View style={styles.withdrawSection}>
          {!showWithdraw ? (
            <Button
              label="Demander un versement"
              onPress={() => setShowWithdraw(true)}
              fullWidth
              variant="secondary"
            />
          ) : (
            <View style={[styles.withdrawCard, { backgroundColor: themeColors.card }]}>
              <Text style={styles.withdrawTitle}>{tr('wallet.montant_a_virer')}</Text>
              <RNTextInput
                style={styles.withdrawInput}
                value={withdrawAmount}
                onChangeText={setWithdrawAmount}
                keyboardType="decimal-pad"
                placeholder="0.00"
                placeholderTextColor={colors.surface[400]}
                accessibilityLabel="Montant du versement"
              />
              <View style={styles.withdrawActions}>
                <Button
                  label="Annuler"
                  onPress={() => {
                    setShowWithdraw(false);
                    setWithdrawAmount('');
                  }}
                  variant="ghost"
                  size="sm"
                />
                <Button
                  label="Confirmer"
                  onPress={handleWithdraw}
                  size="sm"
                  loading={withdraw.isPending}
                />
              </View>
            </View>
          )}
        </View>
      )}

      {/* Transactions */}
      <Text style={styles.sectionTitle} accessibilityRole="header">
        {tr('wallet.transactions_recentes')}
      </Text>
      {loadingTx ? (
        <Skeleton width="100%" height={200} />
      ) : txQuery.isError ? (
        <ErrorState
          compact
          message="Impossible de charger vos transactions."
          onRetry={() => void txQuery.refetch()}
        />
      ) : (
        <FlatList
          data={transactions ?? []}
          scrollEnabled={false}
          keyExtractor={(i) => String(i.id)}
          renderItem={({ item }) => (
            <View style={styles.txRow}>
              <View style={styles.txLeft}>
                <Text style={styles.txDesc}>{item.description}</Text>
                <Text style={styles.txDate}>
                  {new Date(item.created_at).toLocaleDateString('fr-FR')}
                </Text>
              </View>
              <Text
                style={[
                  styles.txAmount,
                  item.amount >= 0 ? styles.positive : styles.negative,
                ]}
              >
                {item.amount >= 0 ? '+' : ''}
                {item.amount.toFixed(2)} {item.currency}
              </Text>
            </View>
          )}
          ItemSeparatorComponent={() => <Divider />}
          ListEmptyComponent={
            <EmptyState title="Aucune transaction" message="Vos mouvements financiers apparaîtront ici." />
          }
        />
      )}
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
    marginBottom: spacing.md,
  },
  stripeBanner: {
    backgroundColor: t.tint.warning,
    borderRadius: radius.md,
    padding: spacing.md,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: spacing.md,
  },
  stripeBannerText: {
    fontSize: typography.fontSize.sm,
    color: t.warning,
    flex: 1,
    marginRight: spacing.sm,
  },
  kpiRow: { flexDirection: 'row', gap: spacing.sm, marginBottom: spacing.md },
  withdrawSection: { marginBottom: spacing.lg },
  withdrawCard: {
    borderRadius: radius.md,
    padding: spacing.md,
    ...shadows.xs,
  },
  withdrawTitle: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.medium,
    color: t.text,
    marginBottom: spacing.sm,
  },
  withdrawInput: {
    borderWidth: 1,
    borderColor: t.border,
    borderRadius: radius.md,
    padding: spacing.md,
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
    backgroundColor: t.card,
    marginBottom: spacing.sm,
    fontVariant: ['tabular-nums'],
  },
  withdrawActions: { flexDirection: 'row', justifyContent: 'flex-end', gap: spacing.sm },
  sectionTitle: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
    marginBottom: spacing.sm,
  },
  txRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: spacing.sm,
  },
  txLeft: { flex: 1, marginRight: spacing.sm },
  txDesc: { fontSize: typography.fontSize.sm, color: t.text },
  txDate: { fontSize: typography.fontSize.xs, color: t.textMuted, marginTop: 2 },
  txAmount: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    fontVariant: ['tabular-nums'],
  },
  positive: { color: t.success },
  negative: { color: t.danger },
});
