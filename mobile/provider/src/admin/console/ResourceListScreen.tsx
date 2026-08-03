import React, { useMemo, useState } from 'react';
import { FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { Button, EmptyState, ErrorState, Icon, Screen, Skeleton, TextInput } from '@/ui';
import { colors, radius, spacing, typography } from '@/theme';
import { useResourceIndex } from './hooks';
import { formatCell } from './format';
import type { FilterValues, ResourceColumn, ResourceRow } from './types';

interface Params {
  resource: string;
  title: string;
}

/**
 * La liste générique du moteur de console — un écran pour tous les domaines.
 *
 * TOUT CE QU'IL AFFICHE VIENT DU DESCRIPTEUR servi avec la page : colonnes, formatage, filtres,
 * présence ou non d'un formulaire. Il ne connaît aucun domaine, et c'est ce qui lui permet d'en
 * servir des dizaines sans diverger du web.
 *
 * LA RECHERCHE PART AU SERVEUR, elle ne filtre jamais localement. Filtrer sur place ne verrait
 * que les lignes déjà chargées : sur un domaine paginé, une recherche silencieusement limitée à
 * la première page rendrait des résultats faux sans le dire.
 */
export function ResourceListScreen({ route }: { route: { params: Params } }) {
  const { resource, title } = route.params;
  const navigation = useNavigation<{ navigate: (screen: string, params?: object) => void }>();

  const [filters, setFilters] = useState<FilterValues>({});

  const { data, isLoading, isError, refetch, fetchNextPage, hasNextPage, isFetchingNextPage } =
    useResourceIndex(resource, { filters });

  const descripteur = data?.pages[0]?.resource;
  const rows = useMemo(
    () => (data?.pages ?? []).flatMap((page) => page.rows),
    [data],
  );

  // Le filtre de recherche est le seul remonté en tête : c'est celui qu'on utilise sans réfléchir.
  // Les autres vivront dans une feuille de filtres (lot suivant).
  const recherche = descripteur?.filters.find((f) => f.type === 'search');

  if (isLoading) {
    return (
      <Screen>
        <View testID="resource-list-loading" style={{ paddingTop: spacing.md }}>
          {Array.from({ length: 8 }).map((_, index) => (
            <View key={index} style={{ paddingBottom: spacing.sm }}>
              <Skeleton width="100%" height={64} />
            </View>
          ))}
        </View>
      </Screen>
    );
  }

  if (isError) {
    return (
      <Screen>
        <ErrorState
          message={`« ${title} » n’a pas pu être chargé.`}
          onRetry={() => {
            void refetch();
          }}
        />
      </Screen>
    );
  }

  const creable = (descripteur?.form.length ?? 0) > 0;

  return (
    <Screen>
      <View style={styles.header}>
        {recherche ? (
          <TextInput
            label={recherche.label}
            accessibilityLabel={recherche.label}
            value={String(filters[recherche.key] ?? '')}
            onChangeText={(value) => setFilters((f) => ({ ...f, [recherche.key]: value }))}
            autoCorrect={false}
          />
        ) : null}

        {creable ? (
          <Button
            label="Créer"
            variant="secondary"
            size="sm"
            onPress={() => navigation.navigate('AdminResourceForm', { resource, title })}
          />
        ) : null}
      </View>

      <FlatList
        testID="resource-list"
        data={rows}
        keyExtractor={(row) => String(row.id)}
        showsVerticalScrollIndicator={false}
        onEndReachedThreshold={0.4}
        onEndReached={() => {
          // `hasNextPage` garde l'appel : sans lui, atteindre le bas d'une liste terminée
          // relancerait la requête à chaque frôlement du doigt.
          if (hasNextPage && !isFetchingNextPage) {
            void fetchNextPage();
          }
        }}
        renderItem={({ item }) => (
          <Row
            row={item}
            columns={descripteur?.columns ?? []}
            onPress={() =>
              navigation.navigate('AdminResourceDetail', { resource, title, id: item.id })
            }
          />
        )}
        ListEmptyComponent={
          <EmptyState
            title="Aucun résultat"
            message="Aucune ligne ne correspond à ce que vous cherchez."
            icon="search-outline"
          />
        }
        ListFooterComponent={
          isFetchingNextPage ? (
            <View style={{ paddingVertical: spacing.md }}>
              <Skeleton width="100%" height={64} />
            </View>
          ) : null
        }
      />
    </Screen>
  );
}

/**
 * Une ligne : la première colonne en titre, les suivantes en sous-titre.
 *
 * Un tableau à colonnes ne tient pas sur 390 px de large — le transposer en fiche est ce qui rend
 * la console lisible sur téléphone plutôt que scrollable horizontalement.
 */
function Row({
  row,
  columns,
  onPress,
}: {
  row: ResourceRow;
  columns: ResourceColumn[];
  onPress: () => void;
}) {
  const [principale, ...secondaires] = columns;

  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      style={({ pressed }) => [styles.row, pressed && styles.rowPressed]}
    >
      <View style={{ flex: 1 }}>
        <Text style={styles.rowTitle} numberOfLines={1}>
          {principale ? formatCell(row[principale.key], principale.type) : String(row.id)}
        </Text>

        {secondaires.length > 0 ? (
          <Text style={styles.rowMeta} numberOfLines={1}>
            {secondaires
              .map((column) => formatCell(row[column.key], column.type))
              .join(' · ')}
          </Text>
        ) : null}
      </View>

      <Icon name="chevron-forward" size={18} color={colors.surface[400]} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  header: {
    paddingTop: spacing.md,
    paddingBottom: spacing.sm,
    gap: spacing.sm,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    paddingVertical: spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: colors.surface[100],
    borderRadius: radius.md,
  },
  rowPressed: { backgroundColor: colors.surface[100] },
  rowTitle: {
    fontSize: typography.fontSize.base,
    color: colors.surface[900],
    fontFamily: typography.fontFamily.bodyMedium,
  },
  rowMeta: {
    fontSize: typography.fontSize.xs,
    color: colors.surface[500],
    marginTop: 2,
  },
});
