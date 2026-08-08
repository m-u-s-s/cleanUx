import React, { useState } from 'react';
import { View, FlatList, Text, TextInput, Alert, StyleSheet, Pressable } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Screen, Button, Badge, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { useAuth, can } from '@/auth';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';

interface Canal {
  id: number;
  name: string;
  type: string;
  is_private: boolean;
}

/**
 * LA LISTE DES CONVERSATIONS — et où il se passe quelque chose.
 *
 * Cet écran mêlait la liste et le fil : on ne pouvait ni ouvrir une conversation, ni savoir laquelle
 * avait du nouveau. `channel_members.last_read_at` existait depuis l'origine et n'était écrit par
 * PERSONNE, si bien que les non-lus ne pouvaient pas exister.
 *
 * LE FIL VIT DANS SON PROPRE ÉCRAN (`ChannelConversationScreen`), avec le temps réel, les
 * participants et le micro. Une liste et une conversation n'ont ni le même cycle de vie ni les mêmes
 * abonnements ; les garder ensemble obligeait à recharger l'un pour rafraîchir l'autre.
 */
export function CompanyChannelsScreen() {
  const styles = stylesFor(useThemeColors());
  const qc = useQueryClient();
  const { user } = useAuth();
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();

  const [nouveauNom, setNouveauNom] = useState('');

  const peutCreer = can(user, 'channels.create');

  const { data: canaux, refetch, isRefetching } = useQuery<Canal[]>({
    queryKey: ['company', 'channels'],
    queryFn: async () => (await apiClient.get('/provider/company/channels')).data.data ?? [],
  });

  const { data: nonLus } = useQuery<Record<number, number>>({
    queryKey: ['company', 'channels', 'unread'],
    queryFn: async () =>
      (await apiClient.get('/provider/company/channels/unread-counts')).data.data ?? {},
  });

  const creer = useMutation({
    mutationFn: async () =>
      apiClient.post('/provider/company/channels', {
        name: nouveauNom.trim(),
        // Un canal d'équipe naît vide de son équipe sans cela, et il faut ajouter chaque collègue
        // un par un — geste que personne ne fait.
        invite_whole_team: true,
      }),
    onSuccess: () => {
      setNouveauNom('');
      qc.invalidateQueries({ queryKey: ['company', 'channels'] });
    },
    onError: (erreur: any) =>
      Alert.alert(
        'Création refusée',
        erreur?.data?.message ?? "Votre rôle ne permet pas d'ouvrir un canal.",
      ),
  });

  return (
    <Screen>
      <Text style={styles.title}>Conversations</Text>

      {peutCreer && (
        <View style={styles.formulaire}>
          <TextInput
            value={nouveauNom}
            onChangeText={setNouveauNom}
            placeholder="Nom de la conversation"
            placeholderTextColor={styles.placeholder.color}
            style={styles.champ}
            testID="champ-nom-canal"
          />
          <Button
            label="Créer"
            size="sm"
            disabled={nouveauNom.trim().length === 0 || creer.isPending}
            onPress={() => creer.mutate()}
          />
        </View>
      )}

      <FlatList
        data={canaux ?? []}
        keyExtractor={(c) => String(c.id)}
        onRefresh={refetch}
        refreshing={isRefetching}
        renderItem={({ item }) => {
          const compte = nonLus?.[item.id] ?? 0;

          return (
            <Pressable
              style={styles.ligne}
              testID={`canal-${item.id}`}
              accessibilityRole="button"
              onPress={() => navigation.navigate('ChannelConversation', { channelId: item.id })}
            >
              <View style={styles.identite}>
                <Text style={styles.nom} numberOfLines={1}>
                  {item.is_private ? '🔒 ' : '# '}
                  {item.name}
                </Text>
              </View>

              {/* Le badge dit où il se passe quelque chose — la raison d'être des non-lus. */}
              {compte > 0 && <Badge label={String(compte)} variant="brand" />}
            </Pressable>
          );
        }}
        ListEmptyComponent={
          <EmptyState
            title="Aucune conversation"
            message="Ouvrez-en une pour coordonner vos interventions sans passer par WhatsApp."
          />
        }
      />
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) =>
  StyleSheet.create({
    title: {
      fontSize: typography.fontSize.xl,
      fontWeight: typography.fontWeight.bold,
      color: t.text,
      marginBottom: spacing.md,
    },
    formulaire: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: spacing.sm,
      marginBottom: spacing.md,
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
    placeholder: { color: t.textMuted },
    ligne: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: spacing.sm,
      paddingVertical: spacing.sm,
      borderBottomWidth: StyleSheet.hairlineWidth,
      borderBottomColor: t.border,
    },
    identite: { flex: 1, minWidth: 0 },
    nom: {
      fontSize: typography.fontSize.base,
      color: t.text,
    },
  });
