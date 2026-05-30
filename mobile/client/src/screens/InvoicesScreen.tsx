import React, { useCallback, useEffect, useState } from 'react';
import {
  FlatList,
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ScrollView,
} from 'react-native';
import { Screen, Badge, Skeleton, EmptyState, ErrorState, KPICard } from '@/ui';
import {
  fetchInvoices,
  fetchInvoicesSummary,
  type Invoice,
  type InvoicesSummaryResponse,
} from '@/finance/useInvoices';
import { colors, spacing, typography, radius } from '@/theme';

// ─── Types ────────────────────────────────────────────────────────────────────

type StatusFilter =
  | 'all'
  | 'draft'
  | 'sent'
  | 'accepted'
  | 'issued'
  | 'partial'
  | 'paid'
  | 'overdue';

type SortOption = 'recent' | 'oldest' | 'amount_desc' | 'amount_asc';

// ─── Constants ────────────────────────────────────────────────────────────────

const STATUS_FILTERS: { label: string; value: StatusFilter }[] = [
  { label: 'Tous', value: 'all' },
  { label: 'Brouillon', value: 'draft' },
  { label: 'Envoyé', value: 'sent' },
  { label: 'Accepté', value: 'accepted' },
  { label: 'Émis', value: 'issued' },
  { label: 'Partiel', value: 'partial' },
  { label: 'Payé', value: 'paid' },
  { label: 'En retard', value: 'overdue' },
];

const SORT_OPTIONS: { label: string; value: SortOption }[] = [
  { label: 'Récent', value: 'recent' },
  { label: 'Ancien', value: 'oldest' },
  { label: 'Montant ↓', value: 'amount_desc' },
  { label: 'Montant ↑', value: 'amount_asc' },
];

const STATUS_VARIANT: Record<string, 'success' | 'warning' | 'danger' | 'neutral' | 'brand'> = {
  paid: 'success',
  partial: 'warning',
  overdue: 'danger',
  issued: 'brand',
  sent: 'brand',
  accepted: 'brand',
  draft: 'neutral',
};

const STATUS_LABEL: Record<string, string> = {
  all: 'Tous',
  draft: 'Brouillon',
  sent: 'Envoyé',
  accepted: 'Accepté',
  issued: 'Émis',
  partial: 'Partiel',
  paid: 'Payée',
  overdue: 'En retard',
};

const SORT_LABEL: Record<SortOption, string> = {
  recent: 'récent',
  oldest: 'ancien',
  amount_desc: 'montant ↓',
  amount_asc: 'montant ↑',
};

// Health tone mapping — the API may return 'rose' (from Tailwind palette) or standard tones
const HEALTH_TONE_MAP: Record<string, 'danger' | 'warning' | 'success' | 'neutral'> = {
  rose: 'danger',
  danger: 'danger',
  amber: 'warning',
  warning: 'warning',
  emerald: 'success',
  success: 'success',
};

const HEALTH_BG: Record<string, string> = {
  danger: colors.danger[50],
  warning: colors.warning[50],
  success: colors.success[50],
  neutral: colors.surface[100],
};

const HEALTH_TEXT: Record<string, string> = {
  danger: colors.danger[700],
  warning: colors.warning[700],
  success: colors.success[700],
  neutral: colors.surface[700],
};

// ─── Screen ───────────────────────────────────────────────────────────────────

interface InvoicesScreenProps {
  navigation: any;
}

export function InvoicesScreen({ navigation }: InvoicesScreenProps) {
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [status, setStatus] = useState<StatusFilter>('all');
  const [sort, setSort] = useState<SortOption>('recent');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);

  const [summary, setSummary] = useState<InvoicesSummaryResponse | null>(null);

  // ── Data fetching ──────────────────────────────────────────────────────────

  const loadList = useCallback(
    async (s: StatusFilter, srt: SortOption) => {
      setLoading(true);
      setError(false);
      try {
        const filters: { status?: string; sort?: string } = { sort: srt };
        if (s !== 'all') filters.status = s;
        const data = await fetchInvoices(filters);
        setInvoices(data);
      } catch {
        setError(true);
      } finally {
        setLoading(false);
      }
    },
    [],
  );

  const loadSummary = useCallback(async () => {
    try {
      const data = await fetchInvoicesSummary();
      setSummary(data);
    } catch {
      // Summary failure is non-blocking — degrade silently
    }
  }, []);

  useEffect(() => {
    // Parallel fetch on mount
    loadList(status, sort);
    loadSummary();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // ── Filter / sort handlers ─────────────────────────────────────────────────

  const handleFilterPress = useCallback(
    (s: StatusFilter) => {
      setStatus(s);
      loadList(s, sort);
    },
    [sort, loadList],
  );

  const handleSortPress = useCallback(
    (srt: SortOption) => {
      setSort(srt);
      loadList(status, srt);
    },
    [status, loadList],
  );

  const handleReset = useCallback(() => {
    const defaultStatus: StatusFilter = 'all';
    const defaultSort: SortOption = 'recent';
    setStatus(defaultStatus);
    setSort(defaultSort);
    loadList(defaultStatus, defaultSort);
  }, [loadList]);

  // ── Error state ────────────────────────────────────────────────────────────

  if (error) {
    return (
      <Screen>
        <ErrorState
          message="Impossible de charger vos factures."
          onRetry={() => loadList(status, sort)}
        />
      </Screen>
    );
  }

  // ── Active filter label ───────────────────────────────────────────────────

  const isFiltered = status !== 'all' || sort !== 'recent';
  const activeFilterParts: string[] = [];
  if (status !== 'all') activeFilterParts.push(`Statut : ${STATUS_LABEL[status] ?? status}`);
  if (sort !== 'recent') activeFilterParts.push(`Tri : ${SORT_LABEL[sort]}`);
  const activeFilterText = activeFilterParts.join(' · ');

  // ── ListHeaderComponent ────────────────────────────────────────────────────

  const ListHeader = (
    <View>
      <Text style={styles.title} accessibilityRole="header">
        Mes factures
      </Text>

      {/* 1 + 2. Summary KPI header + Payment health widget */}
      {summary && <SummaryBlock summary={summary} />}

      {/* 3. Latest payment events panel */}
      {summary && summary.latest_payment_events.length > 0 && (
        <LatestEventsPanel events={summary.latest_payment_events} />
      )}

      {/* 4. Sort control */}
      <View style={styles.sortRow}>
        {SORT_OPTIONS.map(opt => (
          <TouchableOpacity
            key={opt.value}
            testID={`sort-${opt.value}`}
            onPress={() => handleSortPress(opt.value)}
            style={[
              styles.sortChip,
              opt.value === sort && styles.sortChipActive,
            ]}
          >
            <Text
              style={[
                styles.sortLabel,
                opt.value === sort && styles.sortLabelActive,
              ]}
            >
              {opt.label}
            </Text>
          </TouchableOpacity>
        ))}
      </View>

      {/* 5. Status filter strip (8 filters) — horizontal ScrollView, not a nested FlatList */}
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={styles.filterBar}
        style={styles.filterList}
      >
        {STATUS_FILTERS.map(item => (
          <TouchableOpacity
            key={item.value}
            testID={`filter-chip-${item.value}`}
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
        ))}
      </ScrollView>

      {/* 6 + 7. Reset filters + active filter label */}
      <View style={styles.filterMeta}>
        {isFiltered && (
          <Text testID="active-filter-label" style={styles.activeFilterText}>
            {activeFilterText}
          </Text>
        )}
        <TouchableOpacity
          testID="reset-filters"
          onPress={handleReset}
          style={styles.resetBtn}
        >
          <Text style={styles.resetBtnText}>Réinitialiser</Text>
        </TouchableOpacity>
      </View>

      {/* Skeleton rows shown in header while loading */}
      {loading && (
        <View style={styles.skeletons}>
          {[1, 2, 3].map(i => (
            <Skeleton key={i} width="100%" height={80} />
          ))}
        </View>
      )}
    </View>
  );

  // ── Render ─────────────────────────────────────────────────────────────────

  return (
    <Screen>
      <FlatList
        data={loading ? [] : invoices}
        keyExtractor={item => String(item.id)}
        renderItem={({ item }) => (
          <InvoiceRow
            invoice={item}
            onPress={() => navigation.navigate('InvoiceDetail', { id: item.id })}
          />
        )}
        contentContainerStyle={styles.list}
        accessibilityLabel="Liste des factures"
        ListHeaderComponent={ListHeader}
        ListEmptyComponent={
          loading ? null : (
            <View testID="invoices-empty">
              <EmptyState
                title="Aucune facture"
                message="Vos factures apparaîtront ici une fois votre première réservation terminée."
                icon="receipt-outline"
              />
            </View>
          )
        }
      />
    </Screen>
  );
}

// ─── Sub-components ────────────────────────────────────────────────────────────

interface SummaryBlockProps {
  summary: InvoicesSummaryResponse;
}

function SummaryBlock({ summary: { summary, payment_health } }: SummaryBlockProps) {
  const tone = HEALTH_TONE_MAP[payment_health.tone] ?? 'neutral';
  const healthBg = HEALTH_BG[tone];
  const healthText = HEALTH_TEXT[tone];

  const { currency_symbol: sym, outstanding_total, next_due_at } = summary;

  const nextDueLabel = next_due_at
    ? `Prochaine échéance : ${next_due_at.substring(0, 10)}`
    : undefined;

  return (
    <View style={styles.summarySection}>
      {/* KPI cards row */}
      <View style={styles.kpiRow}>
        <KPICard
          title="Factures"
          value={summary.invoices_count}
        />
        <KPICard
          title="Payées"
          value={summary.paid_count}
          tone="success"
        />
        <KPICard
          title="En retard"
          value={summary.overdue_count}
          tone={summary.overdue_count > 0 ? 'danger' : 'neutral'}
        />
      </View>

      {/* Outstanding total */}
      <View style={styles.outstandingRow}>
        <Text style={styles.outstandingLabel}>Total impayé</Text>
        <Text style={styles.outstandingValue}>
          {sym}{outstanding_total}
        </Text>
        {nextDueLabel && (
          <Text style={styles.nextDueText}>{nextDueLabel}</Text>
        )}
      </View>

      {/* 2. Payment health widget */}
      <View style={[styles.healthWidget, { backgroundColor: healthBg }]}>
        <Text style={[styles.healthTitle, { color: healthText }]}>
          {payment_health.title}
        </Text>
        <Text style={[styles.healthMessage, { color: healthText }]}>
          {payment_health.message}
        </Text>
      </View>
    </View>
  );
}

interface LatestEventsPanelProps {
  events: InvoicesSummaryResponse['latest_payment_events'];
}

function LatestEventsPanel({ events }: LatestEventsPanelProps) {
  return (
    <View style={styles.eventsPanel}>
      <Text style={styles.eventsPanelTitle}>Derniers paiements</Text>
      {events.map(ev => (
        <View key={ev.id} style={styles.eventRow}>
          <Text style={styles.eventRef}>{ev.reference ?? `#${ev.id}`}</Text>
          <Text style={styles.eventAmount}>{ev.amount}</Text>
          <Text style={styles.eventDate}>
            {ev.date ? ev.date.substring(0, 10) : '—'}
          </Text>
          <Badge
            label={ev.status ?? 'unknown'}
            variant={STATUS_VARIANT[ev.status ?? ''] ?? 'neutral'}
          />
        </View>
      ))}
    </View>
  );
}

interface InvoiceRowProps {
  invoice: Invoice;
  onPress: () => void;
}

const InvoiceRow = React.memo(function InvoiceRow({ invoice, onPress }: InvoiceRowProps) {
  const variant = STATUS_VARIANT[invoice.effective_status] ?? 'neutral';

  const formattedAmount =
    invoice.currency === 'EUR'
      ? `€${invoice.amount}`
      : `${invoice.amount} ${invoice.currency}`;

  const balanceDue =
    invoice.balance_due != null && invoice.balance_due > 0
      ? invoice.currency === 'EUR'
        ? `Solde dû : €${invoice.balance_due}`
        : `Solde dû : ${invoice.balance_due} ${invoice.currency}`
      : null;

  const isOverdue = invoice.effective_status === 'overdue';

  // 8. service_name (fallback provided by API as 'Service non précisé')
  const serviceName = invoice.service_name ?? 'Service non précisé';

  return (
    <TouchableOpacity onPress={onPress} activeOpacity={0.7}>
      <View style={styles.card}>
        <View style={styles.cardHeader}>
          <Text style={styles.invoiceNumber}>{invoice.number}</Text>
          <Badge label={invoice.effective_status} variant={variant} />
        </View>

        {/* 8. Service name */}
        <Text style={styles.serviceName}>{serviceName}</Text>

        <View style={styles.cardMeta}>
          <Text style={styles.amount}>{formattedAmount}</Text>
          {balanceDue && <Text style={styles.balanceDue}>{balanceDue}</Text>}
        </View>

        {invoice.due_at && (
          <Text style={styles.dueAt}>Échéance : {invoice.due_at}</Text>
        )}

        {/* 9. Overdue warning label */}
        {isOverdue && (
          <View style={styles.overdueWarning}>
            <Text style={styles.overdueWarningText}>
              ⚠ Cette facture est en retard de paiement
            </Text>
          </View>
        )}
      </View>
    </TouchableOpacity>
  );
});

// ─── Styles ────────────────────────────────────────────────────────────────────

const styles = StyleSheet.create({
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
    marginBottom: spacing.sm,
  },

  // Summary section
  summarySection: {
    marginBottom: spacing.md,
  },
  kpiRow: {
    flexDirection: 'row',
    gap: spacing.sm,
    marginBottom: spacing.sm,
  },
  outstandingRow: {
    backgroundColor: colors.surface[100],
    borderRadius: radius.md,
    padding: spacing.md,
    marginBottom: spacing.sm,
  },
  outstandingLabel: {
    fontSize: typography.fontSize.xs,
    color: colors.surface[500],
    marginBottom: 2,
  },
  outstandingValue: {
    fontSize: typography.fontSize['2xl'],
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
    fontVariant: ['tabular-nums'],
  },
  nextDueText: {
    fontSize: typography.fontSize.xs,
    color: colors.surface[500],
    marginTop: 4,
  },

  // Health widget
  healthWidget: {
    borderRadius: radius.md,
    padding: spacing.md,
    marginBottom: spacing.sm,
  },
  healthTitle: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    marginBottom: 4,
  },
  healthMessage: {
    fontSize: typography.fontSize.sm,
  },

  // Latest events panel
  eventsPanel: {
    backgroundColor: colors.surface[50],
    borderRadius: radius.md,
    padding: spacing.md,
    marginBottom: spacing.md,
    borderWidth: 1,
    borderColor: colors.surface[200],
  },
  eventsPanelTitle: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[700],
    marginBottom: spacing.sm,
  },
  eventRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    marginBottom: spacing.xs,
  },
  eventRef: {
    flex: 1,
    fontSize: typography.fontSize.sm,
    color: colors.surface[800],
    fontWeight: typography.fontWeight.medium,
  },
  eventAmount: {
    fontSize: typography.fontSize.sm,
    color: colors.surface[700],
    fontVariant: ['tabular-nums'],
  },
  eventDate: {
    fontSize: typography.fontSize.xs,
    color: colors.surface[400],
  },

  // Sort control
  sortRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.xs,
    marginBottom: spacing.sm,
  },
  sortChip: {
    paddingHorizontal: spacing.sm,
    paddingVertical: 5,
    borderRadius: radius.pill,
    backgroundColor: colors.surface[100],
    borderWidth: 1,
    borderColor: colors.surface[200],
  },
  sortChipActive: {
    backgroundColor: colors.brand[100],
    borderColor: colors.brand[300],
  },
  sortLabel: {
    fontSize: typography.fontSize.xs,
    color: colors.surface[600],
  },
  sortLabelActive: {
    color: colors.brand[700],
    fontWeight: typography.fontWeight.semibold,
  },

  // Filter strip
  filterList: {
    marginBottom: spacing.xs,
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

  // Filter meta row (reset + active-filter label)
  filterMeta: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: spacing.sm,
    flexWrap: 'wrap',
    gap: spacing.xs,
  },
  activeFilterText: {
    flex: 1,
    fontSize: typography.fontSize.xs,
    color: colors.surface[500],
  },
  resetBtn: {
    paddingHorizontal: spacing.sm,
    paddingVertical: 4,
    borderRadius: radius.pill,
    borderWidth: 1,
    borderColor: colors.surface[300],
  },
  resetBtnText: {
    fontSize: typography.fontSize.xs,
    color: colors.surface[600],
  },

  // List
  skeletons: { gap: spacing.sm },
  list: { gap: spacing.sm, paddingBottom: spacing.xl },

  // Invoice card
  card: {
    backgroundColor: colors.surface[50],
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
  serviceName: {
    fontSize: typography.fontSize.xs,
    color: colors.surface[500],
    marginTop: 2,
    marginBottom: 4,
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

  // Overdue warning
  overdueWarning: {
    marginTop: spacing.xs,
    backgroundColor: colors.danger[50],
    borderRadius: radius.sm,
    padding: spacing.xs,
  },
  overdueWarningText: {
    fontSize: typography.fontSize.xs,
    color: colors.danger[700],
    fontWeight: typography.fontWeight.medium,
  },
});
