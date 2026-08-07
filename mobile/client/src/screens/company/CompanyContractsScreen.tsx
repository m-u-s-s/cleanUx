import React from 'react';
import { View, FlatList, Text, StyleSheet } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { Screen, Badge, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { ContratSociete } from './types';

/**
 * LES CONTRATS-CADRES, EN LECTURE SEULE.
 *
 * Ce n'est pas une limite de l'écran mobile mais la règle du portail : `ClientContractsCenter`
 * documente « lecture seule des contrats-cadres B2B où l'organisation courante est la partie
 * cliente ». Un contrat se négocie et se signe ailleurs — proposer ici une modification donnerait
 * un bouton qui ne peut aboutir.
 */
export function CompanyContractsScreen() {
  const styles = stylesFor(useThemeColors());

  const { data: contrats, refetch, isRefetching, isError } = useQuery<ContratSociete[]>({
    queryKey: ['client-company', 'contracts'],
    queryFn: async () => (await apiClient.get('/client/company/contracts')).data.data ?? [],
  });

  if (isError) {
    return (
      <Screen>
        <EmptyState
          title="Contrats indisponibles"
          message="Impossible de charger les contrats de votre société."
          actionLabel="Réessayer"
          onAction={() => void refetch()}
        />
      </Screen>
    );
  }

  return (
    <Screen>
      <Text style={styles.title}>Contrats</Text>

      <FlatList
        data={contrats ?? []}
        keyExtractor={(c) => String(c.id)}
        onRefresh={refetch}
        refreshing={isRefetching}
        renderItem={({ item }) => (
          <View style={styles.ligne} testID={`contrat-${item.id}`}>
            <View style={styles.identite}>
              <Text style={styles.nom} numberOfLines={1}>
                {item.reference ?? `Contrat ${item.id}`}
              </Text>
              <Text style={styles.detail} numberOfLines={1}>
                {item.provider ?? 'Prestataire non renseigné'}
                {item.effective_from ? ` · dès le ${item.effective_from}` : ''}
                {item.payment_terms_days ? ` · paiement ${item.payment_terms_days} j` : ''}
              </Text>
            </View>

            <Badge
              label={item.status}
              variant={item.status === 'active' ? 'success' : 'neutral'}
            />
          </View>
        )}
        ListEmptyComponent={
          <EmptyState
            title="Aucun contrat"
            message="Vos contrats-cadres négociés apparaîtront ici."
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
    ligne: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: spacing.sm,
      paddingVertical: spacing.sm,
      borderBottomWidth: StyleSheet.hairlineWidth,
      borderBottomColor: t.border,
    },
    identite: {
      flex: 1,
      minWidth: 0,
    },
    nom: {
      fontSize: typography.fontSize.base,
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
    },
    detail: {
      fontSize: typography.fontSize.sm,
      color: t.textMuted,
    },
  });
