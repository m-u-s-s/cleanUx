import React, { useState } from 'react';
/* Chemin direct plutot que le baril : des suites mockent les barils a la main. */
import { formatCentimes } from '@/format/money';
import { View, FlatList, Text, StyleSheet, Alert } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Screen, Button, Badge, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

interface Devis {
  id: number;
  reference: string;
  title: string;
  provider_name: string | null;
  status: string;
  total_cents: number;
  valid_until: string | null;
  is_open: boolean;
}

interface Ligne {
  id: number;
  label: string;
  trade_name: string | null;
  quantity: number;
  unit: string;
  total_cents: number;
}

const LIBELLES: Record<string, string> = {
  sent: 'À décider',
  accepted: 'Accepté',
  declined: 'Refusé',
  expired: 'Périmé',
  cancelled: 'Annulé',
};

/**
 * LES DEVIS REÇUS (E24) — la moitié manquante du module.
 *
 * Une société qui bâtit un devis et l'envoie à un client qui ne peut pas y répondre n'a rien
 * envoyé : le client découvre le document par une notification POUSSÉE SUR SON TÉLÉPHONE, et
 * devrait ouvrir un ordinateur pour dire oui. C'est exactement le point où une affaire se perd.
 *
 * ACCEPTER CRÉE LE TRAVAIL, pas un accusé de réception : chaque ligne porte un métier et devient un
 * rendez-vous. L'écran le dit, sans quoi le client croirait n'avoir signé qu'un papier.
 *
 * UN DEVIS PÉRIMÉ NE S'ACCEPTE PAS, même si le balayage serveur n'est pas passé — `is_open` porte
 * cette vérité, et non le seul statut.
 */
export function ReceivedQuotesScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());
  const qc = useQueryClient();

  const [ouvert, setOuvert] = useState<number | null>(null);

  const { data: devis, refetch, isRefetching } = useQuery<Devis[]>({
    queryKey: ['client', 'quotes'],
    queryFn: async () => (await apiClient.get('/client/quotes')).data.data ?? [],
  });

  const { data: detail } = useQuery<{ lines: Ligne[] }>({
    queryKey: ['client', 'quote', ouvert],
    enabled: ouvert !== null,
    queryFn: async () => (await apiClient.get(`/client/quotes/${ouvert}`)).data.data,
  });

  const decider = useMutation({
    mutationFn: async (params: { id: number; accepte: boolean }) =>
      apiClient.post(
        `/client/quotes/${params.id}/${params.accepte ? 'accept' : 'decline'}`,
        {},
      ),
    onSuccess: (reponse: any, params) => {
      qc.invalidateQueries({ queryKey: ['client', 'quotes'] });

      if (params.accepte) {
        const crees = reponse?.data?.data?.bookings_created ?? 0;

        // Accepter CRÉE le travail : le dire évite que le client croie n'avoir signé qu'un papier.
        Alert.alert(
          tr('received_quotes.devis_accepte'),
          `${crees} rendez-vous créé(s). Le prestataire vous contactera pour les planifier.`,
        );
      }
    },
    onError: (erreur: any) =>
      // « Ce devis n'est plus valable » est une réponse, pas une panne.
      Alert.alert(tr('received_quotes.action_refusee'), erreur?.data?.message ?? 'Le devis n’a pas pu être traité.'),
  });

  return (
    <Screen>
      <Text style={styles.title}>{tr('received_quotes.devis_recus')}</Text>
      <Text style={styles.intro}>{tr('received_quotes.accepter_cree_les_rendez_vous')}</Text>

      <FlatList
        data={devis ?? []}
        keyExtractor={(d) => String(d.id)}
        onRefresh={refetch}
        refreshing={isRefetching}
        renderItem={({ item }) => (
          <View style={styles.carte} testID={`devis-${item.id}`}>
            <View style={styles.enTete}>
              <View style={styles.identite}>
                <Text style={styles.nom} numberOfLines={1}>
                  {item.title}
                </Text>
                <Text style={styles.detail} numberOfLines={1}>
                  {item.provider_name ?? 'Prestataire'} · {formatCentimes(item.total_cents)}
                  {item.valid_until ? ` · jusqu'au ${item.valid_until}` : ''}
                </Text>
              </View>

              <Badge
                label={LIBELLES[item.status] ?? item.status}
                variant={
                  item.status === 'accepted' ? 'success' : item.is_open ? 'info' : 'neutral'
                }
              />
            </View>

            <View style={styles.actions}>
              <Button
                label={ouvert === item.id ? 'Masquer le détail' : 'Voir le détail'}
                size="sm"
                variant="ghost"
                onPress={() => setOuvert(ouvert === item.id ? null : item.id)}
                testID={`ouvrir-devis-${item.id}`}
              />

              {item.is_open && (
                <>
                  <Button
                    label={tr('received_quotes.accepter')}
                    size="sm"
                    disabled={decider.isPending}
                    onPress={() => decider.mutate({ id: item.id, accepte: true })}
                    testID={`accepter-devis-${item.id}`}
                  />
                  <Button
                    label={tr('received_quotes.refuser')}
                    size="sm"
                    variant="ghost"
                    disabled={decider.isPending}
                    onPress={() => decider.mutate({ id: item.id, accepte: false })}
                    testID={`refuser-devis-${item.id}`}
                  />
                </>
              )}
            </View>

            {ouvert === item.id &&
              (detail?.lines ?? []).map((ligne) => (
                <View key={ligne.id} style={styles.ligneDetail}>
                  <Text style={styles.detail} numberOfLines={1}>
                    {ligne.label} — {ligne.trade_name ?? 'métier'} · {ligne.quantity} {ligne.unit}
                  </Text>
                  <Text style={styles.montant}>{formatCentimes(ligne.total_cents)}</Text>
                </View>
              ))}
          </View>
        )}
        ListEmptyComponent={<EmptyState title={tr('received_quotes.aucun_devis')} message="Vous n'avez reçu aucun devis." />}
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
    carte: {
      paddingVertical: spacing.sm,
      borderBottomWidth: StyleSheet.hairlineWidth,
      borderBottomColor: t.border,
      gap: spacing.xs,
    },
    enTete: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
    identite: { flex: 1, minWidth: 0 },
    nom: {
      fontSize: typography.fontSize.base,
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
    },
    detail: { fontSize: typography.fontSize.sm, color: t.textMuted, flex: 1 },
    montant: {
      fontSize: typography.fontSize.sm,
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
    },
    actions: { flexDirection: 'row', alignItems: 'center', gap: spacing.xs },
    ligneDetail: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: spacing.sm,
      paddingVertical: spacing.xs / 2,
    },
  });
