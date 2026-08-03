import React, { useCallback } from 'react';
import { FlatList, View, Text, StyleSheet, RefreshControl } from 'react-native';
import { Screen, Button, Skeleton, EmptyState, AnimatedListItem, a11y } from '@/ui';
import { useNotifications, useMarkAllRead } from '@/notifications';
import type { AppNotification } from '@/notifications';
import { colors, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

const NOTIF_ITEM_HEIGHT = 70;

export function ProviderNotificationsScreen() {
  const styles = stylesFor(useThemeColors());

  const { data: notifs, isLoading, refetch, isRefetching } = useNotifications();
  const markAll = useMarkAllRead();
  const unreadCount = notifs?.filter(n => !n.read_at).length ?? 0;

  const handleRefresh = useCallback(() => {
    refetch().then(() => {
      a11y.announce(`${notifs?.length ?? 0} notifications chargées`);
    });
  }, [refetch, notifs?.length]);

  const renderNotifItem = useCallback(({ item, index }: { item: AppNotification; index: number }) => (
    <AnimatedListItem index={index}>
      <View
        style={[styles.notif, !item.read_at && styles.unread]}
        accessible
        accessibilityLabel={`${item.title}. ${item.body}${!item.read_at ? '. Non lu' : ''}`}
      >
        <Text style={styles.notifTitle}>{item.title}</Text>
        <Text style={styles.notifBody}>{item.body}</Text>
        <Text style={styles.notifTime}>
          {new Date(item.created_at).toLocaleDateString()}
        </Text>
      </View>
    </AnimatedListItem>
  ), []);

  const getItemLayout = useCallback((_: any, index: number) => ({
    length: NOTIF_ITEM_HEIGHT,
    offset: NOTIF_ITEM_HEIGHT * index,
    index,
  }), []);

  return (
    <Screen>
      <View style={styles.header}>
        <Text style={styles.title} accessibilityRole="header">Notifications</Text>
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
          renderItem={renderNotifItem}
          getItemLayout={getItemLayout}
          accessibilityLabel="Liste des notifications"
          refreshControl={
            <RefreshControl
              refreshing={isRefetching ?? false}
              onRefresh={handleRefresh}
              tintColor={colors.brand[500]}
              colors={[colors.brand[500]]}
            />
          }
          ListEmptyComponent={<EmptyState title="Aucune notification" message="Vous êtes à jour !" />}
        />
      )}
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: spacing.md,
  },
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
  },
  skeletons: { gap: spacing.sm },
  notif: {
    paddingVertical: spacing.sm,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: t.border,
  },
  unread: { backgroundColor: t.tint.brand },
  notifTitle: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
  },
  notifBody: {
    fontSize: typography.fontSize.xs,
    color: t.textSecondary,
    marginTop: 2,
  },
  notifTime: {
    fontSize: 10,
    color: t.textSecondary,
    marginTop: 4,
  },
  empty: {
    fontSize: typography.fontSize.sm,
    color: t.textSecondary,
    textAlign: 'center',
    marginTop: spacing.xl,
  },
});
