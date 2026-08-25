import React, { useCallback, useEffect, useState } from 'react';
/* Chemin direct plutot que le baril : des suites mockent les barils a la main,
   et un export neuf y manque sans que `tsc` bronche. */
import { formatMontant } from '@/format/money';
import { Alert, ScrollView, StyleSheet, Text, TouchableOpacity, View } from 'react-native';
import { Screen, Badge, Skeleton, ErrorState } from '@/ui';
import { fetchInvoice, invoicePdfUrl, type Invoice } from '@/finance/useInvoices';
import { colors, spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { apiClient } from '@/api';
import { secureStore } from '@/storage/secureStore';

const statusVariant: Record<string, 'success' | 'warning' | 'danger' | 'neutral' | 'brand'> = {
  paid: 'success',
  partial: 'warning',
  overdue: 'danger',
  issued: 'brand',
};

interface Props {
  route: any;
  navigation: any;
}

export function InvoiceDetailScreen({ route, navigation }: Props) {
  const styles = stylesFor(useThemeColors());

  const id: number = route.params.id;
  const [invoice, setInvoice] = useState<Invoice | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(false);
    fetchInvoice(id)
      .then((data) => {
        if (!cancelled) {
          setInvoice(data);
          navigation.setOptions({ title: data.number });
        }
      })
      .catch(() => {
        if (!cancelled) setError(true);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => { cancelled = true; };
  }, [id, navigation]);

  const onDownloadPdf = useCallback(async () => {
    if (!invoice) return;
    // Call synchronously so test assertions on invoicePdfUrl work before async paths
    const path = invoicePdfUrl(invoice.id);
    try {
      // Dynamic imports — Expo Go SDK 53+ gotcha: static imports can crash
      const { File, Paths } = await import('expo-file-system');
      const Sharing = await import('expo-sharing');
      const token = await secureStore.getToken();
      const base = (apiClient.defaults.baseURL ?? '').replace(/\/$/, '');
      const destination = new File(Paths.cache, `invoice-${invoice.id}.pdf`);
      const downloaded = await File.downloadFileAsync(
        `${base}${path}`,
        destination,
        {
          headers: token ? { Authorization: `Bearer ${token}` } : {},
          idempotent: true,
        },
      );
      if (await Sharing.isAvailableAsync()) {
        await Sharing.shareAsync(downloaded.uri);
      }
    } catch {
      // Graceful fallback — never crash the UI
      Alert.alert('PDF', 'Téléchargement indisponible. Réessayez.');
    }
  }, [invoice]);

  if (error) {
    return (
      <Screen>
        <ErrorState
          message="Impossible de charger cette facture."
          onRetry={() => {
            setError(false);
            setLoading(true);
            fetchInvoice(id)
              .then((data) => { setInvoice(data); navigation.setOptions({ title: data.number }); })
              .catch(() => setError(true))
              .finally(() => setLoading(false));
          }}
        />
      </Screen>
    );
  }

  if (loading) {
    return (
      <Screen>
        <View style={styles.skeletons}>
          <Skeleton width="60%" height={28} />
          <Skeleton width="100%" height={80} />
          <Skeleton width="100%" height={60} />
        </View>
      </Screen>
    );
  }

  if (!invoice) return null;

  const variant = statusVariant[invoice.effective_status] ?? 'neutral';
  const formattedAmount = formatMontant(invoice.amount, invoice.currency);
  const formattedBalance =
    invoice.balance_due != null && invoice.balance_due > 0
      ? formatMontant(invoice.balance_due, invoice.currency)
      : null;

  return (
    <Screen>
      <ScrollView contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        {/* Header */}
        <View style={styles.header}>
          <Text style={styles.number}>{invoice.number}</Text>
          <Badge label={invoice.effective_status} variant={variant} />
        </View>

        {/* Amount section */}
        <View style={styles.card}>
          <View style={styles.row}>
            <Text style={styles.label}>Montant total</Text>
            <Text style={styles.amount}>{formattedAmount}</Text>
          </View>
          {formattedBalance && (
            <View style={styles.row}>
              <Text style={styles.label}>Solde dû</Text>
              <Text style={[styles.amount, styles.balanceDue]}>{formattedBalance}</Text>
            </View>
          )}
          {invoice.issued_at && (
            <View style={styles.row}>
              <Text style={styles.label}>Émise le</Text>
              <Text style={styles.value}>{invoice.issued_at.slice(0, 10)}</Text>
            </View>
          )}
          {invoice.due_at && (
            <View style={styles.row}>
              <Text style={styles.label}>Échéance</Text>
              <Text style={styles.value}>{invoice.due_at.slice(0, 10)}</Text>
            </View>
          )}
        </View>

        {/* Payments */}
        {invoice.payments && invoice.payments.length > 0 && (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Paiements</Text>
            {invoice.payments.map((p) => (
              <View key={p.id} style={styles.listItem}>
                <Text style={styles.listItemLabel}>
                  {p.status ?? 'paiement'} —{' '}
                  {formatMontant(p.amount, invoice.currency)}
                </Text>
                {p.paid_at && (
                  <Text style={styles.listItemMeta}>{p.paid_at.slice(0, 10)}</Text>
                )}
              </View>
            ))}
          </View>
        )}

        {/* Reminders */}
        {invoice.reminders && invoice.reminders.length > 0 && (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Rappels envoyés</Text>
            {invoice.reminders.map((r) => (
              <View key={r.id} style={styles.listItem}>
                <Text style={styles.listItemLabel}>{r.type ?? 'rappel'}</Text>
                {r.sent_at && (
                  <Text style={styles.listItemMeta}>{r.sent_at.slice(0, 10)}</Text>
                )}
              </View>
            ))}
          </View>
        )}

        {/* PDF action */}
        <TouchableOpacity
          style={styles.pdfButton}
          onPress={onDownloadPdf}
          accessibilityLabel="Télécharger le PDF"
          accessibilityRole="button"
        >
          <Text style={styles.pdfButtonText}>Télécharger le PDF</Text>
        </TouchableOpacity>
      </ScrollView>
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  content: {
    padding: spacing.md,
    gap: spacing.md,
    paddingBottom: spacing.xl,
  },
  skeletons: {
    padding: spacing.md,
    gap: spacing.sm,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  number: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
  },
  card: {
    backgroundColor: t.card,
    borderRadius: radius.md,
    padding: spacing.md,
    borderWidth: 1,
    borderColor: t.inputBg,
    gap: spacing.xs,
  },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  label: {
    fontSize: typography.fontSize.sm,
    color: t.textSecondary,
  },
  value: {
    fontSize: typography.fontSize.sm,
    color: t.text,
  },
  amount: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
  },
  balanceDue: {
    color: colors.danger[600],
  },
  section: {
    gap: spacing.xs,
  },
  sectionTitle: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
    marginBottom: 2,
  },
  listItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: t.cardSubtle,
    borderRadius: radius.sm,
    paddingHorizontal: spacing.sm,
    paddingVertical: 6,
  },
  listItemLabel: {
    fontSize: typography.fontSize.sm,
    color: t.text,
  },
  listItemMeta: {
    fontSize: typography.fontSize.xs,
    color: t.textMuted,
  },
  pdfButton: {
    backgroundColor: colors.brand[600] ?? colors.brand[500] ?? '#4f46e5',
    borderRadius: radius.md,
    paddingVertical: spacing.sm,
    alignItems: 'center',
    marginTop: spacing.sm,
  },
  pdfButtonText: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: t.card,
  },
});
