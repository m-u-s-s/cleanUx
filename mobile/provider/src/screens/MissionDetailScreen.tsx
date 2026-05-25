import React from 'react';
import { View, Text, Alert, StyleSheet } from 'react-native';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { useNavigation } from '@react-navigation/native';
import { Screen, Button, Badge, Divider } from '@/ui';
import { useMissionDetail, useMissionLifecycle } from '@/missions';
import { colors, spacing, typography, radius, shadows, useThemeColors } from '@/theme';
import type { RootStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<RootStackParamList, 'MissionDetail'>;

export function MissionDetailScreen({ route }: Props) {
  const { missionId } = route.params;
  const { data: mission, isLoading } = useMissionDetail(missionId);
  const lifecycle = useMissionLifecycle(missionId);
  const navigation = useNavigation<any>();
  const themeColors = useThemeColors();

  if (isLoading || !mission) {
    return (
      <Screen>
        <Text style={styles.loading}>Chargement...</Text>
      </Screen>
    );
  }

  const handleAction = (action: 'start' | 'arrive' | 'complete', label: string) => {
    Alert.alert(label, `Confirmer "${label}" ?`, [
      { text: 'Annuler', style: 'cancel' },
      { text: 'Confirmer', onPress: () => lifecycle.mutate(action) },
    ]);
  };

  const badgeVariant =
    mission.status === 'completed'
      ? 'success'
      : mission.status === 'cancelled'
        ? 'danger'
        : 'brand';

  return (
    <Screen scroll>
      <View style={styles.header}>
        <Text style={styles.title}>{mission.service_name}</Text>
        <Badge label={mission.status} variant={badgeVariant} />
      </View>
      <View style={[styles.card, { backgroundColor: themeColors.card }]}>
        <DetailRow label="Client" value={mission.client_name} />
        <Divider />
        <DetailRow label="Adresse" value={`${mission.address}, ${mission.city}`} />
        <Divider />
        <DetailRow
          label="Date"
          value={`${mission.scheduled_date} à ${mission.scheduled_time}`}
        />
        {mission.total_price != null && (
          <>
            <Divider />
            <DetailRow label="Prix" value={`${mission.total_price} €`} />
          </>
        )}
      </View>
      <View style={styles.actions}>
        {mission.status === 'assigned' && (
          <Button
            label="En route"
            onPress={() => handleAction('start', 'En route')}
            fullWidth
          />
        )}
        {mission.status === 'en_route' && (
          <Button
            label="Arrivé"
            onPress={() => handleAction('arrive', 'Arrivé sur site')}
            fullWidth
          />
        )}
        {mission.status === 'arrived' && (
          <Button
            label="Démarrer mission"
            onPress={() => handleAction('start', 'Démarrer')}
            fullWidth
          />
        )}
        {mission.status === 'in_progress' && (
          <>
            <Button
              label="Mission terrain"
              onPress={() => navigation.navigate('MissionField', { missionId })}
              fullWidth
            />
            <Button
              label="Mission terminée"
              onPress={() => handleAction('complete', 'Terminer')}
              variant="danger"
              fullWidth
            />
          </>
        )}
      </View>
    </Screen>
  );
}

function DetailRow({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.row}>
      <Text style={styles.rowLabel}>{label}</Text>
      <Text style={styles.rowValue}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  loading: {
    color: colors.surface[500],
    textAlign: 'center',
    marginTop: spacing.xl,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: spacing.lg,
    marginTop: spacing.md,
  },
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
  },
  card: {
    borderRadius: radius.md,
    padding: spacing.md,
    ...shadows.soft,
    marginBottom: spacing.lg,
  },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: spacing.sm,
  },
  rowLabel: {
    fontSize: typography.fontSize.sm,
    color: colors.surface[500],
  },
  rowValue: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.medium,
    color: colors.surface[900],
    flex: 1,
    textAlign: 'right',
    marginLeft: spacing.sm,
  },
  actions: {
    gap: spacing.sm,
  },
});
