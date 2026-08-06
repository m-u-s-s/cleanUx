import React from 'react';
import { View, FlatList, Text, Alert, StyleSheet } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Screen, Button, Badge, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

interface MissionARepartir {
  id: number;
  status: string;
  planned_start_at: string | null;
  site: string | null;
  city: string | null;
  lead: string | null;
  lead_user_id: number | null;
}

interface Membre {
  user_id: number;
  name: string | null;
  status: string;
}

/**
 * La répartition des missions, en natif.
 *
 * L'assignation passe par `MissionAssignmentService`, partagé avec l'écran web : réassigner
 * suppose de libérer les leads actifs des autres puis de synchroniser `lead_provider_user_id`, et
 * deux implémentations de cette règle divergeraient.
 */
export function CompanyDispatchScreen() {
  const styles = stylesFor(useThemeColors());
  const qc = useQueryClient();

  const { data: missions, refetch, isRefetching } = useQuery<MissionARepartir[]>({
    queryKey: ['company', 'missions'],
    queryFn: async () => (await apiClient.get('/provider/company/missions')).data.data ?? [],
  });

  const { data: membres } = useQuery<Membre[]>({
    queryKey: ['company', 'members'],
    queryFn: async () => (await apiClient.get('/provider/company/members')).data.data ?? [],
  });

  const assigner = useMutation({
    mutationFn: async ({ missionId, userId }: { missionId: number; userId: number }) => {
      await apiClient.post(`/provider/company/missions/${missionId}/assign`, { user_id: userId });
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['company', 'missions'] }),
    onError: () => Alert.alert('Assignation refusée', 'Votre rôle ne permet pas de répartir les missions.'),
  });

  /** Un choix parmi les membres actifs — l'API refuse de toute façon un employé d'une autre société. */
  function proposerAssignation(mission: MissionARepartir) {
    const candidats = (membres ?? []).filter((m) => m.status === 'active');

    if (candidats.length === 0) {
      Alert.alert('Aucun employé', "Invitez d'abord des collaborateurs dans votre société.");

      return;
    }

    Alert.alert(
      'Assigner la mission',
      mission.site ?? `Mission #${mission.id}`,
      [
        ...candidats.slice(0, 10).map((m) => ({
          text: m.name ?? `#${m.user_id}`,
          onPress: () => assigner.mutate({ missionId: mission.id, userId: m.user_id }),
        })),
        { text: 'Annuler', style: 'cancel' as const },
      ],
    );
  }

  return (
    <Screen>
      <Text style={styles.title}>Répartition</Text>

      <FlatList
        data={missions ?? []}
        keyExtractor={(m) => String(m.id)}
        onRefresh={refetch}
        refreshing={isRefetching}
        renderItem={({ item }) => (
          <View style={styles.ligne} testID={`mission-${item.id}`}>
            <View style={styles.identite}>
              <Text style={styles.nom} numberOfLines={1}>
                {item.site ?? `Mission #${item.id}`}
                {item.city ? ` — ${item.city}` : ''}
              </Text>
              <Text style={styles.detail} numberOfLines={1}>
                {item.planned_start_at
                  ? new Date(item.planned_start_at).toLocaleString('fr-FR', {
                      day: '2-digit',
                      month: 'short',
                      hour: '2-digit',
                      minute: '2-digit',
                    })
                  : 'Non planifiée'}
              </Text>
              <Badge label={item.lead ?? 'Non assignée'} variant={item.lead ? 'brand' : 'neutral'} />
            </View>

            <Button
              label={item.lead ? 'Réassigner' : 'Assigner'}
              size="sm"
              variant="secondary"
              onPress={() => proposerAssignation(item)}
            />
          </View>
        )}
        ListEmptyComponent={
          <EmptyState title="Aucune mission" message="Les missions confiées à votre société apparaîtront ici." />
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
      gap: spacing.xs,
      alignItems: 'flex-start',
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
