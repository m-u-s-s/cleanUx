import React from 'react';
import { View, FlatList, Text, TextInput, Alert, StyleSheet } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Screen, Button, Badge, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

interface EquipeTerrain {
  id: number;
  name: string;
  status: string;
  zone: string | null;
  lead: string | null;
  max_concurrent_missions: number | null;
}

/**
 * Les agences de la société, en natif.
 *
 * Jusqu'à la phase 2, seuls les écrans d'administration de la plateforme savaient créer une équipe
 * terrain : une société devait demander l'ouverture de chacune de ses agences. L'écran web puis
 * celui-ci lui en rendent la main.
 */
export function CompanyFieldTeamsScreen() {
  const styles = stylesFor(useThemeColors());
  const qc = useQueryClient();

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

  return (
    <Screen>
      <Text style={styles.title}>Équipes terrain</Text>

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
          <View style={styles.ligne} testID={`equipe-${item.id}`}>
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
    detail: {
      fontSize: typography.fontSize.sm,
      color: t.textMuted,
    },
  });
