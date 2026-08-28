import React, { useMemo, useState } from 'react';
import { FlatList, Pressable, StyleSheet, Switch, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { Alert } from 'react-native';
import { Button, EmptyState, ErrorState, Icon, Screen, Skeleton, TextInput } from '@/ui';
import { colors, radius, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { ActionInputSheet } from './ActionInputSheet';
import { OptionPicker } from './OptionPicker';
import { readServerErrors, useResourceGlobalAction, useResourceIndex } from './hooks';
import { formatCell } from './format';
import type { FilterValues, ResourceAction, ResourceColumn, ResourceRow } from './types';
import { useTraduction } from '@/i18n';

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
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  const { resource, title } = route.params;
  const navigation = useNavigation<{ navigate: (screen: string, params?: object) => void }>();

  const [filters, setFilters] = useState<FilterValues>({});

  /*
   * Les actions GLOBALES portent sur le module, pas sur une ligne : purger un cache, relancer une
   * file, simuler un matching. Les poser dans le menu d'une ligne aurait laissé croire qu'elles
   * s'appliquent à celle qu'on vient de toucher.
   */
  const [saisieEnCours, setSaisieEnCours] = useState<ResourceAction | null>(null);
  const [erreursSaisie, setErreursSaisie] = useState<Record<string, string>>({});
  const actionGlobale = useResourceGlobalAction(resource);

  /*
   * Cent vingt-huit colonnes triables sont déclarées à travers la console, quarante et un modules
   * en offrant plus d'une. Le hook savait les envoyer depuis le début ; l'écran ne les lui passait
   * jamais. Sur une liste paginée, ne pas pouvoir trier oblige à faire défiler pour trouver ce
   * qu'un tri montrerait en tête.
   *
   * `undefined` au départ, pas le tri par défaut du descripteur : le serveur l'applique déjà, et
   * le répéter ici en ferait une seconde source à garder d'accord avec la première.
   */
  const [tri, setTri] = useState<string | undefined>(undefined);
  const [sens, setSens] = useState<'asc' | 'desc' | undefined>(undefined);

  const { data, isLoading, isError, refetch, fetchNextPage, hasNextPage, isFetchingNextPage } =
    useResourceIndex(resource, { filters, sort: tri, direction: sens });

  const descripteur = data?.pages[0]?.resource;
  const rows = useMemo(
    () => (data?.pages ?? []).flatMap((page) => page.rows),
    [data],
  );

  // La recherche vient en premier : c'est le filtre qu'on utilise sans réfléchir. Les listes et
  // les booléens la suivent, dans l'ordre où le descripteur les déclare — le même que sur le web.
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
  const globales = descripteur?.global_actions ?? [];

  /** Lancer un geste global, une fois sa saisie éventuelle recueillie. */
  const lancer = (action: ResourceAction, values?: Record<string, unknown>) => {
    actionGlobale.mutate(
      { action: action.key, values },
      {
        onSuccess: () => {
          setSaisieEnCours(null);
          setErreursSaisie({});
        },
        onError: (e) => {
          const lu = readServerErrors(e);
          setErreursSaisie(lu.fields);

          // Sans saisie ouverte, l'erreur n'a aucun endroit où s'afficher : on la dit.
          if (Object.keys(lu.fields).length === 0) {
            Alert.alert(action.label, lu.message);
          }
        },
      },
    );
  };

  /** Un geste destructif s'annonce AVANT de partir : le serveur, lui, ne demandera rien. */
  const demarrer = (action: ResourceAction) => {
    if (action.fields.length > 0) {
      setErreursSaisie({});
      setSaisieEnCours(action);

      return;
    }

    if (action.confirm) {
      Alert.alert(action.label, action.confirm, [
        { text: 'Annuler', style: 'cancel' },
        { text: 'Confirmer', style: 'destructive', onPress: () => lancer(action) },
      ]);

      return;
    }

    lancer(action);
  };

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

        {/*
          * Les filtres AUTRES que la recherche : soixante listes et dix booléens à travers la
          * console, déclarés par les descripteurs et jusqu'ici rendus par le web seul. Sur un
          * domaine paginé de plusieurs milliers de lignes, « statut = litige ouvert » est la
          * différence entre un écran utilisable et un écran décoratif.
          */}
        {(descripteur?.filters ?? [])
          .filter((f) => f.type === 'select' && f.options.length > 0)
          .map((f) => (
            <OptionPicker
              key={f.key}
              label={f.label}
              options={f.options}
              value={filters[f.key] === undefined ? null : String(filters[f.key])}
              onChange={(v) =>
                setFilters((courants) => {
                  const suivants = { ...courants };

                  // Retirer la clé plutôt que d'envoyer une chaîne vide : le serveur traiterait
                  // « status= » comme un filtre posé sur une valeur qui n'existe pas.
                  if (v === null) {
                    delete suivants[f.key];
                  } else {
                    suivants[f.key] = v;
                  }

                  return suivants;
                })
              }
              effacable
            />
          ))}

        {(descripteur?.filters ?? [])
          .filter((f) => f.type === 'bool')
          .map((f) => (
            <View key={f.key} style={styles.bascule}>
              <Text style={styles.basculeLabel}>{f.label}</Text>
              <Switch
                accessibilityLabel={f.label}
                value={filters[f.key] === true}
                onValueChange={(actif) =>
                  setFilters((courants) => {
                    const suivants = { ...courants };

                    if (actif) {
                      suivants[f.key] = true;
                    } else {
                      delete suivants[f.key];
                    }

                    return suivants;
                  })
                }
              />
            </View>
          ))}

        {/*
          * Le tri n'est proposé que s'il y a un choix à faire. Une seule colonne triable et un
          * sélecteur laisserait croire à une liberté qui n'existe pas.
          */}
        {(descripteur?.sorts.length ?? 0) > 1 ? (
          <View style={styles.rangeeTri}>
            <View style={styles.triChoix}>
              <OptionPicker
                label={tr('resource_list.trier_par')}
                options={(descripteur?.sorts ?? []).map((cle) => ({
                  value: cle,
                  label: libelleDeTri(cle, descripteur?.columns ?? []),
                }))}
                value={tri ?? descripteur?.default_sort ?? null}
                onChange={(v) => setTri(v ?? undefined)}
              />
            </View>

            <Pressable
              accessibilityRole="button"
              accessibilityLabel={tr('resource_list.inverser_le_sens_du_tri')}
              onPress={() => setSens((courant) => (courant === 'asc' ? 'desc' : 'asc'))}
              style={styles.sens}
            >
              <Icon
                name={sens === 'asc' ? 'arrow-up' : 'arrow-down'}
                size={18}
                color={colors.surface[400]}
              />
            </Pressable>
          </View>
        ) : null}

        {globales.map((action) => (
          <Button
            key={action.key}
            label={action.label}
            variant="secondary"
            size="sm"
            disabled={actionGlobale.isPending}
            onPress={() => demarrer(action)}
          />
        ))}

        {creable ? (
          <Button
            label={tr('resource_list.creer')}
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
            title={tr('resource_list.aucun_resultat')}
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

      <ActionInputSheet
        action={saisieEnCours}
        visible={saisieEnCours !== null}
        submitting={actionGlobale.isPending}
        errors={erreursSaisie}
        onCancel={() => setSaisieEnCours(null)}
        onSubmit={(values) => saisieEnCours && lancer(saisieEnCours, values)}
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
  const styles = stylesFor(useThemeColors());

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

/**
 * Le nom lisible d'une colonne de tri.
 *
 * Le serveur envoie des CLÉS (`sorts: ['id', 'created_at']`), pas des libellés. Quand la clé
 * correspond à une colonne affichée, on reprend son libellé — c'est le même mot que celui que
 * l'administrateur lit dans la liste. Sinon on humanise la clé, faute de mieux : une table de
 * traductions ici divergerait du serveur au premier renommage.
 */
function libelleDeTri(cle: string, colonnes: ResourceColumn[]): string {
  const colonne = colonnes.find((c) => c.key === cle);

  if (colonne) {
    return colonne.label;
  }

  const mots = cle.replace(/_/g, ' ');

  return mots.charAt(0).toUpperCase() + mots.slice(1);
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  rangeeTri: { flexDirection: 'row', alignItems: 'flex-start' },
  triChoix: { flex: 1 },
  sens: {
    width: 48,
    minHeight: 56,
    alignItems: 'center',
    justifyContent: 'center',
    marginLeft: spacing.xs,
  },
  bascule: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingBottom: spacing.sm,
  },
  basculeLabel: { ...typography.preset.bodyReadable, color: t.text },
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
    borderBottomColor: t.inputBg,
    borderRadius: radius.md,
  },
  rowPressed: { backgroundColor: t.inputBg },
  rowTitle: {
    fontSize: typography.fontSize.base,
    color: t.text,
    fontFamily: typography.fontFamily.bodyMedium,
  },
  rowMeta: {
    fontSize: typography.fontSize.xs,
    color: t.textSecondary,
    marginTop: 2,
  },
});
