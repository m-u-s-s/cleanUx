import React, { useState, useCallback } from 'react';
import { View, FlatList, Text, KeyboardAvoidingView, Platform, StyleSheet } from 'react-native';
import { Screen, TextInput, Button, Avatar } from '@/ui';
import { useChatMessages, useSendMessage, useMarkThreadRead, useLiveChat } from '@/chat';
import { useAuth } from '@/auth';
import type { ChatMessage } from '@/chat/types';
import { colors, spacing, typography, radius } from '@/theme';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { RootStackParamList } from '@/navigation/types';
import { useEffect } from 'react';

type Props = NativeStackScreenProps<RootStackParamList, 'ProviderChat'>;

export function ProviderChatScreen({ route }: Props) {
  const { threadId } = route.params;
  const { user } = useAuth();
  const { data: messages, refetch } = useChatMessages(threadId);
  const sendMessage = useSendMessage(threadId);
  const markRead = useMarkThreadRead(threadId);
  const [text, setText] = useState('');

  useEffect(() => {
    markRead.mutate();
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [threadId]);

  useLiveChat(threadId, useCallback(() => { refetch(); }, [refetch]));

  const handleSend = () => {
    if (!text.trim()) return;
    sendMessage.mutate({ body: text.trim() });
    setText('');
  };

  const renderMessage = ({ item }: { item: ChatMessage }) => {
    const isMe = item.sender_id === user?.id;
    return (
      <View style={[styles.bubble, isMe ? styles.bubbleMe : styles.bubbleOther]}>
        {!isMe && <Text style={styles.senderName}>{item.sender_name}</Text>}
        <Text style={[styles.messageText, isMe && styles.messageTextMe]}>{item.body}</Text>
        <Text style={styles.time}>
          {new Date(item.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
        </Text>
      </View>
    );
  };

  return (
    <KeyboardAvoidingView
      style={styles.flex}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      keyboardVerticalOffset={90}
    >
      <FlatList
        data={messages ?? []}
        renderItem={renderMessage}
        keyExtractor={i => String(i.id)}
        inverted
        contentContainerStyle={styles.list}
      />
      <View style={styles.inputRow}>
        <View style={styles.input}>
          <TextInput
            label=""
            value={text}
            onChangeText={setText}
            placeholder="Message…"
          />
        </View>
        <Button
          label="Envoyer"
          onPress={handleSend}
          size="sm"
          disabled={!text.trim()}
          loading={sendMessage.isPending}
        />
      </View>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1, backgroundColor: colors.surface[50] },
  list: { paddingHorizontal: spacing.md, paddingVertical: spacing.sm },
  bubble: {
    maxWidth: '80%',
    padding: spacing.sm,
    borderRadius: radius.md,
    marginBottom: spacing.xs,
  },
  bubbleMe: { alignSelf: 'flex-end', backgroundColor: colors.brand[500] },
  bubbleOther: { alignSelf: 'flex-start', backgroundColor: colors.surface[200] },
  senderName: {
    fontSize: typography.fontSize.xs,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[600],
    marginBottom: 2,
  },
  messageText: { fontSize: typography.fontSize.sm, color: colors.surface[900] },
  messageTextMe: { color: '#ffffff' },
  time: { fontSize: 10, color: colors.surface[400], marginTop: 2, alignSelf: 'flex-end' },
  inputRow: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    padding: spacing.sm,
    gap: spacing.xs,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: colors.surface[200],
    backgroundColor: '#fff',
  },
  input: { flex: 1 },
});
