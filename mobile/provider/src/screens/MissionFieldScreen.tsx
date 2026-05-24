import React, { useState, useCallback } from 'react';
import { View, Text, FlatList, Switch, StyleSheet } from 'react-native';
import { Screen, Button, Badge, Divider } from '@/ui';
import { useMissionDetail, useMissionLifecycle } from '@/missions';
import { useInspection, useToggleChecklistItem } from '@/inspection';
import { useGpsWatcher, usePushPosition } from '@/tracking';
import { colors, spacing, typography, radius, shadows } from '@/theme';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { RootStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<RootStackParamList, 'MissionField'>;

export function MissionFieldScreen({ route }: Props) {
  const { missionId } = route.params;
  const { data: mission } = useMissionDetail(missionId);
  const { data: inspection } = useInspection(missionId);
  const toggleItem = useToggleChecklistItem();
  const pushPosition = usePushPosition(missionId);
  const lifecycle = useMissionLifecycle(missionId);
  const [gpsActive, setGpsActive] = useState(true);

  useGpsWatcher(
    gpsActive && mission?.status === 'in_progress',
    useCallback(
      (pos) => {
        pushPosition.mutate({ latitude: pos.latitude, longitude: pos.longitude, speed: pos.speed ?? undefined });
      },
      [pushPosition],
    ),
  );

  if (!mission) return <Screen><Text>Chargement...</Text></Screen>;

  return (
    <Screen scroll>
      <View style={styles.header}>
        <Text style={styles.title}>{mission.service_name}</Text>
        <Badge label={mission.status} variant="success" />
      </View>

      <View style={styles.clientCard}>
        <Text style={styles.clientName}>{mission.client_name}</Text>
        <Text style={styles.clientAddress}>{mission.address}, {mission.city}</Text>
      </View>

      {inspection && (
        <View style={styles.section}>
          <Text style={styles.sectionTitle}>Checklist</Text>
          <FlatList
            data={inspection.items}
            scrollEnabled={false}
            keyExtractor={(i) => String(i.id)}
            renderItem={({ item }) => (
              <View style={styles.checkItem}>
                <Text style={styles.checkLabel}>{item.label}</Text>
                <Switch
                  value={item.completed}
                  onValueChange={(v) =>
                    toggleItem.mutate({ inspectionId: inspection.id, itemId: item.id, value: v })
                  }
                  trackColor={{ true: colors.success[500] }}
                />
              </View>
            )}
          />
        </View>
      )}

      <View style={styles.gpsRow}>
        <Text style={styles.gpsLabel}>Partage GPS actif</Text>
        <Switch
          value={gpsActive}
          onValueChange={setGpsActive}
          trackColor={{ true: colors.brand[500] }}
        />
      </View>

      <View style={styles.actions}>
        {mission.status === 'in_progress' && (
          <Button
            label="Terminer la mission"
            onPress={() => lifecycle.mutate('complete')}
            variant="danger"
            fullWidth
            size="lg"
          />
        )}
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: spacing.md,
    marginBottom: spacing.md,
  },
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
  },
  clientCard: {
    backgroundColor: '#fff',
    borderRadius: radius.md,
    padding: spacing.md,
    ...shadows.xs,
    marginBottom: spacing.md,
  },
  clientName: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[900],
  },
  clientAddress: {
    fontSize: typography.fontSize.sm,
    color: colors.surface[500],
    marginTop: 2,
  },
  section: { marginBottom: spacing.md },
  sectionTitle: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[800],
    marginBottom: spacing.sm,
  },
  checkItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: spacing.sm,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.surface[200],
  },
  checkLabel: {
    fontSize: typography.fontSize.sm,
    color: colors.surface[700],
    flex: 1,
  },
  gpsRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: spacing.md,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: colors.surface[200],
  },
  gpsLabel: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.medium,
    color: colors.surface[700],
  },
  actions: { marginTop: spacing.lg },
});
