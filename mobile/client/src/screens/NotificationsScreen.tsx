import React, { useCallback } from 'react';
import { FlatList, View, Text, StyleSheet, RefreshControl, TouchableOpacity } from 'react-native';
import Animated, { FadeIn } from 'react-native-reanimated';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Screen, Badge, Button, Skeleton, EmptyState, AnimatedListItem, a11y } from '@/ui';
import { useNotifications, useMarkAllRead, severityVariant, severityAccent, formatNotificationDate } from '@/notifications';
import type { AppNotification } from '@/notifications';
import { colors, spacing, typography, radius, shadows } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';

export function NotificationsScreen() {
  const t = useThemeColors();
  const styles = stylesFor(t);
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();

  const { data: notifs, isLoading, refetch, isRefetching } = useNotifications();
  const markAll = useMarkAllRead();
  const unreadCount = notifs?.filter(n => !n.read_at).length ?? 0;

  const handleRefresh = useCallback(() => {
    refetch().then(() => {
      a11y.announce(`${notifs?.length ?? 0} notifications chargées`);
    });
  }, [refetch, notifs?.length]);

  /*
   * `styles` ET `navigation` DOIVENT ÊTRE DANS LES DÉPENDANCES — voir le jumeau prestataire :
   * mémoïser sur `[]` en capturant `styles` figeait les couleurs du premier rendu, et la liste
   * ne suivait pas le passage en mode sombre.
   */
  const renderNotifItem = useCallback(({ item, index }: { item: AppNotification; index: number }) => {
    const nonLue = !item.read_at;
    const contexte = Object.values(item.context ?? {}).map(String);

    return (
      <AnimatedListItem index={index}>
        <TouchableOpacity
          style={[
            styles.notif,
            { borderLeftColor: severityAccent(item.severity, t.border) },
            nonLue && styles.unread,
          ]}
          onPress={() => navigation.navigate('NotificationDetail', { id: item.id })}
          accessibilityRole="button"
          accessibilityLabel={`${item.label} : ${item.title}. ${item.body}${nonLue ? '. Non lue' : ''}`}
          accessibilityHint="Ouvre le détail de la notification"
        >
          <View style={styles.badges}>
            <Badge label={item.label} variant={severityVariant(item.severity)} />
            {nonLue && <Badge label="Nouveau" variant="brand" />}
          </View>

          <Text style={styles.notifTitle}>{item.title}</Text>
          {item.body !== item.title && <Text style={styles.notifBody}>{item.body}</Text>}

          <Text style={styles.notifTime}>
            {[...contexte, formatNotificationDate(item.created_at)].filter(Boolean).join(' · ')}
          </Text>
        </TouchableOpacity>
      </AnimatedListItem>
    );
  }, [styles, navigation, t]);

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
          {[1, 2, 3, 4].map(i => <Skeleton key={i} width="100%" height={72} />)}
        </View>
      ) : (
        <Animated.View entering={FadeIn.duration(280)} style={{ flex: 1 }}>
          <FlatList
            data={notifs ?? []}
            contentContainerStyle={styles.liste}
            keyExtractor={item => item.id}
            renderItem={renderNotifItem}
            accessibilityLabel="Liste des notifications"
            refreshControl={
              <RefreshControl
                refreshing={isRefetching ?? false}
                onRefresh={handleRefresh}
                tintColor={colors.brand[500]}
                colors={[colors.brand[500]]}
              />
            }
            ListEmptyComponent={<EmptyState title="Aucune notification" message="Vous êtes à jour !" icon="notifications-outline" />}
          />
        </Animated.View>
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
  /*
   * UNE CARTE PAR NOTIFICATION, ET DE L'AIR ENTRE ELLES.
   *
   * La liste empilait des lignes séparées par un filet d'un pixel : rien ne disait où finissait
   * une notification et où commençait la suivante. L'espace vient de `contentContainerStyle`
   * plutôt que d'une marge sur la carte — une marge basse laisserait un vide sous la dernière.
   */
  liste: {
    gap: spacing.sm,
    paddingBottom: spacing.md,
  },
  notif: {
    padding: spacing.md,
    borderRadius: radius.md,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: t.border,
    borderLeftWidth: 3,
    backgroundColor: t.card,
    ...shadows.xs,
  },
  unread: { backgroundColor: t.tint.brand },
  badges: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.xs,
    marginBottom: 4,
  },
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
});
