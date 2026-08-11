import React from 'react';
import { View, FlatList, Text, StyleSheet } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { Screen, Badge, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

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
  const styles = stylesFor(useThemeColors());

  const { data: tournee, refetch, isRefetching } = useQuery<Tournee>({
    queryKey: ['provider', 'daily-route'],
    queryFn: async () => (await apiClient.get('/provider/growth/daily-route')).data.data,
  });

  return (
    <Screen>
      <Text style={styles.title}>Ma journée</Text>
      <Text style={styles.intro}>
        {tournee
          ? `${tournee.missions_count} intervention(s) · ${tournee.total_travel_km} km de trajet`
          : 'Chargement…'}
      </Text>

      {(tournee?.tight_transitions ?? 0) > 0 && (
        // Le seul chiffre qui appelle une action : c'est ce qu'il faut savoir la veille, pas en
        // route.
        <View style={styles.avertissement} testID="enchainements-serres">
          <Text style={styles.avertissementTexte}>
            {tournee?.tight_transitions} enchaînement(s) ne tiennent pas. Prévenez le client avant de
            partir.
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
                    ? ` · ${item.slack_minutes} min de battement`
                    : ` · ${Math.abs(item.slack_minutes)} min de retard prévisible`)}
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
                  {item.address ?? 'Adresse non renseignée'}
                </Text>
              </View>

              {item.is_tight && <Badge label="Serré" variant="danger" />}
            </View>
          </View>
        )}
        ListEmptyComponent={
          <EmptyState title="Aucune intervention aujourd'hui" message="Votre journée est libre." />
        }
      />

      {tournee && (
        // L'approximation est ANNONCÉE : prétendre à une durée exacte sans service de routage serait
        // mentir, et un temps sous-estimé ferait rater le rendez-vous suivant.
        <Text style={styles.note}>
          Trajets estimés à {tournee.assumed_speed_kmh} km/h en moyenne, à vol d'oiseau majoré.
        </Text>
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
