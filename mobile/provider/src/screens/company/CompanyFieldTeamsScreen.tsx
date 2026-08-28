import React from 'react';
import { View, FlatList, Text, TextInput, Alert, StyleSheet, Pressable } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Screen, Button, Badge, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { useAuth, can } from '@/auth';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

interface EquipeTerrain {
  id: number;
  name: string;
  status: string;
  zone: string | null;
  lead: string | null;
  max_concurrent_missions: number | null;
}

interface MembreDEquipe {
  id: number;
  user_id: number;
  name: string | null;
  email: string | null;
  is_team_lead: boolean;
}

interface MembreDeLaSociete {
  id: number;
  user_id: number;
  name: string | null;
  role: string;
  status: string;
}

/**
 * Les agences de la société, en natif.
 *
 * Jusqu'à la phase 2, seuls les écrans d'administration de la plateforme savaient créer une équipe
 * terrain : une société devait demander l'ouverture de chacune de ses agences. L'écran web puis
 * celui-ci lui en rendent la main.
 */
export function CompanyFieldTeamsScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());
  const qc = useQueryClient();
  const { user } = useAuth();

  const [nom, setNom] = React.useState('');

  const { data: equipes, refetch, isRefetching } = useQuery<EquipeTerrain[]>({
    queryKey: ['company', 'field-teams'],
    queryFn: async () => (await apiClient.get('/provider/company/field-teams')).data.data ?? [],
  });

  const creer = useMutation({
    mutationFn: async (name: string) => {
      await apiClient.post('/provider/company/field-teams', { name });
    },
    onSuccess: () => {
      setNom('');
      qc.invalidateQueries({ queryKey: ['company', 'field-teams'] });
    },
    // L'API refuse un rôle sans `team.create` : on le dit, plutôt que de laisser l'écran muet.
    onError: () => Alert.alert('Création refusée', "Votre rôle ne permet pas d'ouvrir une agence."),
  });

  const archiver = useMutation({
    mutationFn: async (id: number) => {
      await apiClient.patch(`/provider/company/field-teams/${id}/archive`);
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['company', 'field-teams'] }),
    onError: () => Alert.alert('Archivage refusé', 'Votre rôle ne permet pas cette action.'),
  });

  /*
   * LA COMPOSITION SE DÉPLIE SOUS L'ÉQUIPE.
   *
   * Une équipe VIDE ne peut recevoir aucune mission, et `field_team_members` n'était manipulable
   * que depuis l'administration de la plateforme : une société qui créait son équipe ici ne pouvait
   * pas la peupler, et devait appeler un administrateur. L'écran affichait donc des coquilles.
   */
  const [equipeOuverte, setEquipeOuverte] = React.useState<number | null>(null);

  const peutComposer = can(user, 'team.manage');

  const { data: composition } = useQuery<{ members: MembreDEquipe[] }>({
    queryKey: ['company', 'field-teams', equipeOuverte, 'members'],
    queryFn: async () =>
      (await apiClient.get(`/provider/company/field-teams/${equipeOuverte}/members`)).data.data,
    enabled: equipeOuverte !== null,
  });

  // Le vivier dans lequel on recrute : les membres de la société, chargés seulement quand on
  // s'apprête à composer.
  const { data: collegues } = useQuery<MembreDeLaSociete[]>({
    queryKey: ['company', 'members'],
    queryFn: async () => (await apiClient.get('/provider/company/members')).data.data ?? [],
    enabled: equipeOuverte !== null && peutComposer,
  });

  const composer = useMutation({
    mutationFn: async (params: { teamId: number; userId: number; retirer: boolean }) =>
      params.retirer
        ? apiClient.delete(`/provider/company/field-teams/${params.teamId}/members/${params.userId}`)
        : apiClient.post(`/provider/company/field-teams/${params.teamId}/members`, {
            user_id: params.userId,
          }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['company', 'field-teams'] });
    },
    onError: (erreur: any) =>
      Alert.alert(
        'Action refusée',
        erreur?.data?.message ?? 'Votre rôle ne permet pas de composer les équipes.',
      ),
  });

  return (
    <Screen>
      <Text style={styles.title}>{tr('company_field_teams.equipes_terrain')}</Text>

      <View style={styles.formulaire}>
        <TextInput
          value={nom}
          onChangeText={setNom}
          placeholder="Nom de l'agence"
          placeholderTextColor={styles.placeholder.color}
          style={styles.champ}
          testID="champ-nom-equipe"
        />
        <Button
          label="Créer"
          size="sm"
          onPress={() => nom.trim() && creer.mutate(nom.trim())}
          disabled={creer.isPending || nom.trim().length === 0}
        />
      </View>

      <FlatList
        data={equipes ?? []}
        keyExtractor={(e) => String(e.id)}
        onRefresh={refetch}
        refreshing={isRefetching}
        renderItem={({ item }) => (
          <View>
            <Pressable
              style={styles.ligne}
              testID={`equipe-${item.id}`}
              accessibilityRole="button"
              onPress={() => setEquipeOuverte(equipeOuverte === item.id ? null : item.id)}
            >
              <View style={styles.identite}>
                <Text style={styles.nom} numberOfLines={1}>
                  {item.name}
                </Text>
                <Text style={styles.detail} numberOfLines={1}>
                  {item.zone ?? 'Aucune zone'} · {item.lead ?? 'Sans responsable'}
                  {item.max_concurrent_missions ? ` · ${item.max_concurrent_missions} en parallèle` : ''}
                </Text>
              </View>

              {item.status === 'archived' ? (
                <Badge label="Archivée" variant="neutral" />
              ) : (
                <Button
                  label="Archiver"
                  size="sm"
                  variant="ghost"
                  onPress={() => archiver.mutate(item.id)}
                />
              )}
            </Pressable>

            {equipeOuverte === item.id && (
              <View style={styles.composition} testID={`composition-${item.id}`}>
                <Text style={styles.section}>Composition</Text>

                {(composition?.members ?? []).length === 0 && (
                  <Text style={styles.detail}>
                    {tr('company_field_teams.aucun_membre_une_equipe_vide')}
                  </Text>
                )}

                {(composition?.members ?? []).map((membre) => (
                  <View key={membre.id} style={styles.ligneMembre}>
                    <Text style={styles.nomMembre} numberOfLines={1}>
                      {membre.name ?? 'Utilisateur supprimé'}
                      {membre.is_team_lead ? ' · responsable' : ''}
                    </Text>
                    {peutComposer && (
                      <Button
                        label="Retirer"
                        size="sm"
                        variant="ghost"
                        onPress={() =>
                          composer.mutate({ teamId: item.id, userId: membre.user_id, retirer: true })
                        }
                      />
                    )}
                  </View>
                ))}

                {peutComposer && (
                  <>
                    <Text style={styles.section}>{tr('company_field_teams.ajouter_un_collegue')}</Text>
                    {(collegues ?? [])
                      .filter((c) => c.status === 'active')
                      .filter(
                        (c) => !(composition?.members ?? []).some((m) => m.user_id === c.user_id),
                      )
                      .map((collegue) => (
                        <View key={collegue.id} style={styles.ligneMembre}>
                          <Text style={styles.nomMembre} numberOfLines={1}>
                            {collegue.name ?? '—'}
                          </Text>
                          <Button
                            label="Ajouter"
                            size="sm"
                            variant="secondary"
                            onPress={() =>
                              composer.mutate({
                                teamId: item.id,
                                userId: collegue.user_id,
                                retirer: false,
                              })
                            }
                          />
                        </View>
                      ))}
                  </>
                )}
              </View>
            )}
          </View>
        )}
        ListEmptyComponent={
          <EmptyState
            title="Aucune agence"
            message="Créez une équipe terrain pour organiser vos interventions par zone."
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
    formulaire: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: spacing.sm,
      marginBottom: spacing.md,
    },
    champ: {
      flex: 1,
      borderWidth: StyleSheet.hairlineWidth,
      borderColor: t.border,
      borderRadius: radius.md,
      paddingHorizontal: spacing.sm,
      paddingVertical: spacing.xs,
      color: t.text,
      backgroundColor: t.card,
    },
    placeholder: {
      color: t.textMuted,
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
    composition: {
      paddingLeft: spacing.sm,
      paddingBottom: spacing.sm,
      borderLeftWidth: 2,
      borderLeftColor: t.border,
    },
    section: {
      fontSize: typography.fontSize.sm,
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
      marginTop: spacing.sm,
      marginBottom: spacing.xs,
    },
    ligneMembre: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',
      gap: spacing.sm,
      paddingVertical: spacing.xs,
    },
    nomMembre: {
      flex: 1,
      fontSize: typography.fontSize.sm,
      color: t.text,
    },
    detail: {
      fontSize: typography.fontSize.sm,
      color: t.textMuted,
    },
  });
