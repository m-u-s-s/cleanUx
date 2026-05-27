import React, { useCallback } from 'react';
import { FlatList, View, Text, StyleSheet, RefreshControl, TouchableOpacity } from 'react-native';
import Animated, { FadeIn } from 'react-native-reanimated';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { useQuery } from '@tanstack/react-query';
import { Screen, Badge, Skeleton, EmptyState, AnimatedListItem } from '@/ui';
import { apiClient } from '@/api';
import { colors, spacing, typography, radius, shadows, useThemeColors } from '@/theme';
import type { RootStackParamList } from '@/navigation/types';

interface ActiveMission {
  id: number;
  status: string;
  service_name: string;
  client_name: string;
  address: string;
  city: string;
  scheduled_date: string;
  scheduled_time: string;
}

function useActiveMissions() {
  return useQuery<ActiveMission[]>({
    queryKey: ['provider', 'missions', 'active'],
    queryFn: async () => {
      const res = await apiClient.get('/provider/missions/active');
      return res.data.data ?? res.data ?? [];
    },
    refetchInterval: 30000,
  });
}

function statusBadgeVariant(status: string): 'success' | 'warning' | 'brand' | 'danger' | 'neutral' {
  if (status === 'completed') return 'success';
  if (status === 'cancelled') return 'danger';
  if (status === 'in_progress' || status === 'arrived') return 'warning';
  return 'brand';
}

export function MissionsListScreen() {
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const { data: missions, isLoading, refetch, isRefetching } = useActiveMissions();
  const themeColors = useThemeColors();

  const renderMission = useCallback(
    ({ item, index }: { item: ActiveMission; index: number }) => (
      <AnimatedListItem index={index}>
        <TouchableOpacity
          style={[styles.card, { backgroundColor: themeColors.card }]}
          onPress={() => navigation.navigate('MissionDetail', { missionId: item.id })}
          accessibilityLabel={`Mission ${item.service_name} — ${item.status}`}
          accessibilityRole="button"
        >
          <View style={styles.cardHeader}>
            <Text style={styles.service}>{item.service_name}</Text>
            <Badge label={item.status} variant={statusBadgeVariant(item.status)} />
          </View>
          <Text style={styles.client}>{item.client_name}</Text>
          <Text style={styles.address}>
            {item.address}, {item.city}
          </Text>
          <Text style={styles.schedule}>
            {item.scheduled_date} à {item.scheduled_time}
          </Text>
        </TouchableOpacity>
      </AnimatedListItem>
    ),
    [navigation, themeColors.card],
  );

  return (
    <Screen testID="missions-list-screen">
      <Text style={styles.title} accessibilityRole="header">
        Mes missions
      </Text>
      {isLoading ? (
        <>
          <Skeleton width="100%" height={100} />
          <Skeleton width="100%" height={100} />
        </>
      ) : (
        <Animated.View entering={FadeIn.duration(280)} style={styles.listContainer}>
          <FlatList
            data={missions ?? []}
            keyExtractor={(i) => String(i.id)}
            renderItem={renderMission}
            refreshControl={
              <RefreshControl
                refreshing={isRefetching}
                onRefresh={refetch}
                tintColor={colors.brand[500]}
                colors={[colors.brand[500]]}
              />
            }
            ListEmptyComponent={
              <EmptyState
                title="Aucune mission active"
                message="Vos missions actives et à venir apparaîtront ici."
              />
            }
            contentContainerStyle={styles.listContent}
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
  listContainer: { flex: 1 },
  listContent: { paddingBottom: spacing.xl },
  card: {
    borderRadius: radius.md,
    padding: spacing.md,
    ...shadows.soft,
    marginBottom: spacing.sm,
  },
  cardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: spacing.xs,
  },
  service: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[900],
    flex: 1,
    marginRight: spacing.sm,
  },
  client: { fontSize: typography.fontSize.sm, color: colors.surface[600], marginTop: 2 },
  address: { fontSize: typography.fontSize.xs, color: colors.surface[500], marginTop: 2 },
  schedule: {
    fontSize: typography.fontSize.xs,
    color: colors.brand[600],
    marginTop: spacing.xs,
  },
});
