import React from 'react';
import { FlatList, View, Text, StyleSheet } from 'react-native';
import { Screen, Button, Skeleton } from '@/ui';
import { useNotifications, useMarkAllRead } from '@/notifications';
import type { AppNotification } from '@/notifications';
import { colors, spacing, typography } from '@/theme';

export function NotificationsScreen() {
  const { data: notifs, isLoading, refetch } = useNotifications();
  const markAll = useMarkAllRead();
  const unreadCount = notifs?.filter(n => !n.read_at).length ?? 0;

  return (
    <Screen>
      <View style={styles.header}>
        <Text style={styles.title}>Notifications</Text>
        {unreadCount > 0 && (
          <Button
            label="Tout marquer lu"
            onPress={() => markAll.mutate()}
            size="sm"
            variant="ghost"
          />
        )}
      </View>
      {isLoading ? (
        <View style={styles.skeletons}>
          {[1, 2, 3, 4].map(i => <Skeleton key={i} width="100%" height={60} />)}
        </View>
      ) : (
        <FlatList
          data={notifs ?? []}
          keyExtractor={item => item.id}
          renderItem={({ item }: { item: AppNotification }) => (
            <View style={[styles.notif, !item.read_at && styles.unread]}>
              <Text style={styles.notifTitle}>{item.title}</Text>
              <Text style={styles.notifBody}>{item.body}</Text>
              <Text style={styles.notifTime}>
                {new Date(item.created_at).toLocaleDateString()}
              </Text>
            </View>
          )}
          onRefresh={refetch}
          refreshing={isLoading}
          ListEmptyComponent={
            <Text style={styles.empty}>Aucune notification</Text>
          }
        />
      )}
    </Screen>
  );
}

const styles = StyleSheet.create({
  header: {
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
  skeletons: { gap: spacing.sm },
  notif: {
    paddingVertical: spacing.sm,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.surface[200],
  },
  unread: { backgroundColor: colors.brand[50] },
  notifTitle: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[900],
  },
  notifBody: {
    fontSize: typography.fontSize.xs,
    color: colors.surface[600],
    marginTop: 2,
  },
  notifTime: {
    fontSize: 10,
    color: colors.surface[400],
    marginTop: 4,
  },
  empty: {
    fontSize: typography.fontSize.sm,
    color: colors.surface[400],
    textAlign: 'center',
    marginTop: spacing.xl,
  },
});
