import React, { useCallback, useState } from 'react';
import { View, FlatList, Text, TextInput, StyleSheet, Pressable, Alert } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useRoute, useNavigation } from '@react-navigation/native';
import type { RouteProp } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Screen, Button, Divider, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { useAuth } from '@/auth';
import { useChannel } from '@/realtime';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';
import { enregistrerNoteVocale } from '@/company/voiceRecorder';
import { jouerNoteVocale } from '@/company/voicePlayer';
import { useTraduction } from '@/i18n';

interface MessageCanal {
  id: number;
  content: string;
  sender: string;
  sender_id: number | null;
  is_system: boolean;
  sent_at: string | null;
  /** `voice` pour une note vocale ; l'adresse de lecture est signée et expire en quinze minutes. */
  type?: string;
  duration?: number | null;
  audio_url?: string | null;
}

interface Participant {
  user_id: number;
  name: string | null;
  role: string;
}

/**
 * UNE CONVERSATION D'ÉQUIPE — temps réel, participants, note vocale.
 *
 * `CompanyChannelsScreen` mêlait la liste et le fil dans un seul écran, SANS temps réel : il fallait
 * tirer pour rafraîchir, ce qui d'une messagerie fait un formulaire. Le canal `channel.{id}` est
 * pourtant autorisé et fonctionnel côté serveur depuis longtemps — c'est l'application qui ne s'y
 * abonnait pas.
 *
 * LES PARTICIPANTS SE GÈRENT EN DEUX GESTES, comme l'exige le lot : ouvrir le panneau, appuyer sur
 * un nom. Un ajout qui demanderait de sortir de la conversation ne serait pas fait.
 *
 * LA NOTE VOCALE N'EST PAS UN CONFORT. Sur un chantier on ne tape pas — mains prises, gants,
 * téléphone au fond d'une poche —, et une messagerie qui n'accepte que du texte se fait remplacer
 * par WhatsApp, hors de l'outil et hors de toute trace.
 */
export function ChannelConversationScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());
  const { user } = useAuth();
  const qc = useQueryClient();

  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();
  const route = useRoute<RouteProp<RootStackParamList, 'ChannelConversation'>>();
  const canalId = route.params?.channelId ?? null;

  const [saisie, setSaisie] = useState('');
  const [participantsOuverts, setParticipantsOuverts] = useState(false);
  const [enregistrement, setEnregistrement] = useState(false);
  const [enLecture, setEnLecture] = useState<number | null>(null);

  /**
   * ÉCOUTER UNE NOTE — le geste qui manquait de l'autre côté.
   *
   * On pouvait enregistrer et envoyer depuis le lot 8 ; personne ne pouvait écouter. Une seule
   * note à la fois : deux sons superposés ne s'entendent ni l'un ni l'autre, et sur un chantier
   * c'est déjà assez bruyant.
   */
  const ecouter = useCallback(async (message: MessageCanal) => {
    if (!message.audio_url) {
      Alert.alert('Indisponible', 'Cette note vocale n’est pas encore prête à l’écoute.');
      return;
    }

    setEnLecture(message.id);

    const lecteur = await jouerNoteVocale(message.audio_url);

    if (!lecteur) {
      setEnLecture(null);
      Alert.alert('Lecture impossible', 'Le son n’a pas pu être ouvert sur cet appareil.');
      return;
    }

    // La durée annoncée par l'expéditeur sert de minuterie : `expo-audio` ne notifie pas la fin de
    // façon fiable sur tous les appareils, et un bouton qui reste « en lecture » indéfiniment
    // empêche d'écouter la suivante.
    const millisecondes = Math.max(1, Number(message.duration ?? 30)) * 1000;
    setTimeout(() => setEnLecture((actuel) => (actuel === message.id ? null : actuel)), millisecondes);
  }, []);

  const cleMessages = ['company', 'channel-messages', canalId];

  const { data: messages, refetch, isRefetching } = useQuery<MessageCanal[]>({
    queryKey: cleMessages,
    queryFn: async () =>
      (await apiClient.get(`/provider/company/channels/${canalId}/messages`)).data.data ?? [],
    enabled: canalId !== null,
  });

  const { data: participants } = useQuery<Participant[]>({
    queryKey: ['company', 'channel-members', canalId],
    queryFn: async () =>
      (await apiClient.get(`/provider/company/channels/${canalId}/members`)).data.data ?? [],
    enabled: canalId !== null && participantsOuverts,
  });

  const { data: collegues } = useQuery<Array<{ user_id: number; name: string | null; status: string }>>({
    queryKey: ['company', 'members'],
    queryFn: async () => (await apiClient.get('/provider/company/members')).data.data ?? [],
    enabled: canalId !== null && participantsOuverts,
  });

  /*
   * LE TEMPS RÉEL, ENFIN BRANCHÉ. `channel.{id}` est autorisé côté serveur depuis longtemps ;
   * l'application ne s'y abonnait pas, et une messagerie qu'il faut tirer pour rafraîchir est un
   * formulaire.
   */
  useChannel(canalId !== null ? `channel.${canalId}` : null, {
    'message.sent': () => qc.invalidateQueries({ queryKey: cleMessages }),
    MessageSent: () => qc.invalidateQueries({ queryKey: cleMessages }),
    // On ne se montre pas sa propre bannière : l'appelant est déjà sur l'écran d'appel.
    CallStarted: (donnees: any) => {
      if (donnees?.initiator_user_id !== user?.id) {
        setAppelEntrant({ call_id: donnees.call_id, type: donnees.type });
      }
    },
  });

  /*
   * L'APPEL ENTRANT ARRIVE PAR LE MÊME CANAL QUE LES MESSAGES.
   *
   * `channel.{id}` est déjà autorisé et vérifie l'appartenance au fil — exactement la population qui
   * doit voir la bannière. Ouvrir un canal de diffusion dédié aux appels aurait demandé une seconde
   * règle d'autorisation, vouée à diverger de la première.
   *
   * La charge utile ne porte PAS de jeton : chacun demande le sien, et le demander EST l'acte de
   * décrocher côté serveur.
   */
  const [appelEntrant, setAppelEntrant] = useState<{ call_id: number; type: string } | null>(null);

  const appeler = useMutation({
    mutationFn: async (type: 'audio' | 'video') =>
      apiClient.post(`/provider/company/channels/${canalId}/calls`, { type }),
    onSuccess: (reponse: any) =>
      navigation.navigate('Call', {
        callId: reponse.data.data.call_id,
        video: reponse.data.data.type === 'video',
      }),
    onError: (erreur: any) =>
      Alert.alert(
        'Appel impossible',
        erreur?.data?.message ?? "Les appels ne sont pas disponibles sur cette instance.",
      ),
  });

  const envoyer = useMutation({
    mutationFn: async (contenu: string) =>
      apiClient.post(`/provider/company/channels/${canalId}/messages`, { content: contenu }),
    onSuccess: () => {
      setSaisie('');
      qc.invalidateQueries({ queryKey: cleMessages });
      // Lire ce qu'on vient d'écrire : sans cela le badge de non-lus resterait allumé sur son
      // propre fil.
      apiClient.post(`/provider/company/channels/${canalId}/read`).catch(() => undefined);
    },
    onError: (erreur: any) =>
      Alert.alert('Envoi refusé', erreur?.data?.message ?? "Le message n'a pas pu être envoyé."),
  });

  const gererParticipant = useMutation({
    mutationFn: async (params: { userId: number; retirer: boolean }) =>
      params.retirer
        ? apiClient.delete(`/provider/company/channels/${canalId}/members/${params.userId}`)
        : apiClient.post(`/provider/company/channels/${canalId}/members`, {
            user_id: params.userId,
          }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['company', 'channel-members', canalId] }),
    onError: (erreur: any) =>
      Alert.alert('Action refusée', erreur?.data?.message ?? 'Vous ne gérez pas ce canal.'),
  });

  const envoyerLaNote = useMutation({
    mutationFn: async () => {
      const note = await enregistrerNoteVocale();

      if (note === null) {
        return null;
      }

      const corps = new FormData();
      corps.append('audio', note.fichier as unknown as Blob);
      corps.append('duration', String(note.dureeSecondes));

      return apiClient.post(`/provider/company/channels/${canalId}/voice`, corps);
    },
    onSettled: () => setEnregistrement(false),
    onSuccess: () => qc.invalidateQueries({ queryKey: cleMessages }),
    onError: (erreur: any) =>
      Alert.alert(
        'Note vocale impossible',
        erreur?.data?.message ?? "L'enregistrement n'a pas pu être envoyé.",
      ),
  });

  if (canalId === null) {
    return (
      <Screen>
        <EmptyState title="Conversation introuvable" message="Ce canal n'existe plus." />
      </Screen>
    );
  }

  return (
    <Screen>
      {appelEntrant !== null && (
        <View style={styles.banniere} testID="banniere-appel">
          <Text style={styles.texteBanniere}>{tr('channel_conversation.appel_entrant')}</Text>
          <Button
            label="Répondre"
            size="sm"
            onPress={() => {
              const entrant = appelEntrant;
              setAppelEntrant(null);
              navigation.navigate('Call', {
                callId: entrant.call_id,
                video: entrant.type === 'video',
              });
            }}
          />
          <Button
            label="Refuser"
            size="sm"
            variant="ghost"
            onPress={() => {
              // Refuser TERMINE l'appel côté serveur : sinon il continuerait de sonner jusqu'au
              // délai, et la bannière reviendrait au prochain rendu.
              apiClient.post(`/provider/company/calls/${appelEntrant.call_id}/end`).catch(() => undefined);
              setAppelEntrant(null);
            }}
          />
        </View>
      )}

      <View style={styles.entete}>
        <Pressable
          accessibilityRole="button"
          testID="bouton-appeler"
          onPress={() => appeler.mutate('audio')}
        >
          <Text style={styles.lienAppel}>📞 Appeler</Text>
        </Pressable>
      </View>

      <Pressable
        accessibilityRole="button"
        testID="ouvrir-participants"
        onPress={() => setParticipantsOuverts(!participantsOuverts)}
      >
        <Text style={styles.lienParticipants}>
          {participantsOuverts ? '− Participants' : '+ Participants'}
        </Text>
      </Pressable>

      {participantsOuverts && (
        <View style={styles.participants} testID="panneau-participants">
          {(participants ?? []).map((participant) => (
            <View key={participant.user_id} style={styles.ligneParticipant}>
              <Text style={styles.nomParticipant} numberOfLines={1}>
                {participant.name ?? '—'}
                {participant.role !== 'member' ? ` · ${participant.role}` : ''}
              </Text>
              <Button
                label="Retirer"
                size="sm"
                variant="ghost"
                onPress={() => gererParticipant.mutate({ userId: participant.user_id, retirer: true })}
              />
            </View>
          ))}

          <Divider />

          {(collegues ?? [])
            .filter((c) => c.status === 'active')
            .filter((c) => !(participants ?? []).some((p) => p.user_id === c.user_id))
            .map((collegue) => (
              <View key={collegue.user_id} style={styles.ligneParticipant}>
                <Text style={styles.nomParticipant} numberOfLines={1}>
                  {collegue.name ?? '—'}
                </Text>
                <Button
                  label="Ajouter"
                  size="sm"
                  variant="secondary"
                  onPress={() =>
                    gererParticipant.mutate({ userId: collegue.user_id, retirer: false })
                  }
                />
              </View>
            ))}
        </View>
      )}

      <FlatList
        data={messages ?? []}
        keyExtractor={(m) => String(m.id)}
        onRefresh={refetch}
        refreshing={isRefetching}
        renderItem={({ item }) => (
          <View
            style={[styles.message, item.sender_id === user?.id ? styles.moi : styles.autre]}
            testID={`message-${item.id}`}
          >
            {!item.is_system && <Text style={styles.expediteur}>{item.sender}</Text>}

            {item.type === 'voice' ? (
              <Pressable
                onPress={() => ecouter(item)}
                accessibilityRole="button"
                accessibilityLabel={`Écouter la note vocale de ${item.sender}`}
                testID={`note-vocale-${item.id}`}
                style={styles.noteVocale}
              >
                <Text style={styles.contenu}>
                  {enLecture === item.id ? '⏸  Lecture…' : '▶️  Note vocale'}
                  {item.duration ? `  ·  ${item.duration}s` : ''}
                </Text>
              </Pressable>
            ) : (
              <Text style={item.is_system ? styles.systeme : styles.contenu}>{item.content}</Text>
            )}
          </View>
        )}
        ListEmptyComponent={
          <EmptyState title="Aucun message" message="Ouvrez la conversation en écrivant un mot." />
        }
      />

      <View style={styles.barre}>
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
          disabled={saisie.trim().length === 0 || envoyer.isPending}
          onPress={() => envoyer.mutate(saisie.trim())}
        />
        <Pressable
          accessibilityRole="button"
          testID="bouton-micro"
          onPress={() => {
            setEnregistrement(true);
            envoyerLaNote.mutate();
          }}
        >
          <Text style={styles.micro}>{enregistrement ? '⏺' : '🎙️'}</Text>
        </Pressable>
      </View>
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) =>
  StyleSheet.create({
    banniere: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',
      gap: spacing.xs,
      padding: spacing.sm,
      borderRadius: radius.md,
      backgroundColor: t.tint.brand,
      marginBottom: spacing.sm,
    },
    texteBanniere: {
      flex: 1,
      fontSize: typography.fontSize.sm,
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
    },
    entete: {
      flexDirection: 'row',
      justifyContent: 'flex-end',
    },
    lienAppel: {
      fontSize: typography.fontSize.sm,
      color: t.textSecondary,
      paddingVertical: spacing.xs,
    },
    lienParticipants: {
      fontSize: typography.fontSize.sm,
      color: t.textSecondary,
      paddingVertical: spacing.xs,
    },
    participants: {
      paddingLeft: spacing.sm,
      borderLeftWidth: 2,
      borderLeftColor: t.border,
      marginBottom: spacing.sm,
    },
    ligneParticipant: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',
      gap: spacing.sm,
      paddingVertical: spacing.xs,
    },
    nomParticipant: {
      flex: 1,
      fontSize: typography.fontSize.sm,
      color: t.text,
    },
    message: {
      marginBottom: spacing.xs,
      padding: spacing.sm,
      borderRadius: radius.md,
      maxWidth: '85%',
    },
    // La zone d'écoute doit rester pressable au doigt ganté : 44 points, la même hauteur que les
    // autres cibles tactiles de l'application.
    noteVocale: {
      minHeight: 44,
      justifyContent: 'center',
    },
    moi: {
      alignSelf: 'flex-end',
      backgroundColor: t.tint.brand,
    },
    autre: {
      alignSelf: 'flex-start',
      backgroundColor: t.cardSubtle,
    },
    expediteur: {
      fontSize: typography.fontSize.xs,
      color: t.textMuted,
      marginBottom: 2,
    },
    contenu: {
      fontSize: typography.fontSize.sm,
      color: t.text,
    },
    systeme: {
      fontSize: typography.fontSize.xs,
      color: t.textMuted,
      fontStyle: 'italic',
    },
    barre: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: spacing.xs,
      paddingTop: spacing.sm,
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
    micro: {
      fontSize: typography.fontSize.lg,
      paddingHorizontal: spacing.xs,
    },
  });
