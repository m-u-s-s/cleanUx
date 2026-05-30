import React, { useCallback, useEffect, useState } from 'react';
import { FlatList, View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import { Screen, Badge, Skeleton, EmptyState, ErrorState } from '@/ui';
import { fetchInvoices, type Invoice } from '@/finance/useInvoices';
import { colors, spacing, typography, radius } from '@/theme';

type Status = 'all' | 'issued' | 'partial' | 'paid' | 'overdue';

const STATUS_FILTERS: { label: string; value: Status }[] = [
  { label: 'Tous', value: 'all' },
  { label: 'Émis', value: 'issued' },
  { label: 'Partiel', value: 'partial' },
  { label: 'Payé', value: 'paid' },
  { label: 'En retard', value: 'overdue' },
];

const statusVariant: Record<string, 'success' | 'warning' | 'danger' | 'neutral' | 'brand'> = {
  paid: 'success',
  partial: 'warning',
  overdue: 'danger',
  issued: 'brand',
};

interface InvoicesScreenProps {
  navigation: any;
}

export function InvoicesScreen({ navigation }: InvoicesScreenProps) {
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [status, setStatus] = useState<Status>('all');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);

  const load = useCallback(
    async (s: Status = status) => {
      setLoading(true);
      setError(false);
      try {
        const data = await fetchInvoices(s !== 'all' ? { status: s } : {});
        setInvoices(data);
      } catch {
        setError(true);
      } finally {
        setLoading(false);
      }
    },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [status],
  );

  useEffect(() => {
    load(status);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [status]);

  const handleFilterPress = useCallback((s: Status) => {
    setStatus(s);
  }, []);

  if (error) {
    return (
      <Screen>
        <ErrorState message="Impossible de charger vos factures." onRetry={() => load(status)} />
      </Screen>
    );
  }

  return (
    <Screen>
      <Text style={styles.title} accessibilityRole="header">
        Mes factures
      </Text>

      {/* Status filter strip */}
      <FlatList
        horizontal
        data={STATUS_FILTERS}
        keyExtractor={item => item.value}
        renderItem={({ item }) => (
          <TouchableOpacity
            onPress={() => handleFilterPress(item.value)}
            style={[
              styles.filterChip,
              item.value === status && styles.filterChipActive,
            ]}
          >
            <Text
              style={[
                styles.filterLabel,
                item.value === status && styles.filterLabelActive,
              ]}
            >
              {item.label}
            </Text>
          </TouchableOpacity>
        )}
        contentContainerStyle={styles.filterBar}
        showsHorizontalScrollIndicator={false}
        style={styles.filterList}
      />

      {loading ? (
        <View style={styles.skeletons}>
          {[1, 2, 3].map(i => (
            <Skeleton key={i} width="100%" height={80} />
          ))}
        </View>
      ) : (
        <FlatList
          data={invoices}
          keyExtractor={item => String(item.id)}
          renderItem={({ item }) => (
            <InvoiceRow invoice={item} onPress={() => navigation.navigate('InvoiceDetail', { id: item.id })} />
          )}
          contentContainerStyle={styles.list}
          accessibilityLabel="Liste des factures"
          ListEmptyComponent={
            <View testID="invoices-empty">
              <EmptyState
                title="Aucune facture"
                message="Vos factures apparaîtront ici une fois votre première réservation terminée."
                icon="receipt-outline"
              />
            </View>
          }
        />
      )}
    </Screen>
  );
}

interface InvoiceRowProps {
  invoice: Invoice;
  onPress: () => void;
}

const InvoiceRow = React.memo(function InvoiceRow({ invoice, onPress }: InvoiceRowProps) {
  const variant = statusVariant[invoice.effective_status] ?? 'neutral';

  const formattedAmount = invoice.currency === 'EUR'
    ? `€${invoice.amount}`
    : `${invoice.amount} ${invoice.currency}`;

  const balanceDue = invoice.balance_due != null && invoice.balance_due > 0
    ? invoice.currency === 'EUR'
      ? `Solde dû : €${invoice.balance_due}`
      : `Solde dû : ${invoice.balance_due} ${invoice.currency}`
    : null;

  return (
    <TouchableOpacity onPress={onPress} activeOpacity={0.7}>
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Text style={styles.invoiceNumber}>{invoice.number}</Text>
          <Badge label={invoice.effective_status} variant={variant} />
        </View>
        <View style={styles.cardMeta}>
          <Text style={styles.amount}>{formattedAmount}</Text>
          {balanceDue && <Text style={styles.balanceDue}>{balanceDue}</Text>}
        </View>
        {invoice.due_at && (
          <Text style={styles.dueAt}>Échéance : {invoice.due_at}</Text>
        )}
      </View>
    </TouchableOpacity>
  );
});

const styles = StyleSheet.create({
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
    marginBottom: spacing.sm,
  },
  filterList: {
    marginBottom: spacing.sm,
  },
  filterBar: {
    gap: spacing.xs,
    paddingRight: spacing.md,
  },
  filterChip: {
    paddingHorizontal: spacing.sm,
    paddingVertical: 6,
    borderRadius: radius.pill,
    backgroundColor: colors.surface[100],
    borderWidth: 1,
    borderColor: colors.surface[200],
    marginRight: spacing.xs,
  },
  filterChipActive: {
    backgroundColor: colors.brand[100],
    borderColor: colors.brand[300],
  },
  filterLabel: {
    fontSize: typography.fontSize.sm,
    color: colors.surface[600],
  },
  filterLabelActive: {
    color: colors.brand[700],
    fontWeight: typography.fontWeight.semibold,
  },
  skeletons: { gap: spacing.sm },
  list: { gap: spacing.sm, paddingBottom: spacing.xl },
  card: {
    backgroundColor: '#fff',
    borderRadius: radius.md,
    padding: spacing.md,
    borderWidth: 1,
    borderColor: colors.surface[100],
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  invoiceNumber: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[900],
  },
  cardMeta: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    marginTop: spacing.xs,
  },
  amount: {
    fontSize: typography.fontSize.sm,
    color: colors.surface[700],
    fontWeight: typography.fontWeight.medium,
  },
  balanceDue: {
    fontSize: typography.fontSize.xs,
    color: colors.danger[600],
  },
  dueAt: {
    fontSize: typography.fontSize.xs,
    color: colors.surface[400],
    marginTop: 2,
  },
});
