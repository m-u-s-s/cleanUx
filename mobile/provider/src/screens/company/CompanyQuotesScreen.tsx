import React, { useState } from 'react';
/* Chemin direct plutot que le baril : des suites mockent les barils a la main. */
import { formatCentimes } from '@/format/money';
import { View, FlatList, Text, TextInput, StyleSheet, Alert } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Screen, Button, Badge, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { useAuth, can } from '@/auth';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

interface Devis {
  id: number;
  reference: string;
  title: string;
  client_name: string | null;
  status: string;
  total_cents: number;
  is_open: boolean;
}

const LIBELLES: Record<string, string> = {
  draft: 'Brouillon',
  sent: 'Envoyé',
  accepted: 'Accepté',
  declined: 'Refusé',
  expired: 'Périmé',
  cancelled: 'Annulé',
};

/**
 * LES DEVIS DE LA SOCIÉTÉ (E24), CHIFFRÉS SUR PLACE.
 *
 * C'EST L'ÉCRAN LE PLUS LÉGITIMEMENT MOBILE DU LOT. Un devis se chiffre CHEZ LE CLIENT, pendant la
 * visite : c'est le seul moment où l'on voit la surface, l'état des sols, la hauteur sous plafond,
 * l'absence d'ascenseur. Le noter sur un carnet pour le saisir en rentrant, c'est perdre la moitié
 * des détails et deux jours de délai de réponse — souvent le délai qui fait perdre l'affaire.
 *
 * UN DEVIS ENVOYÉ NE SE MODIFIE PLUS, et l'écran cesse d'en proposer la modification. Le corriger
 * après coup ferait diverger ce que le client a reçu de ce qu'il accepte.
 */
export function CompanyQuotesScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());
  const { user } = useAuth();
  const qc = useQueryClient();

  const [titre, setTitre] = useState('');

  const peutGerer = can(user, 'quotes.manage');

  const { data: devis, refetch, isRefetching } = useQuery<Devis[]>({
    queryKey: ['company', 'quotes'],
    queryFn: async () => (await apiClient.get('/provider/company/quotes')).data.data ?? [],
  });

  const creer = useMutation({
    mutationFn: async () => apiClient.post('/provider/company/quotes', { title: titre.trim() }),
    onSuccess: () => {
      setTitre('');
      qc.invalidateQueries({ queryKey: ['company', 'quotes'] });
    },
    onError: (erreur: any) =>
      Alert.alert('Création refusée', erreur?.data?.message ?? 'Votre rôle ne permet pas de chiffrer.'),
  });

  const envoyer = useMutation({
    mutationFn: async (id: number) => apiClient.post(`/provider/company/quotes/${id}/send`, {}),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['company', 'quotes'] }),
    onError: (erreur: any) =>
      // « Un devis sans ligne n'a rien à proposer » est une règle à LIRE : la remplacer par « une
      // erreur est survenue » ferait recommencer la saisie.
      Alert.alert('Envoi refusé', erreur?.data?.message ?? 'Le devis n’a pas pu être envoyé.'),
  });

  return (
    <Screen>
      <Text style={styles.title}>Devis</Text>
      <Text style={styles.intro}>
        {tr('company_quotes.chiffrez_pendant_la_visite_accepte')}
      </Text>

      {peutGerer && (
        <View style={styles.formulaire}>
          <TextInput
            value={titre}
            onChangeText={setTitre}
            placeholder="Objet du devis"
            placeholderTextColor={styles.placeholder.color}
            style={styles.champ}
            testID="champ-titre-devis"
          />
          <Button
            label="Ouvrir le brouillon"
            size="sm"
            fullWidth
            disabled={titre.trim().length === 0 || creer.isPending}
            onPress={() => creer.mutate()}
            testID="bouton-creer-devis"
          />
        </View>
      )}

      <FlatList
        data={devis ?? []}
        keyExtractor={(d) => String(d.id)}
        onRefresh={refetch}
        refreshing={isRefetching}
        renderItem={({ item }) => (
          <View style={styles.ligne} testID={`devis-${item.id}`}>
            <View style={styles.identite}>
              <Text style={styles.nom} numberOfLines={1}>
                {item.title}
              </Text>
              <Text style={styles.detail} numberOfLines={1}>
                {item.reference}
                {item.client_name ? ` · ${item.client_name}` : ''}
                {` · ${formatCentimes(item.total_cents)}`}
              </Text>
            </View>

            <Badge
              label={LIBELLES[item.status] ?? item.status}
              variant={
                item.status === 'accepted'
                  ? 'success'
                  : item.status === 'declined'
                    ? 'danger'
                    : 'neutral'
              }
            />

            {/* Un devis envoyé ne se modifie plus : le bouton disparaît avec le brouillon. */}
            {peutGerer && item.status === 'draft' && (
              <Button
                label="Envoyer"
                size="sm"
                onPress={() => envoyer.mutate(item.id)}
                testID={`envoyer-devis-${item.id}`}
              />
            )}
          </View>
        )}
        ListEmptyComponent={
          <EmptyState
            title="Aucun devis"
            message="Jusqu'ici, seul un administrateur pouvait en saisir un pour vous."
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
    },
    intro: {
      fontSize: typography.fontSize.sm,
      color: t.textMuted,
      marginBottom: spacing.md,
    },
    formulaire: { gap: spacing.xs, marginBottom: spacing.md },
    champ: {
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
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
    },
    detail: { fontSize: typography.fontSize.sm, color: t.textMuted },
  });
