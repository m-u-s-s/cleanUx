import React from 'react';
import { View, FlatList, Text, TextInput, Alert, StyleSheet } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Screen, Button, Badge, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

interface Tache {
  id: number;
  title: string;
  description: string | null;
  status: string;
  priority: string;
}

const LIBELLES_STATUT: Record<string, string> = {
  todo: 'À faire',
  in_progress: 'En cours',
  done: 'Terminée',
  cancelled: 'Annulée',
};

/** L'étape suivante dans le cycle de vie d'une tâche, ou `null` si elle est close. */
function statutSuivant(statut: string): string | null {
  if (statut === 'todo') return 'in_progress';
  if (statut === 'in_progress') return 'done';

  return null;
}

/**
 * Le tableau de tâches de la société, en natif.
 *
 * L'API applique la même règle que l'écran web : déplacer une tâche demande `tasks.create`, sauf
 * pour son créateur qui garde la main sur la sienne. Les deux surfaces ne doivent pas diverger.
 */
export function CompanyTasksScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());
  const qc = useQueryClient();

  const [titre, setTitre] = React.useState('');

  const { data: taches, refetch, isRefetching } = useQuery<Tache[]>({
    queryKey: ['company', 'tasks'],
    queryFn: async () => (await apiClient.get('/provider/company/tasks')).data.data ?? [],
  });

  const creer = useMutation({
    mutationFn: async (title: string) => {
      await apiClient.post('/provider/company/tasks', { title, priority: 'medium' });
    },
    onSuccess: () => {
      setTitre('');
      qc.invalidateQueries({ queryKey: ['company', 'tasks'] });
    },
    onError: () => Alert.alert('Création refusée', 'Votre rôle ne permet pas de créer une tâche.'),
  });

  const deplacer = useMutation({
    mutationFn: async ({ id, status }: { id: number; status: string }) => {
      await apiClient.patch(`/provider/company/tasks/${id}`, { status });
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['company', 'tasks'] }),
    onError: () => Alert.alert('Déplacement refusé', 'Votre rôle ne permet pas cette action.'),
  });

  return (
    <Screen>
      <Text style={styles.title}>{tr('company_tasks.taches')}</Text>

      <View style={styles.formulaire}>
        <TextInput
          value={titre}
          onChangeText={setTitre}
          placeholder="Nouvelle tâche"
          placeholderTextColor={styles.placeholder.color}
          style={styles.champ}
          testID="champ-titre-tache"
        />
        <Button
          label="Ajouter"
          size="sm"
          onPress={() => titre.trim() && creer.mutate(titre.trim())}
          disabled={creer.isPending || titre.trim().length === 0}
        />
      </View>

      <FlatList
        data={taches ?? []}
        keyExtractor={(t) => String(t.id)}
        onRefresh={refetch}
        refreshing={isRefetching}
        renderItem={({ item }) => {
          const suivant = statutSuivant(item.status);

          return (
            <View style={styles.ligne} testID={`tache-${item.id}`}>
              <View style={styles.identite}>
                <Text style={styles.nom} numberOfLines={2}>
                  {item.title}
                </Text>
                <Badge label={LIBELLES_STATUT[item.status] ?? item.status} variant="neutral" />
              </View>

              {suivant && (
                <Button
                  label={LIBELLES_STATUT[suivant] ?? suivant}
                  size="sm"
                  variant="secondary"
                  onPress={() => deplacer.mutate({ id: item.id, status: suivant })}
                />
              )}
            </View>
          );
        }}
        ListEmptyComponent={
          <EmptyState title="Aucune tâche" message="Ajoutez une tâche pour organiser le travail de l'équipe." />
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
      gap: spacing.xs,
      alignItems: 'flex-start',
    },
    nom: {
      fontSize: typography.fontSize.base,
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
    },
  });
