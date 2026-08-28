import React from 'react';
/* Chemin direct plutot que le baril : des suites mockent les barils a la main,
   et un export neuf y manque sans que `tsc` bronche. */
import { formatMontant } from '@/format/money';
import { View, FlatList, Text, StyleSheet } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Screen, Button, Badge, KPICard, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';
import type { FactureSociete, ResumeFacturation } from './types';
import { useTraduction } from '@/i18n';

interface Facturation {
  summary: ResumeFacturation;
  invoices: FactureSociete[];
}

/**
 * LA FACTURATION DE LA SOCIÉTÉ.
 *
 * L'écran web équivalent (`BillingCenter`) renvoie des zéros codés en dur — « Données simulées, à
 * connecter à Invoice model » — et une liste vide. Le recopier aurait donné un écran natif
 * incapable d'afficher quoi que ce soit, ce qui est pire qu'une absence : un zéro se laisse lire
 * comme un fait.
 *
 * La donnée existait pourtant : `/api/client/invoices` la sert déjà. L'API société s'appuie sur le
 * même `ClientFinanceDocumentScope`, qui porte aussi la restriction par local d'un membre à portée
 * réduite.
 */
export function CompanyBillingScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();

  const { data, refetch, isRefetching, isError } = useQuery<Facturation | null>({
    queryKey: ['client-company', 'billing'],
    queryFn: async () => (await apiClient.get('/client/company/billing')).data.data ?? null,
  });

  if (isError) {
    return (
      <Screen>
        <EmptyState
          title={tr('company_billing.facturation_indisponible')}
          message="Votre rôle ne permet peut-être pas de consulter la facturation de la société."
          actionLabel="Réessayer"
          onAction={() => void refetch()}
        />
      </Screen>
    );
  }

  const resume = data?.summary;

  return (
    <Screen>
      <Text style={styles.title}>Facturation</Text>

      <View style={styles.grilleKpis}>
        <View style={styles.kpi}>
          <KPICard
            title={tr('company_billing.reste_a_payer')}
            value={resume ? formatMontant(resume.unpaid) : '—'}
            tone={resume && resume.unpaid > 0 ? 'warning' : 'neutral'}
            loading={!resume}
          />
        </View>
        <View style={styles.kpi}>
          <KPICard
            title={tr('company_billing.emis_ce_mois')}
            value={resume ? formatMontant(resume.total_month) : '—'}
            loading={!resume}
          />
        </View>
        <View style={styles.kpi}>
          <KPICard title="Factures" value={resume?.count_total ?? 0} loading={!resume} />
        </View>
      </View>

      <FlatList
        data={data?.invoices ?? []}
        keyExtractor={(f) => String(f.id)}
        onRefresh={refetch}
        refreshing={isRefetching}
        renderItem={({ item }) => (
          <View style={styles.ligne} testID={`facture-${item.id}`}>
            <View style={styles.identite}>
              <Text style={styles.nom} numberOfLines={1}>
                {item.invoice_number ?? `Facture ${item.id}`}
              </Text>
              <Text style={styles.detail} numberOfLines={1}>
                {item.issued_at ?? 'Non émise'} · {formatMontant(item.total_amount, item.currency)}
                {item.balance_due > 0 ? ` · ${formatMontant(item.balance_due, item.currency)} dus` : ''}
              </Text>
            </View>

            <Badge
              label={item.status}
              variant={item.status === 'paid' ? 'success' : 'warning'}
            />

            {/*
              Le détail d'une facture est l'écran EXISTANT de l'application, servi par
              `/client/invoices/{id}` — dont la portée org est la même. En écrire un second ici
              dupliquerait le PDF et son URL signée.
            */}
            <Button
              label="Voir"
              size="sm"
              variant="ghost"
              onPress={() => navigation.navigate('InvoiceDetail', { id: item.id })}
            />
          </View>
        )}
        ListEmptyComponent={
          <EmptyState
            title={tr('company_billing.aucune_facture')}
            message="Les factures de votre société apparaîtront ici dès la première intervention facturée."
          />
        }
      />
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) =>
  StyleSheet.create({
    title: {
      fontSize: typography.fontSize.xl,
      fontWeight: typography.fontWeight.bold,
      color: t.text,
      marginBottom: spacing.md,
    },
    grilleKpis: {
      flexDirection: 'row',
      flexWrap: 'wrap',
      gap: spacing.sm,
      marginBottom: spacing.md,
    },
    kpi: {
      flexGrow: 1,
      flexBasis: '30%',
    },
    ligne: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: spacing.sm,
      paddingVertical: spacing.sm,
      borderBottomWidth: StyleSheet.hairlineWidth,
      borderBottomColor: t.border,
    },
    identite: {
      flex: 1,
      minWidth: 0,
    },
    nom: {
      fontSize: typography.fontSize.base,
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
    },
    detail: {
      fontSize: typography.fontSize.sm,
      color: t.textMuted,
    },
  });
