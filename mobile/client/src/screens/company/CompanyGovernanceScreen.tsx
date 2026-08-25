import React from 'react';
/* Chemin direct plutot que le baril : des suites mockent les barils a la main. */
import { formatCentimes } from '@/format/money';
import { View, ScrollView, Text, StyleSheet, Alert } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Screen, Button, Badge } from '@/ui';
import { apiClient } from '@/api';
import { spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

interface Budget {
  budget_id: number;
  site_name: string | null;
  limit_cents: number;
  committed_cents: number;
  usage_percent: number;
  is_warning: boolean;
  is_exceeded: boolean;
}

interface Demande {
  id: number;
  site_name: string | null;
  trade_name: string | null;
  requested_by: string | null;
  scheduled_at: string | null;
  estimated_cents: number;
}

interface Sla {
  meta: {
    bookings_count: number;
    completion_rate: number;
    cancellation_rate: number;
    punctuality_rate: number | null;
    without_arrival_data: number;
  };
}

const euros = (cents: number) => formatCentimes(cents);

/**
 * LE PILOTAGE D'UNE ENTREPRISE CLIENTE — budgets (E7), approbations (E8), niveau de service (E9).
 *
 * L'APPROBATION EST MOBILE AVANT TOUT. Une demande qui attend bloque une intervention, et
 * l'approbateur est rarement à son bureau : c'est un gérant, un responsable de site, quelqu'un qui
 * se déplace. Chaque heure d'attente est une heure où le prestataire n'est pas cherché — et sur une
 * fuite d'eau, ça compte.
 *
 * APPROUVER LANCE LA RECHERCHE. L'écran le dit, parce que c'est précisément ce qui manquait avant :
 * la demande basculait de statut et personne ne cherchait de professionnel.
 *
 * LES EXPORTS COMPTABLES (E11) N'ONT PAS D'ÉQUIVALENT ICI, délibérément : un fichier FEC destiné à
 * un logiciel de comptabilité n'a rien à faire dans le stockage d'un téléphone.
 */
export function CompanyGovernanceScreen() {
  const styles = stylesFor(useThemeColors());
  const qc = useQueryClient();

  const { data: budgets, refetch, isRefetching } = useQuery<Budget[]>({
    queryKey: ['client-company', 'budgets'],
    queryFn: async () => (await apiClient.get('/client/company/budgets')).data.data ?? [],
  });

  const { data: demandes } = useQuery<Demande[]>({
    queryKey: ['client-company', 'approvals'],
    queryFn: async () => (await apiClient.get('/client/company/approvals')).data.data ?? [],
  });

  const { data: sla } = useQuery<Sla>({
    queryKey: ['client-company', 'service-level'],
    queryFn: async () => (await apiClient.get('/client/company/service-level')).data,
  });

  const decider = useMutation({
    mutationFn: async (params: { id: number; approve: boolean }) =>
      apiClient.post(`/client/company/approvals/${params.id}/decision`, { approve: params.approve }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['client-company', 'approvals'] });
      qc.invalidateQueries({ queryKey: ['client-company', 'budgets'] });
    },
    onError: (erreur: any) =>
      // « Une demande ne s'approuve pas soi-même » est une règle à LIRE, pas une panne.
      Alert.alert('Décision refusée', erreur?.data?.message ?? 'La demande n’a pas pu être traitée.'),
  });

  return (
    <Screen>
      <ScrollView>
        <Text style={styles.title}>Pilotage</Text>
        <Text style={styles.intro}>
          Budgets, approbations et niveau de service.
        </Text>

        <View style={styles.bloc} testID="approbations">
          <Text style={styles.sousTitre}>Demandes à approuver</Text>

          {(demandes ?? []).map((demande) => (
            <View key={demande.id} style={styles.ligne} testID={`demande-${demande.id}`}>
              <View style={styles.identite}>
                <Text style={styles.nom} numberOfLines={1}>
                  {demande.site_name ?? 'Sans local'}
                  {demande.trade_name ? ` — ${demande.trade_name}` : ''}
                </Text>
                <Text style={styles.detail} numberOfLines={1}>
                  {demande.requested_by ?? 'un membre'} · {euros(demande.estimated_cents)}
                </Text>
              </View>

              <Button
                label="Approuver"
                size="sm"
                onPress={() => decider.mutate({ id: demande.id, approve: true })}
                testID={`approuver-${demande.id}`}
              />
              <Button
                label="Refuser"
                size="sm"
                variant="ghost"
                onPress={() => decider.mutate({ id: demande.id, approve: false })}
                testID={`refuser-${demande.id}`}
              />
            </View>
          ))}

          {(demandes ?? []).length === 0 && (
            <Text style={styles.detail}>Aucune demande en attente.</Text>
          )}

          {(demandes ?? []).length > 0 && (
            // Ce qui manquait avant : la demande basculait de statut et personne ne cherchait de
            // professionnel.
            <Text style={styles.note}>
              Approuver lance immédiatement la recherche d'un professionnel.
            </Text>
          )}
        </View>

        {sla && (
          <View style={styles.bloc} testID="niveau-service">
            <Text style={styles.sousTitre}>Niveau de service</Text>

            <View style={styles.ligne}>
              <View style={styles.identite}>
                <Text style={styles.nom}>Réalisation</Text>
                <Text style={styles.detail}>{sla.meta.bookings_count} intervention(s)</Text>
              </View>
              <Badge label={`${sla.meta.completion_rate} %`} variant="neutral" />
            </View>

            <View style={styles.ligne}>
              <View style={styles.identite}>
                <Text style={styles.nom}>Ponctualité</Text>
                {/* Annoncées, jamais fondues : les compter comme des retards punirait un GPS
                    coupé ; comme des arrivées à l'heure, l'inverse. */}
                <Text style={styles.detail}>
                  {sla.meta.without_arrival_data} sans arrivée relevée
                </Text>
              </View>
              <Badge
                label={sla.meta.punctuality_rate !== null ? `${sla.meta.punctuality_rate} %` : '—'}
                variant="neutral"
              />
            </View>
          </View>
        )}

        <View style={styles.bloc} testID="budgets">
          <Text style={styles.sousTitre}>Budgets en cours</Text>

          {(budgets ?? []).map((budget) => (
            <View key={budget.budget_id} style={styles.ligne} testID={`budget-${budget.budget_id}`}>
              <View style={styles.identite}>
                <Text style={styles.nom} numberOfLines={1}>
                  {budget.site_name ?? 'Toute la société'}
                </Text>
                <Text style={styles.detail} numberOfLines={1}>
                  {euros(budget.committed_cents)} sur {euros(budget.limit_cents)}
                </Text>
              </View>
              <Badge
                label={`${budget.usage_percent} %`}
                variant={budget.is_exceeded ? 'danger' : budget.is_warning ? 'warning' : 'success'}
              />
            </View>
          ))}

          {(budgets ?? []).length === 0 && (
            <Text style={styles.detail}>
              Aucun budget défini. Sans plafond, le dépassement se découvre à la facture.
            </Text>
          )}
        </View>

        <Button
          label="Rafraîchir"
          size="sm"
          variant="ghost"
          disabled={isRefetching}
          onPress={() => refetch()}
        />
      </ScrollView>
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
