import React from 'react';
/* Chemin direct plutot que le baril : des suites mockent les barils a la main,
   et un export neuf y manque sans que `tsc` bronche. */
import { formatCentimes } from '@/format/money';
import { View, FlatList, Text, StyleSheet } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { Screen, Badge, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

interface LigneMois {
  month: string;
  bookings_count: number;
  total_cents: number;
}

interface LigneMetier {
  trade: string;
  bookings_count: number;
  total_cents: number;
}

interface Serie {
  bookings_count: number;
  total_cents: number;
  average_cents: number;
}

interface Budget {
  bookings_count: number;
  total_cents: number;
  monthly_average_cents: number;
  by_month: LigneMois[];
  by_trade: LigneMetier[];
  subscription_vs_on_demand: { subscription: Serie; on_demand: Serie };
}

/*
 * La division par cent ET le symbole vivaient ici. Les deux sont desormais dans le
 * formateur partage : une division recopiee finit par etre oubliee quelque part, et
 * un montant affiche cent fois trop grand se remarque avant nous.
 */
const euros = (cents: number) => formatCentimes(cents);

/**
 * LE BUDGET MAISON (E4).
 *
 * TOUT EST DÉJÀ EN BASE, et personne ne le voit. Un client reçoit ses factures une par une et n'a
 * aucun moyen de répondre à la seule question qu'il se pose : « combien est-ce que je dépense en
 * entretien, et est-ce que ça augmente ». C'est elle qui décide de passer à un abonnement, d'espacer
 * les interventions, ou de renoncer — et elle se pose souvent en recevant une facture, sur son
 * téléphone.
 *
 * LE COMPARATIF ABONNEMENT / À LA DEMANDE EST LE SEUL CHIFFRE QUI SERVE À DÉCIDER. Le reste
 * documente ; celui-ci répond.
 */
export function BudgetScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  const { data: budget, refetch, isRefetching } = useQuery<Budget>({
    queryKey: ['client', 'budget'],
    queryFn: async () => (await apiClient.get('/client/budget')).data.data,
  });

  const comparatif = budget?.subscription_vs_on_demand;

  return (
    <Screen>
      <Text style={styles.title}>{tr('budget.mon_budget_entretien')}</Text>
      <Text style={styles.intro}>{tr('budget.ce_que_vous_engagez_par')}</Text>

      <View style={styles.resume} testID="resume-budget">
        <View style={styles.carte}>
          <Text style={styles.libelle}>{tr('budget.total')}</Text>
          <Text style={styles.montant}>{euros(budget?.total_cents ?? 0)}</Text>
          <Text style={styles.detail}>{budget?.bookings_count ?? 0} intervention(s)</Text>
        </View>

        <View style={styles.carte}>
          <Text style={styles.libelle}>{tr('budget.par_mois')}</Text>
          <Text style={styles.montant}>{euros(budget?.monthly_average_cents ?? 0)}</Text>
          {/* Calculée sur les mois actifs : diviser par douze un client arrivé en octobre lui
              montrerait une moyenne qu'il ne reconnaît pas. */}
          <Text style={styles.detail}>{tr('budget.sur_vos_mois_actifs')}</Text>
        </View>
      </View>

      {comparatif && (
        <View style={styles.bloc} testID="comparatif">
          <Text style={styles.sousTitre}>{tr('budget.abonnement_ou_a_la_demande')}</Text>

          <View style={styles.ligne}>
            <View style={styles.identite}>
              <Text style={styles.nom}>{tr('budget.recurrentes')}</Text>
              <Text style={styles.detail}>
                {comparatif.subscription.bookings_count} · {euros(comparatif.subscription.average_cents)} en moyenne
              </Text>
            </View>
            <Badge label={euros(comparatif.subscription.total_cents)} variant="neutral" />
          </View>

          <View style={styles.ligne}>
            <View style={styles.identite}>
              <Text style={styles.nom}>{tr('budget.ponctuelles')}</Text>
              <Text style={styles.detail}>
                {comparatif.on_demand.bookings_count} · {euros(comparatif.on_demand.average_cents)} en moyenne
              </Text>
            </View>
            <Badge label={euros(comparatif.on_demand.total_cents)} variant="neutral" />
          </View>

          {comparatif.subscription.bookings_count === 0 && (
            // « Vous n'avez aucun abonnement » est une réponse utile ; un bloc absent ne l'est pas.
            <Text style={styles.note}>
              {tr('budget.vous_n_avez_aucune_intervention')}
            </Text>
          )}
        </View>
      )}

      <FlatList
        data={budget?.by_trade ?? []}
        keyExtractor={(l) => l.trade}
        onRefresh={refetch}
        refreshing={isRefetching}
        style={styles.liste}
        ListHeaderComponent={<Text style={styles.sousTitre}>{tr('budget.par_metier')}</Text>}
        renderItem={({ item }) => (
          <View style={styles.ligne} testID={`metier-${item.trade}`}>
            <View style={styles.identite}>
              <Text style={styles.nom} numberOfLines={1}>
                {item.trade}
              </Text>
              <Text style={styles.detail}>{item.bookings_count} intervention(s)</Text>
            </View>
            <Text style={styles.montantLigne}>{euros(item.total_cents)}</Text>
          </View>
        )}
        ListEmptyComponent={
          <EmptyState
            title={tr('budget.rien_a_afficher')}
            message="Vos interventions apparaîtront ici au fur et à mesure."
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
    },
    intro: {
      fontSize: typography.fontSize.sm,
      color: t.textMuted,
      marginBottom: spacing.md,
    },
    sousTitre: {
      fontSize: typography.fontSize.base,
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
      marginBottom: spacing.xs,
    },
    resume: { flexDirection: 'row', gap: spacing.sm },
    carte: {
      flex: 1,
      backgroundColor: t.card,
      borderRadius: 12,
      padding: spacing.sm,
    },
    libelle: { fontSize: typography.fontSize.xs, color: t.textMuted },
    montant: {
      fontSize: typography.fontSize.lg,
      fontWeight: typography.fontWeight.bold,
      color: t.text,
    },
    montantLigne: {
      fontSize: typography.fontSize.sm,
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
    },
    bloc: { marginTop: spacing.md },
    liste: { marginTop: spacing.md },
    ligne: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: spacing.sm,
      paddingVertical: spacing.sm,
      borderBottomWidth: StyleSheet.hairlineWidth,
      borderBottomColor: t.border,
    },
    identite: { flex: 1, minWidth: 0 },
    nom: {
      fontSize: typography.fontSize.base,
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
    },
    detail: { fontSize: typography.fontSize.sm, color: t.textMuted },
    note: { fontSize: typography.fontSize.xs, color: t.textMuted, marginTop: spacing.xs },
  });
