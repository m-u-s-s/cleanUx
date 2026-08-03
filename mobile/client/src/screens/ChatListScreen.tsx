import React from 'react';
import { FlatList, TouchableOpacity, View, Text, StyleSheet } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Screen, Avatar, Badge, Skeleton, EmptyState, AnimatedListItem } from '@/ui';
import { useChatThreads } from '@/chat';
import type { ChatThread } from '@/chat/types';
import {spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';

export function ChatListScreen() {
  const styles = stylesFor(useThemeColors());

  const { data: threads, isLoading, refetch, isRefetching } = useChatThreads();
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();

  return (
    <Screen>
      <Text style={styles.title}>Messagerie</Text>
      {isLoading ? (
        <View style={styles.skeletons}>
          {[1, 2, 3].map(i => <Skeleton key={i} width="100%" height={60} />)}
        </View>
      ) : (
        <FlatList
          data={threads ?? []}
          keyExtractor={item => String(item.id)}
          renderItem={({ item, index }: { item: ChatThread; index: number }) => (
            <AnimatedListItem index={index}>
            <TouchableOpacity
              style={styles.row}
              onPress={() => navigation.navigate('Chat', {
                threadId: item.id,
                title: item.participants.map(p => p.name).join(', '),
              })}
            >
              <Avatar name={item.participants[0]?.name ?? '?'} size={40} />
              <View style={styles.rowContent}>
                <Text style={styles.rowName}>
                  {item.participants.map(p => p.name).join(', ')}
                </Text>
                {item.last_message && (
                  <Text style={styles.rowPreview} numberOfLines={1}>
                    {item.last_message}
                  </Text>
                )}
              </View>
              {item.unread_count > 0 && (
                <Badge label={String(item.unread_count)} variant="danger" />
              )}
            </TouchableOpacity>
            </AnimatedListItem>
          )}
          onRefresh={refetch}
          refreshing={isRefetching}
          ListEmptyComponent={<EmptyState title="Aucune conversation" message="Vos échanges avec les prestataires apparaîtront ici." icon="chatbubble-outline" />}
        />
      )}
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
    marginBottom: spacing.md,
  },
  skeletons: { gap: spacing.sm },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: spacing.sm,
    gap: spacing.sm,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: t.border,
  },
  rowContent: { flex: 1 },
  rowName: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.medium,
    color: t.text,
  },
  rowPreview: {
    fontSize: typography.fontSize.xs,
    color: t.textSecondary,
    marginTop: 2,
  },
  empty: {
    fontSize: typography.fontSize.sm,
    color: t.textMuted,
    textAlign: 'center',
    marginTop: spacing.xl,
  },
});
