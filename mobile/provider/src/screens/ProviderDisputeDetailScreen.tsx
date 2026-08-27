import React, { useState } from 'react';
import { ScrollView, View, Text, StyleSheet, Pressable, Alert, Image } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import type { RouteProp } from '@react-navigation/native';
import { useRoute } from '@react-navigation/native';
import * as ImagePicker from 'expo-image-picker';
import { Screen, Badge, Skeleton, ErrorState, Button, TextInput } from '@/ui';
import { apiClient } from '@/api';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';

/** Le serveur plafonne à cinq (`PreuvesDeLitige::NOMBRE_MAX`) et n'accepte que des images. */
const MAX_PREUVES = 5;

interface PieceJointe {
  path: string;
  original_name?: string;
  /** Lien signé de quinze minutes, servi par l'API : il porte sa preuve, sans session. */
  url?: string | null;
}

interface Evenement {
  id: number;
  type: string;
  body: string | null;
  author_role: string | null;
  created_at: string;
  attachments?: PieceJointe[] | null;
  author?: { id: number; name: string } | null;
}

interface Dossier {
  id: number;
  reference: string;
  subject: string;
  description: string;
  status: string;
  attachments?: PieceJointe[] | null;
  events?: Evenement[];
}

const ROLES: Record<string, string> = {
  client: 'Client',
  provider: 'Vous',
  admin: 'Support Brio',
  system: 'Système',
};

/**
 * RÉPONDRE À UN LITIGE DEPUIS LE TÉLÉPHONE.
 *
 * L'application ne savait que LISTER : `GET /provider/disputes` rendait des dossiers,
 * `POST /{dispute}/respond` acceptait une réponse, et rien entre les deux. Répondre demandait donc
 * d'écrire à l'aveugle, sans voir ce que le client avait dit.
 *
 * CE QUI N'ARRIVE PAS JUSQU'ICI : les notes internes du support. `visibleTo(ROLE_PROVIDER)` filtre
 * côté serveur, à la requête — ce n'est pas cet écran qui décide de les cacher.
 */
export function ProviderDisputeDetailScreen() {
  const theme = useThemeColors();
  const styles = stylesFor(theme);

  const { params } = useRoute<RouteProp<RootStackParamList, 'ProviderDisputeDetail'>>();
  const queryClient = useQueryClient();

  const { data, isLoading, isError, refetch } = useQuery<Dossier>({
    queryKey: ['provider', 'disputes', params.disputeId],
    queryFn: async () => (await apiClient.get(`/provider/disputes/${params.disputeId}`)).data.data,
  });

  const [reponse, setReponse] = useState('');
  const [preuves, setPreuves] = useState<string[]>([]);

  const ajouterUnePreuve = async () => {
    if (preuves.length >= MAX_PREUVES) {
      Alert.alert('Cinq photos au maximum', 'Retirez-en une pour en ajouter une autre.');
      return;
    }

    const permission = await ImagePicker.requestMediaLibraryPermissionsAsync();

    if (!permission.granted) {
      Alert.alert('Permission requise', 'Autorisez l’accès aux photos pour joindre une preuve.');
      return;
    }

    const choix = await ImagePicker.launchImageLibraryAsync({ mediaTypes: ['images'], quality: 0.8 });
    const asset = !choix.canceled ? choix.assets[0] : null;

    if (asset) {
      setPreuves(actuelles => [...actuelles, asset.uri]);
    }
  };

  const retirerUnePreuve = (uri: string) =>
    setPreuves(actuelles => actuelles.filter(autre => autre !== uri));

  const envoyer = useMutation({
    mutationFn: async () => {
      // SANS PREUVE, PAS DE MULTIPART : le corps JSON reste la forme normale de cet appel.
      if (preuves.length === 0) {
        return (await apiClient.post(`/provider/disputes/${params.disputeId}/respond`, { body: reponse })).data;
      }

      const corps = new FormData();
      corps.append('body', reponse);

      preuves.forEach((uri, index) => {
        // La forme { uri, name, type } est celle qu'attend FormData en React Native pour un
        // fichier local ; un Blob n'y fonctionne pas.
        corps.append('attachments[]', { uri, name: `preuve-${index + 1}.jpg`, type: 'image/jpeg' } as never);
      });

      return (
        await apiClient.post(`/provider/disputes/${params.disputeId}/respond`, corps, {
          headers: { 'Content-Type': 'multipart/form-data' },
        })
      ).data;
    },
    onSuccess: () => {
      setReponse('');
      setPreuves([]);
      queryClient.invalidateQueries({ queryKey: ['provider', 'disputes'] });
      Alert.alert('Réponse envoyée', 'Le support et le client la verront.');
    },
    onError: () => Alert.alert('Échec', "La réponse n'a pas pu être envoyée. Réessayez."),
  });

  if (isError) {
    return (
      <Screen>
        <ErrorState message="Impossible de charger ce litige." onRetry={refetch} />
      </Screen>
    );
  }

  if (isLoading || !data) {
    return (
      <Screen>
        <Skeleton width="100%" height={120} />
      </Screen>
    );
  }

  const peutEnvoyer = reponse.trim().length >= 1 && !envoyer.isPending;

  return (
    <Screen>
      <ScrollView contentContainerStyle={styles.contenu} keyboardShouldPersistTaps="handled">
        <View style={styles.entete}>
          <Text style={styles.reference}>{data.reference}</Text>
          <Badge label={data.status} variant={data.status === 'resolved' ? 'success' : 'warning'} />
        </View>

        <Text style={styles.titre}>{data.subject}</Text>
        <Text style={styles.corps}>{data.description}</Text>

        <PiecesRecues fichiers={data.attachments} styles={styles} />

        <Text style={styles.section}>Échanges</Text>

        {(data.events ?? []).map(evenement => (
          <View key={evenement.id} style={styles.evenement} testID={`evenement-${evenement.id}`}>
            <View style={styles.entete}>
              <Text style={styles.auteur}>{ROLES[evenement.author_role ?? ''] ?? 'Participant'}</Text>
              <Text style={styles.date}>{evenement.created_at?.slice(0, 16).replace('T', ' ')}</Text>
            </View>

            {evenement.body ? <Text style={styles.corps}>{evenement.body}</Text> : null}

            <PiecesRecues fichiers={evenement.attachments} styles={styles} />
          </View>
        ))}

        <Text style={styles.section}>Votre réponse</Text>

        <TextInput
          label="Message"
          placeholder="Expliquez ce qui s’est passé"
          value={reponse}
          onChangeText={setReponse}
          multiline
        />

        <View style={styles.preuves}>
          {preuves.map(uri => (
            <Pressable
              key={uri}
              onPress={() => retirerUnePreuve(uri)}
              accessibilityLabel="Retirer cette photo"
              style={styles.preuve}
            >
              <Image source={{ uri }} style={styles.preuveImage} />
            </Pressable>
          ))}

          {preuves.length < MAX_PREUVES && (
            <Button label="Ajouter une photo" size="sm" variant="outline" onPress={ajouterUnePreuve} />
          )}
        </View>

        <Button
          label="Envoyer la réponse"
          onPress={() => envoyer.mutate()}
          disabled={!peutEnvoyer}
          loading={envoyer.isPending}
        />
      </ScrollView>
    </Screen>
  );
}

/**
 * Les pièces REÇUES, affichées.
 *
 * L'API sert désormais un lien que ce téléphone peut ouvrir : la route web exige une session en
 * plus de la signature — mesuré, elle rend `302 → /login` — donc `PrivateMedia::urlPourAppareil()`
 * signe un lien qui porte sa seule preuve, quinze minutes, sur le seul chemin qu'il nomme.
 *
 * Une pièce sans lien ne rend rien plutôt qu'un carré cassé.
 */
function PiecesRecues({
  fichiers,
  styles,
}: {
  fichiers?: PieceJointe[] | null;
  styles: ReturnType<typeof stylesFor>;
}) {
  const affichables = (Array.isArray(fichiers) ? fichiers : []).filter(piece => !!piece.url);

  if (affichables.length === 0) {
    return null;
  }

  return (
    <View style={styles.preuves} testID="pieces-recues">
      {affichables.map(piece => (
        <Image
          key={piece.path}
          source={{ uri: piece.url as string }}
          style={styles.preuveImage}
          accessibilityLabel={piece.original_name ?? 'Pièce jointe'}
        />
      ))}
    </View>
  );
}

function stylesFor(theme: ThemeTokens) {
  return StyleSheet.create({
    contenu: { paddingBottom: spacing.xl, gap: spacing.sm },
    entete: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
    reference: { fontSize: typography.fontSize.xs, color: theme.textMuted },
    titre: { fontSize: typography.fontSize.lg, fontWeight: typography.fontWeight.bold, color: theme.text },
    corps: { fontSize: typography.fontSize.sm, color: theme.textSecondary, lineHeight: 20 },
    section: { marginTop: spacing.md, fontSize: typography.fontSize.sm, fontWeight: typography.fontWeight.bold, color: theme.text },
    evenement: { padding: spacing.md, borderRadius: radius.lg, backgroundColor: theme.card, gap: spacing.xs },
    auteur: { fontSize: typography.fontSize.sm, fontWeight: typography.fontWeight.semibold, color: theme.text },
    date: { fontSize: typography.fontSize.xs, color: theme.textMuted },
    pieces: { fontSize: typography.fontSize.xs, color: theme.textMuted },
    preuves: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm, alignItems: 'center' },
    preuve: { borderRadius: radius.md, overflow: 'hidden' },
    preuveImage: { width: 64, height: 64 },
  });
}
