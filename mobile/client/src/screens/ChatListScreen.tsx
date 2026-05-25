import React from 'react';
import { FlatList, TouchableOpacity, View, Text, StyleSheet } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Screen, Avatar, Badge, Skeleton, EmptyState } from '@/ui';
import { useChatThreads } from '@/chat';
import type { ChatThread } from '@/chat/types';
import { colors, spacing, typography } from '@/theme';
import type { RootStackParamList } from '@/navigation/types';

export function ChatListScreen() {
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
          renderItem={({ item }: { item: ChatThread }) => (
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
          )}
          onRefresh={refetch}
          refreshing={isRefetching}
          ListEmptyComponent={<EmptyState title="Aucune conversation" message="Vos échanges avec les prestataires apparaîtront ici." />}
        />
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
  skeletons: { gap: spacing.sm },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: spacing.sm,
    gap: spacing.sm,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.surface[200],
  },
  rowContent: { flex: 1 },
  rowName: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.medium,
    color: colors.surface[900],
  },
  rowPreview: {
    fontSize: typography.fontSize.xs,
    color: colors.surface[500],
    marginTop: 2,
  },
  empty: {
    fontSize: typography.fontSize.sm,
    color: colors.surface[400],
    textAlign: 'center',
    marginTop: spacing.xl,
  },
});
