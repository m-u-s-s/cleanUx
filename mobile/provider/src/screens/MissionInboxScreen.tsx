import React from 'react';
import { FlatList, View, Text, Alert, StyleSheet, RefreshControl } from 'react-native';
import Animated, { FadeIn } from 'react-native-reanimated';
import { Screen, Button, Badge, Skeleton, EmptyState, AnimatedListItem } from '@/ui';
import { useMissionInbox, useAcceptMission, useDeclineMission } from '@/missions';
import type { MissionAssignment } from '@/missions';
import { colors, spacing, typography, radius, shadows, useThemeColors } from '@/theme';

export function MissionInboxScreen() {
  const { data: assignments, isLoading, refetch, isRefetching } = useMissionInbox();
  const accept = useAcceptMission();
  const decline = useDeclineMission();
  const themeColors = useThemeColors();

  const handleAccept = (a: MissionAssignment) => {
    Alert.alert('Accepter', `Accepter la mission ${a.service_name} ?`, [
      { text: 'Annuler', style: 'cancel' },
      { text: 'Accepter', onPress: () => accept.mutate(a.id) },
    ]);
  };

  const handleDecline = (a: MissionAssignment) => {
    Alert.alert('Décliner', 'Décliner cette mission ?', [
      { text: 'Annuler', style: 'cancel' },
      { text: 'Décliner', style: 'destructive', onPress: () => decline.mutate(a.id) },
    ]);
  };

  return (
    <Screen>
      <Text style={styles.title}>Missions disponibles</Text>
      {isLoading ? (
        <Skeleton width="100%" height={120} />
      ) : (
        <Animated.View entering={FadeIn.duration(280)} style={{ flex: 1 }}>
          <FlatList
            data={assignments ?? []}
            keyExtractor={i => String(i.id)}
            renderItem={({ item, index }) => (
              <AnimatedListItem index={index}>
              <View style={[styles.card, { backgroundColor: themeColors.card }]}>
                <Text style={styles.service}>{item.service_name}</Text>
                <Text style={styles.client}>{item.client_name}</Text>
                <Text style={styles.address}>
                  {item.address}, {item.city}
                </Text>
                <Text style={styles.schedule}>
                  {item.scheduled_date} à {item.scheduled_time}
                </Text>
                {item.distance_km != null && (
                  <Badge label={`${item.distance_km.toFixed(1)} km`} variant="brand" />
                )}
                <View style={styles.actions}>
                  <Button label="Accepter" onPress={() => handleAccept(item)} size="sm" />
                  <Button
                    label="Décliner"
                    onPress={() => handleDecline(item)}
                    variant="ghost"
                    size="sm"
                  />
                </View>
              </View>
              </AnimatedListItem>
            )}
            refreshControl={
              <RefreshControl
                refreshing={isRefetching}
                onRefresh={refetch}
                tintColor={colors.brand[500]}
                colors={[colors.brand[500]]}
              />
            }
            ListEmptyComponent={<EmptyState title="Aucune mission en attente" message="Les nouvelles missions vous seront proposées ici." />}
          />
        </Animated.View>
      )}
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
  card: {
    borderRadius: radius.md,
    padding: spacing.md,
    ...shadows.soft,
    marginBottom: spacing.sm,
  },
  service: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[900],
  },
  client: {
    fontSize: typography.fontSize.sm,
    color: colors.surface[600],
    marginTop: 2,
  },
  address: {
    fontSize: typography.fontSize.xs,
    color: colors.surface[500],
    marginTop: 2,
  },
  schedule: {
    fontSize: typography.fontSize.xs,
    color: colors.brand[600],
    marginTop: spacing.xs,
  },
  actions: {
    flexDirection: 'row',
    gap: spacing.sm,
    marginTop: spacing.sm,
  },
  empty: {
    color: colors.surface[400],
    textAlign: 'center',
    marginTop: spacing.xl,
  },
});
