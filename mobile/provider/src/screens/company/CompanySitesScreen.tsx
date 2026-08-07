import React from 'react';
import { View, FlatList, Text, StyleSheet } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { Screen, Badge, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

interface Referent {
  id: number;
  name: string | null;
  role: string;
}

interface SiteDesservi {
  id: number;
  name: string;
  city: string | null;
  postal_code: string | null;
  address: string | null;
  referents: Referent[];
}

/**
 * LES SITES CLIENTS QUE LA SOCIÉTÉ DESSERT.
 *
 * En lecture sur mobile : désigner un référent est un geste d'organisation qu'on pose au bureau,
 * depuis l'écran web, pas entre deux interventions. Ce qu'on veut ici c'est se souvenir de QUI
 * connaît ce bâtiment avant d'y envoyer quelqu'un.
 *
 * Les sites se DÉDUISENT des missions et des contrats-cadres — un prestataire ne possède pas les
 * locaux de ses clients. Et les référents affichés sont ceux de NOTRE société : deux prestataires
 * peuvent desservir le même immeuble.
 */
export function CompanySitesScreen() {
  const styles = stylesFor(useThemeColors());

  const { data: sites, refetch, isRefetching, isError } = useQuery<SiteDesservi[]>({
    queryKey: ['company', 'sites'],
    queryFn: async () => (await apiClient.get('/provider/company/sites')).data.data ?? [],
  });

  if (isError) {
    return (
      <Screen>
        <EmptyState
          title="Sites indisponibles"
          message="Impossible de charger les sites desservis par votre société."
          actionLabel="Réessayer"
          onAction={() => void refetch()}
        />
      </Screen>
    );
  }

  return (
    <Screen>
      <Text style={styles.title}>Sites desservis</Text>

      <FlatList
        data={sites ?? []}
        keyExtractor={(s) => String(s.id)}
        onRefresh={refetch}
        refreshing={isRefetching}
        renderItem={({ item }) => (
          <View style={styles.ligne} testID={`site-${item.id}`}>
            <View style={styles.identite}>
              <Text style={styles.nom} numberOfLines={1}>
                {item.name}
              </Text>
              <Text style={styles.detail} numberOfLines={1}>
                {[item.city, item.postal_code].filter(Boolean).join(' ') || 'Adresse non renseignée'}
              </Text>
              <Text style={styles.detail} numberOfLines={1}>
                {item.referents.length > 0
                  ? item.referents.map((r) => r.name ?? 'Compte supprimé').join(', ')
                  : 'Aucun référent désigné'}
              </Text>
            </View>

            {item.referents.length > 0 ? (
              <Badge label="Référent" variant="success" />
            ) : (
              <Badge label="À couvrir" variant="neutral" />
            )}
          </View>
        )}
        ListEmptyComponent={
          <EmptyState
            title="Aucun site desservi"
            message="Les sites apparaîtront dès votre première mission ou votre premier contrat-cadre."
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
    identite: { flex: 1, minWidth: 0 },
    nom: {
      fontSize: typography.fontSize.base,
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
    },
    detail: { fontSize: typography.fontSize.sm, color: t.textMuted },
  });
