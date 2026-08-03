import React, { useState, useCallback } from 'react';
import { View, Text, FlatList, Switch, StyleSheet } from 'react-native';
import { Screen, Button, Badge, Divider } from '@/ui';
import { useMissionDetail, useMissionLifecycle } from '@/missions';
import { useInspection, useToggleChecklistItem } from '@/inspection';
import { useGpsWatcher, usePushPosition } from '@/tracking';
import { colors, spacing, typography, radius, shadows } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { RootStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<RootStackParamList, 'MissionField'>;

export function MissionFieldScreen({ route }: Props) {
  const styles = stylesFor(useThemeColors());

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

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
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
    color: t.text,
  },
  clientCard: {
    backgroundColor: t.card,
    borderRadius: radius.md,
    padding: spacing.md,
    ...shadows.xs,
    marginBottom: spacing.md,
  },
  clientName: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
  },
  clientAddress: {
    fontSize: typography.fontSize.sm,
    color: t.textSecondary,
    marginTop: 2,
  },
  section: { marginBottom: spacing.md },
  sectionTitle: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
    marginBottom: spacing.sm,
  },
  checkItem: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: spacing.sm,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: t.border,
  },
  checkLabel: {
    fontSize: typography.fontSize.sm,
    color: t.text,
    flex: 1,
  },
  gpsRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: spacing.md,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: t.border,
  },
  gpsLabel: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.medium,
    color: t.text,
  },
  actions: { marginTop: spacing.lg },
});
