import React from 'react';
import { View, FlatList, Text, Alert, StyleSheet, Pressable } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Screen, Button, Badge, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { useAuth, can } from '@/auth';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { RootStackParamList } from '@/navigation/types';
import { spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

interface MissionARepartir {
  id: number;
  status: string;
  planned_start_at: string | null;
  site: string | null;
  city: string | null;
  lead: string | null;
  lead_user_id: number | null;
}

interface EquipeTerrain {
  id: number;
  name: string;
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
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());
  const qc = useQueryClient();
  const { user } = useAuth();
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const peutRepartir = can(user, 'missions.dispatch');

  const { data: missions, refetch, isRefetching } = useQuery<MissionARepartir[]>({
    queryKey: ['company', 'missions'],
    queryFn: async () => (await apiClient.get('/provider/company/missions')).data.data ?? [],
  });

  /*
   * ON N'ENVOIE PAS UNE PERSONNE DANS UN IMMEUBLE DE DIX ÉTAGES.
   *
   * Le geste ordinaire d'une société est de confier la mission à une ÉQUIPE ; il n'existait sur
   * aucune surface. Composer une équipe demandait d'assigner un responsable puis N renforts, un par
   * un, sans que rien n'enregistre QUELLE équipe intervenait.
   */
  const { data: equipes } = useQuery<EquipeTerrain[]>({
    queryKey: ['company', 'field-teams'],
    queryFn: async () => (await apiClient.get('/provider/company/field-teams')).data.data ?? [],
  });

  const assignerLEquipe = useMutation({
    mutationFn: async ({ missionId, teamId }: { missionId: number; teamId: number }) => {
      await apiClient.post(`/provider/company/missions/${missionId}/assign-team`, {
        field_team_id: teamId,
      });
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['company', 'missions'] }),
    // Le serveur distingue le refus d'autorisation de l'équipe vide, et rend un message lisible :
    // le réécrire ici produirait deux formulations de la même règle.
    onError: (erreur: any) =>
      Alert.alert(
        'Assignation refusée',
        erreur?.data?.message ?? 'Votre rôle ne permet pas de répartir les missions.',
      ),
  });

  function proposerLEquipe(mission: MissionARepartir) {
    const actives = (equipes ?? []).filter((e) => e.status !== 'archived');

    if (actives.length === 0) {
      Alert.alert('Aucune équipe', "Créez d'abord une équipe terrain, puis composez-la.");

      return;
    }

    Alert.alert(
      'Confier à une équipe',
      mission.site ?? `Mission #${mission.id}`,
      [
        ...actives.slice(0, 10).map((e) => ({
          text: e.name,
          onPress: () => assignerLEquipe.mutate({ missionId: mission.id, teamId: e.id }),
        })),
        { text: 'Annuler', style: 'cancel' as const },
      ],
    );
  }

  /*
   * L'AUTO-ASSIGNATION — deux gestes distincts, et il faut les distinguer.
   *
   * Le BOUTON traite l'arriéré une fois, maintenant. Le MODE CONTINU est un réglage de société :
   * il agit sur des missions créées quand personne n'est devant l'application. Les confondre en un
   * seul interrupteur laisserait croire qu'appuyer suffit, ou au contraire qu'activer traite le
   * passé.
   */
  const { data: reglages } = useQuery<{ auto_assign_enabled: boolean }>({
    queryKey: ['company', 'auto-assign', 'settings'],
    queryFn: async () => (await apiClient.get('/provider/company/auto-assign/settings')).data.data,
    enabled: peutRepartir,
  });

  const lancerAuto = useMutation({
    mutationFn: async () => apiClient.post('/provider/company/missions/auto-assign'),
    onSuccess: () =>
      Alert.alert(
        'Répartition lancée',
        'Les missions sans personne sont en cours de traitement. Vous recevrez un résumé.',
      ),
    onError: (erreur: any) =>
      Alert.alert('Lancement refusé', erreur?.data?.message ?? 'Votre rôle ne le permet pas.'),
  });

  const basculerModeContinu = useMutation({
    mutationFn: async (actif: boolean) =>
      apiClient.put('/provider/company/auto-assign/settings', { auto_assign_enabled: actif }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['company', 'auto-assign', 'settings'] }),
    onError: (erreur: any) =>
      Alert.alert('Réglage refusé', erreur?.data?.message ?? 'Votre rôle ne le permet pas.'),
  });

  return (
    <Screen>
      <Text style={styles.title}>{tr('company_dispatch.repartition')}</Text>

      {peutRepartir && (
        <View style={styles.commandes}>
          <Button
            label={tr('company_dispatch.assigner_les_missions_sans_personne')}
            size="sm"
            fullWidth
            onPress={() => lancerAuto.mutate()}
            disabled={lancerAuto.isPending}
          />
          <Pressable
            accessibilityRole="switch"
            accessibilityState={{ checked: reglages?.auto_assign_enabled === true }}
            onPress={() => basculerModeContinu.mutate(!(reglages?.auto_assign_enabled === true))}
          >
            <Text style={styles.toggle}>
              {reglages?.auto_assign_enabled ? '✓ ' : '· '}
              Assigner automatiquement chaque nouvelle mission
            </Text>
          </Pressable>
        </View>
      )}

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

            <View style={styles.actions}>
              {/*
                L'ALERTE À DIX NOMS EST REMPLACÉE PAR UN ÉCRAN.

                `Alert.alert` plafonnait à dix boutons et n'affichait AUCUN indicateur de
                disponibilité : au-delà de dix personnes, les suivantes n'étaient pas proposables,
                et le répartiteur choisissait à l'aveugle. Le détail montre qui est libre.
              */}
              <Button
                label={item.lead ? 'Réassigner' : 'Assigner'}
                size="sm"
                variant="secondary"
                onPress={() => navigation.navigate('CompanyMissionDetail', { missionId: item.id })}
              />
              <Button
                label={tr('company_dispatch.equipe')}
                size="sm"
                variant="ghost"
                onPress={() => proposerLEquipe(item)}
              />
            </View>
          </View>
        )}
        ListEmptyComponent={
          <EmptyState title={tr('company_dispatch.aucune_mission')} message="Les missions confiées à votre société apparaîtront ici." />
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
    commandes: {
      gap: spacing.xs,
      marginBottom: spacing.md,
    },
    toggle: {
      fontSize: typography.fontSize.sm,
      color: t.textSecondary,
      paddingVertical: spacing.xs,
    },
    ligne: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: spacing.sm,
      paddingVertical: spacing.sm,
      borderBottomWidth: StyleSheet.hairlineWidth,
      borderBottomColor: t.border,
    },
    actions: {
      alignItems: 'flex-end',
      gap: spacing.xs,
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
