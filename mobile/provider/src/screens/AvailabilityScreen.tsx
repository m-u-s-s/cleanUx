import React from 'react';
import { View, FlatList, Text, Alert, StyleSheet } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Screen, Button, Badge, Divider } from '@/ui';
import { apiClient } from '@/api';
import { colors, spacing, typography, radius, shadows } from '@/theme';

interface Slot { id: number; day_of_week: number; start_time: string; end_time: string; }
const DAYS = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

export function AvailabilityScreen() {
  const { data: slots, isLoading } = useQuery<Slot[]>({
    queryKey: ['availability'],
    queryFn: async () => (await apiClient.get('/provider/availability')).data.data ?? [],
  });
  const qc = useQueryClient();
  const deleteSlot = useMutation({
    mutationFn: async (id: number) => { await apiClient.delete(`/provider/availability/slots/${id}`); },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['availability'] }),
  });

  return (
    <Screen scroll>
      <Text style={styles.title}>Mes disponibilités</Text>
      <FlatList
        data={slots ?? []}
        scrollEnabled={false}
        keyExtractor={i => String(i.id)}
        renderItem={({ item }) => (
          <View style={styles.slot}>
            <Badge label={DAYS[item.day_of_week] ?? '?'} variant="brand" />
            <Text style={styles.slotTime}>{item.start_time} — {item.end_time}</Text>
            <Button
              label="Suppr"
              size="sm"
              variant="ghost"
              onPress={() => Alert.alert('Supprimer ?', '', [
                { text: 'Non' },
                { text: 'Oui', style: 'destructive', onPress: () => deleteSlot.mutate(item.id) },
              ])}
            />
          </View>
        )}
        ListEmptyComponent={
          <Text style={styles.empty}>Aucune disponibilité configurée</Text>
        }
      />
      <Button
        label="Exporter iCal"
        onPress={async () => {
          try {
            await apiClient.get('/provider/availability/ical');
            Alert.alert('iCal', 'Lien copié');
          } catch {}
        }}
        variant="secondary"
        fullWidth
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
    marginBottom: spacing.md,
  },
  slot: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    paddingVertical: spacing.sm,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.surface[200],
  },
  slotTime: {
    flex: 1,
    fontSize: typography.fontSize.sm,
    color: colors.surface[700],
  },
  empty: {
    color: colors.surface[400],
    textAlign: 'center',
    marginTop: spacing.xl,
  },
});
