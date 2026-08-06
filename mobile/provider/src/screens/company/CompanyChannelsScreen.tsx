import React from 'react';
import { View, FlatList, Text, TextInput, Alert, StyleSheet } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Screen, Button, Badge, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

interface Canal {
  id: number;
  name: string;
  type: string;
  is_private: boolean;
}

interface MessageCanal {
  id: number;
  content: string;
  sender: string;
  sender_id: number | null;
  is_system: boolean;
  sent_at: string | null;
}

/**
 * La messagerie d'équipe, en natif.
 *
 * Lecture ET écriture passent par `ChannelPolicy` côté serveur. C'est en écrivant cette API qu'a
 * été découvert le défaut corrigé le même jour : l'écran web ne consultait aucune politique à
 * l'envoi, si bien qu'on pouvait publier dans le canal privé d'une autre société.
 */
export function CompanyChannelsScreen() {
  const styles = stylesFor(useThemeColors());
  const qc = useQueryClient();

  const [canalActif, setCanalActif] = React.useState<number | null>(null);
  const [saisie, setSaisie] = React.useState('');

  const { data: canaux } = useQuery<Canal[]>({
    queryKey: ['company', 'channels'],
    queryFn: async () => (await apiClient.get('/provider/company/channels')).data.data ?? [],
  });

  // Le premier canal disponible s'ouvre seul : un écran vide sans explication ferait croire à
  // une messagerie en panne.
  React.useEffect(() => {
    const premier = canaux?.[0];

    if (canalActif === null && premier) {
      setCanalActif(premier.id);
    }
  }, [canaux, canalActif]);

  const { data: messages, refetch, isRefetching } = useQuery<MessageCanal[]>({
    queryKey: ['company', 'channel-messages', canalActif],
    queryFn: async () =>
      (await apiClient.get(`/provider/company/channels/${canalActif}/messages`)).data.data ?? [],
    enabled: canalActif !== null,
  });

  const envoyer = useMutation({
    mutationFn: async (content: string) => {
      await apiClient.post(`/provider/company/channels/${canalActif}/messages`, { content });
    },
    onSuccess: () => {
      setSaisie('');
      qc.invalidateQueries({ queryKey: ['company', 'channel-messages', canalActif] });
    },
    onError: () =>
      Alert.alert('Envoi refusé', "Ce canal est verrouillé, archivé, ou vous n'en êtes pas membre."),
  });

  return (
    <Screen>
      <Text style={styles.title}>Canaux</Text>

      {/* Sélecteur de canal */}
      <FlatList
        data={canaux ?? []}
        horizontal
        showsHorizontalScrollIndicator={false}
        keyExtractor={(c) => String(c.id)}
        style={styles.selecteur}
        renderItem={({ item }) => (
          <View style={styles.pastille}>
            <Button
              label={`${item.is_private ? '🔒 ' : '# '}${item.name}`}
              size="sm"
              variant={item.id === canalActif ? 'primary' : 'ghost'}
              onPress={() => setCanalActif(item.id)}
            />
          </View>
        )}
        ListEmptyComponent={
          <Text style={styles.detail}>Aucun canal — demandez à être ajouté à une conversation.</Text>
        }
      />

      <FlatList
        data={messages ?? []}
        keyExtractor={(m) => String(m.id)}
        onRefresh={refetch}
        refreshing={isRefetching}
        style={styles.fil}
        renderItem={({ item }) => (
          <View style={styles.message} testID={`message-${item.id}`}>
            {item.is_system ? (
              <Badge label={item.content} variant="neutral" />
            ) : (
              <>
                <Text style={styles.expediteur}>{item.sender}</Text>
                <Text style={styles.contenu}>{item.content}</Text>
              </>
            )}
          </View>
        )}
        ListEmptyComponent={
          canalActif !== null ? (
            <EmptyState title="Aucun message" message="Lancez la conversation." />
          ) : null
        }
      />

      {canalActif !== null && (
        <View style={styles.formulaire}>
          <TextInput
            value={saisie}
            onChangeText={setSaisie}
            placeholder="Votre message"
            placeholderTextColor={styles.placeholder.color}
            style={styles.champ}
            testID="champ-message"
          />
          <Button
            label="Envoyer"
            size="sm"
            onPress={() => saisie.trim() && envoyer.mutate(saisie.trim())}
            disabled={envoyer.isPending || saisie.trim().length === 0}
          />
        </View>
      )}
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) =>
  StyleSheet.create({
    title: {
      fontSize: typography.fontSize.xl,
      fontWeight: typography.fontWeight.bold,
      color: t.text,
      marginBottom: spacing.sm,
    },
    selecteur: {
      flexGrow: 0,
      marginBottom: spacing.sm,
    },
    pastille: {
      marginRight: spacing.xs,
    },
    fil: {
      flex: 1,
    },
    message: {
      paddingVertical: spacing.xs,
      borderBottomWidth: StyleSheet.hairlineWidth,
      borderBottomColor: t.border,
    },
    expediteur: {
      fontSize: typography.fontSize.sm,
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
    },
    contenu: {
      fontSize: typography.fontSize.base,
      color: t.text,
    },
    detail: {
      fontSize: typography.fontSize.sm,
      color: t.textMuted,
    },
    formulaire: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: spacing.sm,
      marginTop: spacing.sm,
    },
    champ: {
      flex: 1,
      borderWidth: StyleSheet.hairlineWidth,
      borderColor: t.border,
      borderRadius: radius.md,
      paddingHorizontal: spacing.sm,
      paddingVertical: spacing.xs,
      color: t.text,
      backgroundColor: t.card,
    },
    placeholder: {
      color: t.textMuted,
    },
  });
