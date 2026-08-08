import React from 'react';
import { View, Text, ScrollView, StyleSheet, Pressable, Alert } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useRoute } from '@react-navigation/native';
import type { RouteProp } from '@react-navigation/native';
import { Screen, Button, Badge, Divider, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { useAuth, can } from '@/auth';
import { useLiveMissionUpdates } from '@/missions';
import { spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';

interface Disponibilite {
  user_id: number;
  name: string | null;
  is_free: boolean;
}

interface MissionSociete {
  id: number;
  status: string;
  planned_start_at: string | null;
  site: string | null;
  city: string | null;
  lead: string | null;
  lead_user_id: number | null;
}

/**
 * LE DÉTAIL D'UNE MISSION DE SOCIÉTÉ — choisir en sachant qui est libre.
 *
 * Ce qui existait tenait dans un `Alert.alert` limité à dix noms, SANS indicateur de disponibilité :
 * le répartiteur choisissait à l'aveugle depuis son téléphone, là où l'écran web le renseignait
 * déjà. Un nom de plus que dix, et la personne n'était tout simplement pas proposable.
 *
 * LA DISPONIBILITÉ VIENT DU SERVEUR, jamais d'un calcul local. Elle repose sur le chevauchement des
 * missions de toute la société — une donnée que le téléphone n'a pas, et ne doit pas avoir.
 *
 * `useLiveMissionUpdates` était défini depuis longtemps et JAMAIS APPELÉ. Deux répartiteurs sur la
 * même mission s'écrasaient sans le voir ; ici l'écran se rafraîchit quand la mission bouge
 * ailleurs.
 */
export function CompanyMissionDetailScreen() {
  const styles = stylesFor(useThemeColors());
  const { user } = useAuth();
  const qc = useQueryClient();

  const route = useRoute<RouteProp<RootStackParamList, 'CompanyMissionDetail'>>();
  const missionId = route.params?.missionId ?? null;

  const peutRepartir = can(user, 'missions.assign') || can(user, 'missions.dispatch');

  const { data: missions } = useQuery<MissionSociete[]>({
    queryKey: ['company', 'missions'],
    queryFn: async () => (await apiClient.get('/provider/company/missions')).data.data ?? [],
  });

  const mission = (missions ?? []).find((m) => m.id === missionId) ?? null;

  const { data: dispos, refetch: rechargerDispos } = useQuery<Disponibilite[]>({
    queryKey: ['company', 'availability', missionId],
    queryFn: async () =>
      (await apiClient.get(`/provider/company/availability?mission_id=${missionId}`)).data.data
        ?.workers ?? [],
    enabled: missionId !== null && peutRepartir,
  });

  // Enfin branché : la mission peut changer sous les yeux d'un autre répartiteur.
  useLiveMissionUpdates(missionId, () => {
    qc.invalidateQueries({ queryKey: ['company', 'missions'] });
    rechargerDispos();
  });

  const assigner = useMutation({
    mutationFn: async (userId: number) =>
      apiClient.post(`/provider/company/missions/${missionId}/assign`, { user_id: userId }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['company', 'missions'] });
      rechargerDispos();
    },
    onError: (erreur: any) =>
      Alert.alert(
        'Assignation refusée',
        erreur?.data?.message ?? 'Votre rôle ne permet pas de répartir cette mission.',
      ),
  });

  const renfort = useMutation({
    mutationFn: async (params: { userId: number; retirer: boolean }) =>
      apiClient.post(`/provider/company/missions/${missionId}/helpers`, {
        user_id: params.userId,
        remove: params.retirer,
      }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['company', 'missions'] });
      rechargerDispos();
    },
    onError: (erreur: any) =>
      Alert.alert('Action refusée', erreur?.data?.message ?? "L'action n'a pas pu être effectuée."),
  });

  if (mission === null) {
    return (
      <Screen>
        <EmptyState
          title="Mission introuvable"
          message="Elle a peut-être été annulée ou confiée à une autre société."
        />
      </Screen>
    );
  }

  return (
    <Screen>
      <ScrollView>
        <Text style={styles.title}>{mission.site ?? `Mission #${mission.id}`}</Text>
        <Text style={styles.sousTitre}>
          {mission.city ? `${mission.city} · ` : ''}
          {mission.planned_start_at
            ? new Date(mission.planned_start_at).toLocaleString('fr-FR', {
                weekday: 'long',
                day: '2-digit',
                month: 'long',
                hour: '2-digit',
                minute: '2-digit',
              })
            : 'Non planifiée'}
        </Text>

        <View style={styles.badge}>
          <Badge
            label={mission.lead ?? 'Non assignée'}
            variant={mission.lead ? 'brand' : 'neutral'}
          />
        </View>

        {!peutRepartir && (
          <Text style={styles.info}>
            Vous consultez cette mission. Sa répartition relève d'un autre rôle.
          </Text>
        )}

        {peutRepartir && (
          <>
            <Divider />
            <Text style={styles.section}>Qui est libre sur ce créneau</Text>

            {(dispos ?? []).length === 0 && (
              <Text style={styles.info}>
                Aucun collaborateur à proposer — la mission n'a peut-être pas d'horaire.
              </Text>
            )}

            {(dispos ?? []).map((personne) => (
              <View key={personne.user_id} style={styles.lignePersonne}>
                <View style={styles.identite}>
                  <Text style={styles.nom} numberOfLines={1}>
                    {personne.name ?? `#${personne.user_id}`}
                  </Text>
                  {/*
                    LE BADGE DIT, IL N'INTERDIT PAS. Un répartiteur qui connaît son équipe passe
                    outre pour de bonnes raisons — un échange entre collègues, une heure
                    supplémentaire consentie. L'outil l'informe ; il ne décide pas à sa place.
                  */}
                  <Text style={personne.is_free ? styles.libre : styles.pris}>
                    {personne.is_free ? 'libre' : 'déjà pris'}
                  </Text>
                </View>

                <View style={styles.actions}>
                  <Button
                    label={mission.lead_user_id === personne.user_id ? 'Responsable' : 'Assigner'}
                    size="sm"
                    variant={mission.lead_user_id === personne.user_id ? 'ghost' : 'secondary'}
                    disabled={mission.lead_user_id === personne.user_id}
                    onPress={() => assigner.mutate(personne.user_id)}
                  />
                  <Pressable
                    accessibilityRole="button"
                    onPress={() => renfort.mutate({ userId: personne.user_id, retirer: false })}
                  >
                    <Text style={styles.lienRenfort}>+ renfort</Text>
                  </Pressable>
                </View>
              </View>
            ))}
          </>
        )}
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
    sousTitre: {
      fontSize: typography.fontSize.sm,
      color: t.textMuted,
      marginTop: spacing.xs,
    },
    badge: {
      flexDirection: 'row',
      marginTop: spacing.sm,
      marginBottom: spacing.sm,
    },
    section: {
      fontSize: typography.fontSize.sm,
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
      marginTop: spacing.sm,
      marginBottom: spacing.xs,
    },
    info: {
      fontSize: typography.fontSize.sm,
      color: t.textMuted,
      marginTop: spacing.sm,
    },
    lignePersonne: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',
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
      color: t.text,
    },
    libre: {
      fontSize: typography.fontSize.sm,
      color: t.textSecondary,
    },
    pris: {
      fontSize: typography.fontSize.sm,
      color: t.textMuted,
    },
    actions: {
      alignItems: 'flex-end',
      gap: spacing.xs,
    },
    lienRenfort: {
      fontSize: typography.fontSize.sm,
      color: t.textSecondary,
      textDecorationLine: 'underline',
    },
  });
