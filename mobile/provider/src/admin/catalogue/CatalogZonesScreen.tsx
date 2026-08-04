import React from 'react';
import { FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { useNavigation, useRoute } from '@react-navigation/native';
import { Badge, EmptyState, ErrorState, Icon, Screen, Skeleton } from '@/ui';
import { colors, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useResourceIndex } from '../console/hooks';
import type { ResourceRow } from '../console/types';

/**
 * Deuxième niveau : les zones d'un pays.
 *
 * LE CLOISONNEMENT EST FAIT PAR LE SERVEUR. Le filtre `country_id` vit dans le descripteur, pas
 * ici : filtrer à l'affichage laisse passer les actions, et l'écran des zones belges montrerait
 * Paris dès qu'un second marché ouvrirait. Cet écran ne fait que transmettre le pays.
 */
export function CatalogZonesScreen() {
  const styles = stylesFor(useThemeColors());
  const navigation = useNavigation<{ navigate: (screen: string, params?: object) => void }>();
  const route = useRoute<{ key: string; name: string; params: { countryId: number; title?: string } }>();

  const { countryId } = route.params;

  const { data, isLoading, isError, refetch } = useResourceIndex('zones', {
    filters: { country_id: String(countryId) },
    sort: 'name',
  });

  const zones = data?.pages.flatMap((page) => page.rows) ?? [];

  if (isLoading) {
    return (
      <Screen>
        <View style={styles.chargement}>
          <Skeleton width="100%" height={64} />
          <Skeleton width="100%" height={64} />
        </View>
      </Screen>
    );
  }

  if (isError) {
    return (
      <Screen>
        <ErrorState message="Impossible de charger les zones." onRetry={() => refetch()} />
      </Screen>
    );
  }

  return (
    <Screen>
      <Text style={styles.intro}>
        Ouvrez une zone pour voir et régler les métiers qu’elle propose.
      </Text>

      <FlatList
        data={zones}
        keyExtractor={(item) => String(item.id)}
        showsVerticalScrollIndicator={false}
        renderItem={({ item }) => (
          <ZoneRow
            row={item}
            onOpen={() =>
              navigation.navigate('AdminCatalogTrades', {
                zoneId: item.id,
                title: String(item.name ?? 'Catalogue'),
              })
            }
          />
        )}
        ListEmptyComponent={
          <EmptyState
            title="Aucune zone"
            message="Ce pays n’a pas encore de zone. Créez-en une depuis l’administration web."
          />
        }
      />
    </Screen>
  );
}

function ZoneRow({ row, onOpen }: { row: ResourceRow; onOpen: () => void }) {
  const styles = stylesFor(useThemeColors());

  const reservable = row.is_bookable === true || row.is_bookable === 1 || row.is_bookable === '1';

  return (
    <Pressable
      onPress={onOpen}
      accessibilityRole="button"
      accessibilityLabel={`Ouvrir le catalogue de ${String(row.name)}`}
      style={({ pressed }) => [styles.row, pressed && styles.rowPressed]}
    >
      <View style={styles.rowTexte}>
        <Text style={styles.rowTitre}>{String(row.name ?? '—')}</Text>
        <Text style={styles.rowMeta}>
          {String(row.code ?? '')} · {String(row.status ?? '')}
        </Text>
      </View>

      <Badge
        label={reservable ? 'Réservable' : 'Fermée'}
        variant={reservable ? 'success' : 'neutral'}
      />
      <Icon name="chevron-forward" size={18} color={colors.surface[400]} />
    </Pressable>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  chargement: { gap: spacing.sm, paddingTop: spacing.md },
  intro: {
    ...typography.preset.subhead,
    color: t.textSecondary,
    paddingVertical: spacing.md,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    minHeight: 64,
    paddingHorizontal: spacing.sm,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: t.border,
  },
  rowPressed: { backgroundColor: t.inputBg },
  rowTexte: { flex: 1 },
  rowTitre: { ...typography.preset.bodyReadable, color: t.text, fontWeight: '600' },
  rowMeta: { fontSize: typography.fontSize.xs, color: t.textMuted, marginTop: 2 },
});
