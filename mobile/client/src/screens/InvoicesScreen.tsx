import React, { useCallback, useEffect, useState } from 'react';
/* Chemin direct plutot que le baril : des suites mockent les barils a la main,
   et un export neuf y manque sans que `tsc` bronche. */
import { formatMontant } from '@/format/money';
import {
  FlatList,
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ScrollView,
} from 'react-native';
import { Screen, Badge, Skeleton, EmptyState, ErrorState, KPICard, TextInput } from '@/ui';
import {
  fetchInvoices,
  fetchInvoicesSummary,
  type Invoice,
  type InvoicesSummaryResponse,
} from '@/finance/useInvoices';
import { colors, spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

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
  { label: 'Émise', value: 'issued' },
  { label: 'Partiel', value: 'partial' },
  { label: 'Payée', value: 'paid' },
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
  issued: 'Émise',
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
  const styles = stylesFor(useThemeColors());

  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [status, setStatus] = useState<StatusFilter>('all');
  const [sort, setSort] = useState<SortOption>('recent');
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);

  const [summary, setSummary] = useState<InvoicesSummaryResponse | null>(null);

  // ── Data fetching ──────────────────────────────────────────────────────────

  const loadList = useCallback(
    async (s: StatusFilter, srt: SortOption, srch: string) => {
      setLoading(true);
      setError(false);
      try {
        const filters: { status?: string; sort?: string; search?: string } = { sort: srt };
        if (s !== 'all') filters.status = s;
        if (srch !== '') filters.search = srch;
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
    loadList(status, sort, search);
    loadSummary();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // ── Filter / sort handlers ─────────────────────────────────────────────────

  const handleFilterPress = useCallback(
    (s: StatusFilter) => {
      setStatus(s);
      loadList(s, sort, search);
    },
    [sort, search, loadList],
  );

  const handleSortPress = useCallback(
    (srt: SortOption) => {
      setSort(srt);
      loadList(status, srt, search);
    },
    [status, search, loadList],
  );

  const handleReset = useCallback(() => {
    const defaultStatus: StatusFilter = 'all';
    const defaultSort: SortOption = 'recent';
    const defaultSearch = '';
    setStatus(defaultStatus);
    setSort(defaultSort);
    setSearch(defaultSearch);
    loadList(defaultStatus, defaultSort, defaultSearch);
  }, [loadList]);

  const handleSearchSubmit = useCallback(
    (text: string) => {
      loadList(status, sort, text);
    },
    [status, sort, loadList],
  );

  // ── Error state ────────────────────────────────────────────────────────────

  if (error) {
    return (
      <Screen>
        <ErrorState
          message="Impossible de charger vos factures."
          onRetry={() => loadList(status, sort, search)}
        />
      </Screen>
    );
  }

  // ── Active filter label ───────────────────────────────────────────────────

  const isFiltered = status !== 'all' || sort !== 'recent' || search !== '';
  const activeFilterParts: string[] = [];
  if (status !== 'all') activeFilterParts.push(`Statut : ${STATUS_LABEL[status] ?? status}`);
  if (sort !== 'recent') activeFilterParts.push(`Tri : ${SORT_LABEL[sort]}`);
  if (search !== '') activeFilterParts.push(`Recherche : ${search}`);
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

      {/* 4. Search input */}
      <View style={styles.searchRow}>
        <TextInput
          testID="invoices-search"
          label="Rechercher une facture…"
          value={search}
          onChangeText={setSearch}
          onSubmitEditing={e => handleSearchSubmit(e.nativeEvent.text)}
          returnKeyType="search"
          autoCorrect={false}
          autoCapitalize="none"
          clearButtonMode="while-editing"
        />
      </View>

      {/* 5. Sort control */}
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

      {/* 6. Status filter strip (8 filters) — horizontal ScrollView, not a nested FlatList */}
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

      {/* 7 + 8. Reset filters + active filter label */}
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
  const styles = stylesFor(useThemeColors());

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
          title="Partielles"
          value={summary.partial_count}
          tone={summary.partial_count > 0 ? 'warning' : 'neutral'}
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
  const styles = stylesFor(useThemeColors());

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
  const styles = stylesFor(useThemeColors());
  const variant = STATUS_VARIANT[invoice.effective_status] ?? 'neutral';

  const formattedAmount = formatMontant(invoice.amount, invoice.currency);

  const balanceDue =
    invoice.balance_due != null && invoice.balance_due > 0
      ? `Solde dû : ${formatMontant(invoice.balance_due, invoice.currency)}`
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

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
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
    backgroundColor: t.inputBg,
    borderRadius: radius.md,
    padding: spacing.md,
    marginBottom: spacing.sm,
  },
  outstandingLabel: {
    fontSize: typography.fontSize.xs,
    color: t.textSecondary,
    marginBottom: 2,
  },
  outstandingValue: {
    fontSize: typography.fontSize['2xl'],
    fontWeight: typography.fontWeight.bold,
    color: t.text,
    fontVariant: ['tabular-nums'],
  },
  nextDueText: {
    fontSize: typography.fontSize.xs,
    color: t.textSecondary,
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
    backgroundColor: t.cardSubtle,
    borderRadius: radius.md,
    padding: spacing.md,
    marginBottom: spacing.md,
    borderWidth: 1,
    borderColor: t.border,
  },
  eventsPanelTitle: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
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
    color: t.text,
    fontWeight: typography.fontWeight.medium,
  },
  eventAmount: {
    fontSize: typography.fontSize.sm,
    color: t.text,
    fontVariant: ['tabular-nums'],
  },
  eventDate: {
    fontSize: typography.fontSize.xs,
    color: t.textMuted,
  },

  // Search row
  searchRow: {
    marginBottom: spacing.sm,
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
    backgroundColor: t.inputBg,
    borderWidth: 1,
    borderColor: t.border,
  },
  sortChipActive: {
    backgroundColor: t.tint.brand,
    borderColor: colors.brand[300],
  },
  sortLabel: {
    fontSize: typography.fontSize.xs,
    color: t.textSecondary,
  },
  sortLabelActive: {
    color: t.brandText,
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
    backgroundColor: t.inputBg,
    borderWidth: 1,
    borderColor: t.border,
    marginRight: spacing.xs,
  },
  filterChipActive: {
    backgroundColor: t.tint.brand,
    borderColor: colors.brand[300],
  },
  filterLabel: {
    fontSize: typography.fontSize.sm,
    color: t.textSecondary,
  },
  filterLabelActive: {
    color: t.brandText,
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
    color: t.textSecondary,
  },
  resetBtn: {
    paddingHorizontal: spacing.sm,
    paddingVertical: 4,
    borderRadius: radius.pill,
    borderWidth: 1,
    borderColor: t.border,
  },
  resetBtnText: {
    fontSize: typography.fontSize.xs,
    color: t.textSecondary,
  },

  // List
  skeletons: { gap: spacing.sm },
  list: { gap: spacing.sm, paddingBottom: spacing.xl },

  // Invoice card
  card: {
    backgroundColor: t.cardSubtle,
    borderRadius: radius.md,
    padding: spacing.md,
    borderWidth: 1,
    borderColor: t.inputBg,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  invoiceNumber: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
  },
  serviceName: {
    fontSize: typography.fontSize.xs,
    color: t.textSecondary,
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
    color: t.text,
    fontWeight: typography.fontWeight.medium,
  },
  balanceDue: {
    fontSize: typography.fontSize.xs,
    color: t.danger,
  },
  dueAt: {
    fontSize: typography.fontSize.xs,
    color: t.textMuted,
    marginTop: 2,
  },

  // Overdue warning
  overdueWarning: {
    marginTop: spacing.xs,
    backgroundColor: t.tint.danger,
    borderRadius: radius.sm,
    padding: spacing.xs,
  },
  overdueWarningText: {
    fontSize: typography.fontSize.xs,
    color: t.danger,
    fontWeight: typography.fontWeight.medium,
  },
});
