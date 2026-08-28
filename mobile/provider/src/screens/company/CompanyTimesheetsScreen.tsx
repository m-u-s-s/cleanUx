import React from 'react';
/* Chemin direct plutot que le baril : des suites mockent les barils a la main. */
import { formatCentimes } from '@/format/money';
import { View, FlatList, Text, StyleSheet, Alert } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Screen, Button, Badge, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { useAuth, can } from '@/auth';
import { spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

interface LigneFeuille {
  user_id: number;
  name: string | null;
  entries_count: number;
  worked_minutes: number;
  worked_hours: number;
}

interface Correction {
  id: number;
  user_id: number;
  user_name: string | null;
  started_at: string | null;
  worked_minutes: number;
  notes: string | null;
}

interface LigneRentabilite {
  key: number | string;
  missions_count: number;
  missions_without_timesheet: number;
  revenue_cents: number;
  total_cost_cents: number;
  margin_cents: number;
}

interface Rentabilite {
  data: LigneRentabilite[];
  meta: { missions_without_timesheet: number; default_hourly_rate_cents: number };
}

/**
 * LES HEURES (E20) ET CE QU'ELLES COÛTENT (E22).
 *
 * CE QUE CET ÉCRAN REFUSE DE MASQUER : les missions sans pointage. Les fondre dans la moyenne ferait
 * apparaître une marge de 100 % sur chacune, et un site entier paraîtrait florissant parce que
 * personne n'y a pointé. Une rentabilité flatteuse et fausse est pire que pas de rentabilité.
 *
 * LA MARGE N'EST PAS UNE DONNÉE D'ÉQUIPE. L'API la réserve à `analytics.view` : elle dit ce que coûte
 * chaque personne, et un exécutant n'a pas à lire le prix de ses propres heures dans un écran
 * d'exploitation. Ici on se contente de ne pas la demander quand le droit manque — l'API refuserait
 * de toute façon.
 *
 * ET LE TAUX HORAIRE EST UNE HYPOTHÈSE, QUI SE DIT. La plateforme ne connaît pas les salaires : une
 * marge présentée sans cette réserve se lirait comme un fait.
 */
export function CompanyTimesheetsScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());
  const { user } = useAuth();
  const qc = useQueryClient();

  const peutGerer = can(user, 'team.manage');
  const peutVoirLaMarge = can(user, 'analytics.view');

  const { data: feuille, refetch, isRefetching } = useQuery<{
    data: LigneFeuille[];
    pending: Correction[];
  }>({
    queryKey: ['company', 'timesheets'],
    queryFn: async () => (await apiClient.get('/provider/company/timesheets')).data,
  });

  const { data: rentabilite } = useQuery<Rentabilite>({
    queryKey: ['company', 'profitability'],
    // Demander une donnée qu'on n'a pas le droit de lire produirait un 403 à chaque ouverture.
    enabled: peutVoirLaMarge,
    queryFn: async () => (await apiClient.get('/provider/company/profitability')).data,
  });

  const statuer = useMutation({
    mutationFn: async (params: { id: number; approve: boolean }) =>
      apiClient.post(`/provider/company/timesheets/${params.id}/decision`, {
        approve: params.approve,
      }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['company', 'timesheets'] }),
    onError: (erreur: any) =>
      Alert.alert(tr('company_timesheets.decision_refusee'), erreur?.data?.message ?? 'Votre rôle ne permet pas cette action.'),
  });

  const sansPointage = rentabilite?.meta?.missions_without_timesheet ?? 0;

  return (
    <Screen>
      <Text style={styles.title}>{tr('company_timesheets.heures_et_rentabilite')}</Text>
      <Text style={styles.intro}>
        {tr('company_timesheets.ce_qui_a_ete_travaille')}
      </Text>

      {peutGerer && (feuille?.pending?.length ?? 0) > 0 && (
        <View style={styles.bloc}>
          <Text style={styles.sousTitre}>{tr('company_timesheets.corrections_a_approuver')}</Text>
          {(feuille?.pending ?? []).map((correction) => (
            <View key={correction.id} style={styles.ligne} testID={`correction-${correction.id}`}>
              <View style={styles.identite}>
                <Text style={styles.nom} numberOfLines={1}>
                  {correction.user_name ?? 'Sans nom'}
                </Text>
                <Text style={styles.detail} numberOfLines={1}>
                  {correction.worked_minutes} min
                  {correction.notes ? ` · ${correction.notes}` : ''}
                </Text>
              </View>
              <Button
                label={tr('company_timesheets.approuver')}
                size="sm"
                onPress={() => statuer.mutate({ id: correction.id, approve: true })}
                testID={`approuver-correction-${correction.id}`}
              />
              <Button
                label={tr('company_timesheets.refuser')}
                size="sm"
                variant="ghost"
                onPress={() => statuer.mutate({ id: correction.id, approve: false })}
              />
            </View>
          ))}
        </View>
      )}

      {peutVoirLaMarge && sansPointage > 0 && (
        // Annoncé, jamais masqué : une mission sans heures afficherait une marge de 100 %.
        <View style={styles.avertissement}>
          <Text style={styles.avertissementTexte}>
            {sansPointage} mission(s) sans pointage : leur marge n'est pas calculable.
          </Text>
        </View>
      )}

      <FlatList
        data={feuille?.data ?? []}
        keyExtractor={(l) => String(l.user_id)}
        onRefresh={refetch}
        refreshing={isRefetching}
        style={styles.liste}
        renderItem={({ item }) => (
          <View style={styles.ligne} testID={`heures-${item.user_id}`}>
            <View style={styles.identite}>
              <Text style={styles.nom} numberOfLines={1}>
                {item.name ?? 'Sans nom'}
              </Text>
              <Text style={styles.detail}>
                {item.entries_count} ligne(s) · {item.worked_minutes} min
              </Text>
            </View>
            <Badge label={`${item.worked_hours.toFixed(2)} h`} variant="neutral" />
          </View>
        )}
        ListEmptyComponent={
          <EmptyState
            title={tr('company_timesheets.aucune_heure_retenue')}
            message="Une correction en attente ne compte pas : payer avant approbation reviendrait à ne jamais approuver."
          />
        }
      />

      {peutVoirLaMarge && (rentabilite?.data?.length ?? 0) > 0 && (
        <View style={styles.bloc}>
          <Text style={styles.sousTitre}>{tr('company_timesheets.rentabilite')}</Text>
          {(rentabilite?.data ?? []).map((ligne) => (
            <View key={String(ligne.key)} style={styles.ligne} testID={`marge-${ligne.key}`}>
              <View style={styles.identite}>
                <Text style={styles.nom} numberOfLines={1}>
                  {ligne.key ? `Site ${ligne.key}` : 'Non ventilé'}
                </Text>
                <Text style={styles.detail}>
                  {ligne.missions_count} mission(s)
                  {ligne.missions_without_timesheet > 0
                    ? ` · ${ligne.missions_without_timesheet} sans heures`
                    : ''}
                </Text>
              </View>
              <Badge
                label={formatCentimes(ligne.margin_cents)}
                variant={ligne.margin_cents >= 0 ? 'success' : 'danger'}
              />
            </View>
          ))}
          <Text style={styles.note}>
            Le coût de main-d'œuvre s'appuie sur le taux horaire déclaré par votre société, à défaut{' '}
            {formatCentimes(rentabilite?.meta?.default_hourly_rate_cents ?? 0)} — une
            hypothèse prudente, pas un salaire connu de la plateforme.
          </Text>
        </View>
      )}
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
    bloc: { marginTop: spacing.md },
    liste: { marginVertical: spacing.md },
    avertissement: {
      backgroundColor: t.card,
      borderRadius: 12,
      padding: spacing.sm,
      marginTop: spacing.sm,
    },
    avertissementTexte: { fontSize: typography.fontSize.sm, color: t.text },
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
    note: {
      fontSize: typography.fontSize.xs,
      color: t.textMuted,
      marginTop: spacing.xs,
    },
  });
