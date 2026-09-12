import React from 'react';
import { View, FlatList, Text, StyleSheet } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { Screen, Badge, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

interface Etape {
  mission_id: number;
  booking_reference: string | null;
  address: string | null;
  planned_start_at: string | null;
  travel_km: number | null;
  travel_minutes: number | null;
  slack_minutes: number | null;
  is_tight: boolean;
}

interface Tournee {
  date: string;
  missions_count: number;
  total_travel_km: number;
  tight_transitions: number;
  steps: Etape[];
  assumed_speed_kmh: number;
}

/**
 * MA JOURNÉE (E17 + E34).
 *
 * CE QUI SE PASSE AUJOURD'HUI. Un prestataire a quatre interventions et les découvre dans une liste
 * triée par heure. Il ne sait pas combien de temps il lui faut entre la deuxième et la troisième, ni
 * si l'ordre lui fait traverser la ville deux fois. Il l'apprend en le faisant, et arrive en retard
 * à la troisième.
 *
 * ON NE RÉORDONNE RIEN. Un client attend à 14 h : la tournée n'est pas une optimisation libre, et un
 * outil qui propose de décaler des rendez-vous pris ne sert à personne. Le calcul de trajet sert à
 * dire si l'enchaînement TIENT — c'est ce qui permet de prévenir AVANT, et ça change tout pour le
 * client.
 *
 * L'ÉCRAN SE CONSULTE EN MONTANT DANS LA VOITURE. Les battements négatifs sont mis en avant : ce
 * sont les seuls chiffres qui appellent une action.
 */
export function DailyRouteScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  const { data: tournee, refetch, isRefetching } = useQuery<Tournee>({
    queryKey: ['provider', 'daily-route'],
    queryFn: async () => (await apiClient.get('/provider/growth/daily-route')).data.data,
  });

  return (
    <Screen>
      <Text style={styles.title}>{tr('daily_route.ma_journee')}</Text>
      <Text style={styles.intro}>
        {tournee
          ? tr('daily_route.n_interventions_km_de_trajet', { n: tournee.missions_count, km: tournee.total_travel_km })
          : tr('daily_route.chargement')}
      </Text>

      {(tournee?.tight_transitions ?? 0) > 0 && (
        // Le seul chiffre qui appelle une action : c'est ce qu'il faut savoir la veille, pas en
        // route.
        <View style={styles.avertissement} testID="enchainements-serres">
          <Text style={styles.avertissementTexte}>
            {tr('daily_route.enchainements_ne_tiennent_pas', { n: tournee?.tight_transitions ?? 0 })}
          </Text>
        </View>
      )}

      <FlatList
        data={tournee?.steps ?? []}
        keyExtractor={(e) => String(e.mission_id)}
        onRefresh={refetch}
        refreshing={isRefetching}
        style={styles.liste}
        renderItem={({ item }) => (
          <View style={styles.etape} testID={`etape-${item.mission_id}`}>
            {item.travel_minutes !== null && (
              <Text style={[styles.trajet, item.is_tight && styles.trajetServe]}>
                ↓ {item.travel_km} km · {item.travel_minutes} min
                {item.slack_minutes !== null &&
                  (item.slack_minutes >= 0
                    ? ` · ${tr('daily_route.n_min_de_battement', { n: item.slack_minutes })}`
                    : ` · ${tr('daily_route.n_min_de_retard_previsible', { n: Math.abs(item.slack_minutes) })}`)}
              </Text>
            )}

            <View style={styles.ligne}>
              <View style={styles.identite}>
                <Text style={styles.nom} numberOfLines={1}>
                  {item.planned_start_at
                    ? new Date(item.planned_start_at).toLocaleTimeString('fr-BE', {
                        hour: '2-digit',
                        minute: '2-digit',
                      })
                    : '—'}{' '}
                  · {item.booking_reference ?? `Mission ${item.mission_id}`}
                </Text>
                <Text style={styles.detail} numberOfLines={1}>
                  {item.address ?? tr('daily_route.adresse_non_renseignee')}
                </Text>
              </View>

              {item.is_tight && <Badge label={tr('daily_route.serre')} variant="danger" />}
            </View>
          </View>
        )}
        ListEmptyComponent={
          <EmptyState title={tr('daily_route.aucune_intervention_aujourd_hui')} message={tr('daily_route.votre_journee_est_libre')} />
        }
      />

      {tournee && (
        // L'approximation est ANNONCÉE : prétendre à une durée exacte sans service de routage serait
        // mentir, et un temps sous-estimé ferait rater le rendez-vous suivant.
        <Text style={styles.note}>{tr('daily_route.trajets_estimes_a_n_kmh', { n: tournee.assumed_speed_kmh })}</Text>
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
    avertissement: {
      backgroundColor: t.card,
      borderRadius: 12,
      padding: spacing.sm,
    },
    avertissementTexte: { fontSize: typography.fontSize.sm, color: t.text },
    liste: { marginTop: spacing.md },
    etape: { marginBottom: spacing.sm },
    trajet: {
      fontSize: typography.fontSize.xs,
      color: t.textMuted,
      marginBottom: spacing.xs / 2,
    },
    trajetServe: { color: t.text, fontWeight: typography.fontWeight.semibold },
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
    note: { fontSize: typography.fontSize.xs, color: t.textMuted, marginTop: spacing.sm },
  });
