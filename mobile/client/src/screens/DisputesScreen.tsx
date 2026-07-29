import React, { useState } from 'react';
import { FlatList, View, Text, StyleSheet, Pressable, Alert } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Screen, Badge, Skeleton, EmptyState, ErrorState, Button, TextInput } from '@/ui';
import { apiClient } from '@/api';
import { colors, spacing, typography, radius } from '@/theme';

const CATEGORIES: { value: string; label: string }[] = [
  { value: 'quality', label: 'Qualité' },
  { value: 'no_show', label: 'Absence' },
  { value: 'payment', label: 'Paiement' },
  { value: 'damage', label: 'Dommage' },
  { value: 'safety', label: 'Sécurité' },
  { value: 'communication', label: 'Communication' },
  { value: 'other', label: 'Autre' },
];

export function DisputesScreen() {
  const queryClient = useQueryClient();
  const { data, isLoading, isError, refetch, isRefetching } = useQuery({
    queryKey: ['disputes'],
    queryFn: async () => {
      const res = await apiClient.get('/client/disputes');
      return res.data.data ?? [];
    },
  });

  const [showForm, setShowForm] = useState(false);
  const [subject, setSubject] = useState('');
  const [description, setDescription] = useState('');
  const [category, setCategory] = useState<string | null>(null);

  const create = useMutation({
    mutationFn: async () =>
      (await apiClient.post('/client/disputes', { subject, description, category })).data,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['disputes'] });
      setShowForm(false);
      setSubject('');
      setDescription('');
      setCategory(null);
      Alert.alert('Litige ouvert', 'Notre équipe va le traiter dans les meilleurs délais.');
    },
    onError: () => Alert.alert('Échec', "Le litige n'a pas pu être ouvert. Réessayez."),
  });

  const canSubmit = subject.trim().length >= 3 && description.trim().length >= 10 && !!category && !create.isPending;

  if (isError) return <Screen><ErrorState message="Impossible de charger vos litiges." onRetry={refetch} /></Screen>;

  return (
    <Screen>
      <View style={styles.headerRow}>
        <Text style={styles.title}>Mes litiges</Text>
        <Button label={showForm ? 'Fermer' : 'Ouvrir un litige'} size="sm" onPress={() => setShowForm(v => !v)} />
      </View>

      {showForm && (
        <View style={styles.form}>
          <TextInput label="Sujet" placeholder="Sujet" value={subject} onChangeText={setSubject} />
          <TextInput
            label="Description"
            placeholder="Décrivez le problème"
            value={description}
            onChangeText={setDescription}
            multiline
          />
          <Text style={styles.formLabel}>Catégorie</Text>
          <View style={styles.chips}>
            {CATEGORIES.map(cat => (
              <Pressable
                key={cat.value}
                onPress={() => setCategory(cat.value)}
                style={[styles.chip, category === cat.value && styles.chipActive]}
              >
                <Text style={[styles.chipText, category === cat.value && styles.chipTextActive]}>{cat.label}</Text>
              </Pressable>
            ))}
          </View>
          <Button label="Envoyer" onPress={() => create.mutate()} disabled={!canSubmit} loading={create.isPending} />
        </View>
      )}

      {isLoading ? (
        <Skeleton width="100%" height={80} />
      ) : (
        <FlatList
          data={data ?? []}
          keyExtractor={(item: any) => String(item.id)}
          renderItem={({ item }: { item: any }) => (
            <View style={styles.card}>
              <View style={styles.cardHeader}>
                <Text style={styles.cardTitle}>Litige #{item.id}</Text>
                <Badge
                  label={item.status}
                  variant={item.status === 'resolved' ? 'success' : 'warning'}
                />
              </View>
              <Text style={styles.cardDesc}>{item.reason ?? item.description ?? ''}</Text>
            </View>
          )}
          onRefresh={refetch}
          refreshing={isRefetching}
          ListEmptyComponent={<EmptyState title="Aucun litige" message="Vous n'avez aucun litige en cours." icon="shield-outline" />}
        />
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: spacing.md,
  },
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
  },
  form: {
    backgroundColor: '#fff',
    borderRadius: radius.md,
    padding: spacing.md,
    marginBottom: spacing.md,
    gap: spacing.sm,
  },
  formLabel: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[700],
  },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
  chip: {
    paddingVertical: spacing.xs,
    paddingHorizontal: spacing.sm,
    // `radius.full` n'existe pas dans l'échelle : elle s'arrête à `pill`. La valeur était donc
    // `undefined` et les puces s'affichaient à angles droits, sans que rien ne le signale.
    borderRadius: radius.pill,
    borderWidth: 1,
    borderColor: colors.surface[300],
  },
  chipActive: { backgroundColor: colors.brand[500], borderColor: colors.brand[500] },
  chipText: { fontSize: typography.fontSize.xs, color: colors.surface[700] },
  chipTextActive: { color: '#fff', fontWeight: typography.fontWeight.semibold },
  card: {
    padding: spacing.md,
    backgroundColor: '#fff',
    borderRadius: radius.md,
    marginBottom: spacing.sm,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: spacing.xs,
  },
  cardTitle: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[900],
  },
  cardDesc: { fontSize: typography.fontSize.xs, color: colors.surface[500], marginTop: spacing.xs },
  empty: { color: colors.surface[400], textAlign: 'center', marginTop: spacing.xl },
});
