import React from 'react';
import { FlatList, TouchableOpacity, View, Text, StyleSheet } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Screen, Avatar, Badge, Skeleton, EmptyState, AnimatedListItem } from '@/ui';
import { useChatThreads } from '@/chat';
import { useAuth } from '@/auth';
import type { ChatThread } from '@/chat/types';
import {spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';
import { useTraduction } from '@/i18n';

export function ChatListScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  const { data: threads, isLoading, refetch, isRefetching } = useChatThreads();
  const { user } = useAuth();
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();

  return (
    <Screen>
      <Text style={styles.title}>{tr('chat_list.messagerie')}</Text>
      {isLoading ? (
        <View style={styles.skeletons}>
          {[1, 2, 3].map(i => <Skeleton key={i} width="100%" height={60} />)}
        </View>
      ) : (
        <FlatList
          data={threads ?? []}
          keyExtractor={item => String(item.id)}
          renderItem={({ item, index }: { item: ChatThread; index: number }) => {
            /*
             * UN FIL A TOUJOURS UN NOM À MONTRER.
             *
             * Les participants viennent du serveur, qui ne les envoie pas toujours. On descend
             * alors sur le titre du fil, puis sur un mot générique — plutôt que d'afficher une
             * ligne vide, et surtout plutôt que de tomber : `item.participants[0]` sans garde
             * faisait sauter tout l'écran au premier fil.
             */
            /*
             * ON NOMME UN FIL PAR LES AUTRES, jamais par soi-même.
             *
             * Le serveur renvoie TOUS les participants, soi compris. Les lister tels quels donnait
             * une conversation intitulée « Gabrielle Lemoine » à Gabrielle Lemoine elle-même.
             */
            const autres = (item.participants ?? [])
              .filter(p => p.id !== user?.id)
              .map(p => p.name)
              .filter(Boolean);
            const nom = autres.join(', ') || item.title || 'Conversation';

            return (
            <AnimatedListItem index={index}>
            <TouchableOpacity
              style={styles.row}
              onPress={() => navigation.navigate('Chat', {
                threadId: item.id,
                title: nom,
              })}
            >
              <Avatar name={nom} size={40} />
              <View style={styles.rowContent}>
                <Text style={styles.rowName}>
                  {nom}
                </Text>
                {item.last_message && (
                  <Text style={styles.rowPreview} numberOfLines={1}>
                    {item.last_message}
                  </Text>
                )}
              </View>
              {(item.unread_count ?? 0) > 0 && (
                <Badge label={String(item.unread_count)} variant="danger" />
              )}
            </TouchableOpacity>
            </AnimatedListItem>
            );
          }}
          onRefresh={refetch}
          refreshing={isRefetching}
          ListEmptyComponent={<EmptyState title={tr('chat_list.aucune_conversation')} message="Vos échanges avec les prestataires apparaîtront ici." icon="chatbubble-outline" />}
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
