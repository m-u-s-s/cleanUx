import React, { useState } from 'react';
import { View, FlatList, Text, TextInput, StyleSheet, Alert } from 'react-native';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Screen, Button, Badge, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { useAuth, can } from '@/auth';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

interface Article {
  id: number;
  name: string;
  unit: string;
  quantity: number;
  reorder_threshold: number;
  agency_name: string | null;
  needs_reorder: boolean;
}

/**
 * LE STOCK DE CONSOMMABLES (E23), CONSULTÉ ET MOUVEMENTÉ DEPUIS LE TERRAIN.
 *
 * C'est l'écran le plus légitimement mobile des cinq : on regarde ce qui reste AVANT de partir, et
 * on déclare ce qu'on a pris APRÈS l'intervention. Les deux gestes se font debout, à côté d'une
 * camionnette — jamais devant un ordinateur.
 *
 * ON NE SAISIT JAMAIS LE COMPTEUR, on déclare un mouvement. Le stock est le RÉSULTAT des mouvements :
 * dès qu'on peut l'écrire à la main, le registre et le compteur divergent et plus personne ne sait
 * lequel croire.
 *
 * ET LE REFUS DU DOMAINE S'AFFICHE TEL QUEL. « Il ne reste que trois cartons » est la seule réponse
 * utile : la remplacer par « une erreur est survenue » obligerait à rappeler le bureau.
 */
export function CompanyInventoryScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());
  const { user } = useAuth();
  const qc = useQueryClient();

  const [quantites, setQuantites] = useState<Record<number, string>>({});

  const peutGerer = can(user, 'inventory.manage');

  const { data: articles, refetch, isRefetching } = useQuery<Article[]>({
    queryKey: ['company', 'inventory'],
    queryFn: async () => (await apiClient.get('/provider/company/inventory')).data.data ?? [],
  });

  const bouger = useMutation({
    mutationFn: async (params: { id: number; type: 'reception' | 'consumption'; quantity: number }) =>
      apiClient.post(`/provider/company/inventory/${params.id}/movements`, {
        type: params.type,
        quantity: params.quantity,
      }),
    onSuccess: (_data, params) => {
      setQuantites((etat) => ({ ...etat, [params.id]: '' }));
      qc.invalidateQueries({ queryKey: ['company', 'inventory'] });
    },
    onError: (erreur: any) =>
      Alert.alert('Mouvement refusé', erreur?.data?.message ?? 'Le stock n’a pas pu être modifié.'),
  });

  const quantiteDe = (id: number): number => {
    const brut = Number.parseInt(quantites[id] ?? '', 10);

    return Number.isFinite(brut) && brut > 0 ? brut : 0;
  };

  return (
    <Screen>
      <Text style={styles.title}>{tr('company_inventory.consommables')}</Text>
      <Text style={styles.intro}>
        Ce qui reste, et ce que vous prélevez. Le compteur découle des mouvements, jamais l'inverse.
      </Text>

      <FlatList
        data={articles ?? []}
        keyExtractor={(a) => String(a.id)}
        onRefresh={refetch}
        refreshing={isRefetching}
        renderItem={({ item }) => (
          <View style={styles.carte} testID={`article-${item.id}`}>
            <View style={styles.enTete}>
              <View style={styles.identite}>
                <Text style={styles.nom} numberOfLines={1}>
                  {item.name}
                </Text>
                <Text style={styles.detail} numberOfLines={1}>
                  {item.quantity} {item.unit}
                  {item.agency_name ? ` · ${item.agency_name}` : ''}
                </Text>
              </View>

              {item.needs_reorder && <Badge label={tr('company_inventory.stock_bas')} variant="danger" />}
            </View>

            {peutGerer && (
              <View style={styles.actions}>
                <TextInput
                  value={quantites[item.id] ?? ''}
                  onChangeText={(v) => setQuantites((etat) => ({ ...etat, [item.id]: v }))}
                  placeholder="Qté"
                  placeholderTextColor={styles.placeholder.color}
                  keyboardType="number-pad"
                  style={styles.champ}
                  testID={`quantite-${item.id}`}
                />
                <Button
                  label={tr('company_inventory.prelever')}
                  size="sm"
                  variant="ghost"
                  disabled={quantiteDe(item.id) === 0 || bouger.isPending}
                  onPress={() =>
                    bouger.mutate({ id: item.id, type: 'consumption', quantity: quantiteDe(item.id) })
                  }
                  testID={`prelever-${item.id}`}
                />
                <Button
                  label={tr('company_inventory.receptionner')}
                  size="sm"
                  disabled={quantiteDe(item.id) === 0 || bouger.isPending}
                  onPress={() =>
                    bouger.mutate({ id: item.id, type: 'reception', quantity: quantiteDe(item.id) })
                  }
                  testID={`receptionner-${item.id}`}
                />
              </View>
            )}
          </View>
        )}
        ListEmptyComponent={
          <EmptyState
            title={tr('company_inventory.aucun_consommable')}
            message="Déclarez vos articles depuis l'espace société pour suivre ce qui reste."
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
    detail: { fontSize: typography.fontSize.sm, color: t.textMuted },
    actions: { flexDirection: 'row', alignItems: 'center', gap: spacing.xs },
    champ: {
      width: 72,
      borderWidth: StyleSheet.hairlineWidth,
      borderColor: t.border,
      borderRadius: radius.md,
      paddingHorizontal: spacing.sm,
      paddingVertical: spacing.xs,
      color: t.text,
      backgroundColor: t.card,
    },
    placeholder: { color: t.textMuted },
  });
