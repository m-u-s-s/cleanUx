import React from 'react';
import { View, FlatList, Text, StyleSheet } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { Screen, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

interface Local {
  id: number;
  name: string;
  code: string | null;
  city: string | null;
  address: string | null;
}

/**
 * Les locaux de la société cliente, en natif.
 *
 * L'espace société cliente n'existait que sur le web : `routes/api/client.php` n'exposait que
 * l'annuaire des sociétés prestataires et les réservations. L'API `/client/company/*` a été créée
 * avec ces écrans.
 */
export function CompanySitesScreen() {
  const styles = stylesFor(useThemeColors());

  const { data: locaux, refetch, isRefetching } = useQuery<Local[]>({
    queryKey: ['client-company', 'sites'],
    queryFn: async () => (await apiClient.get('/client/company/sites')).data.data ?? [],
  });

  return (
    <Screen>
      <Text style={styles.title}>Mes locaux</Text>

      <FlatList
        data={locaux ?? []}
        keyExtractor={(l) => String(l.id)}
        onRefresh={refetch}
        refreshing={isRefetching}
        renderItem={({ item }) => (
          <View style={styles.ligne} testID={`local-${item.id}`}>
            <Text style={styles.nom} numberOfLines={1}>
              {item.name}
            </Text>
            <Text style={styles.detail} numberOfLines={1}>
              {item.code ?? '—'}
              {item.city ? ` · ${item.city}` : ''}
              {item.address ? ` · ${item.address}` : ''}
            </Text>
          </View>
        )}
        ListEmptyComponent={
          <EmptyState
            title="Aucun local"
            message="Ajoutez vos sites depuis l'espace société sur le web."
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
      paddingVertical: spacing.sm,
      borderBottomWidth: StyleSheet.hairlineWidth,
      borderBottomColor: t.border,
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
