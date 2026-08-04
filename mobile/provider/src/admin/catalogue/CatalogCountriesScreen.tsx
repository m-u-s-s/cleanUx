import React from 'react';
import { FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { Badge, EmptyState, ErrorState, Icon, Screen, Skeleton } from '@/ui';
import { colors, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { messageDErreur } from './erreur';
import { useResourceAction, useResourceDelete, useResourceIndex } from '../console/hooks';
import type { ResourceRow } from '../console/types';
import { LigneActions } from './LigneActions';

/**
 * Premier niveau du catalogue : les pays.
 *
 * IL REFLÈTE LE WEB, et c'est la raison d'être de cet écran. Le module servait jusqu'ici une liste
 * PLATE de secteurs sur mobile, alors que le web est devenu une descente Pays → Zones → Métiers.
 * Deux descriptions différentes du même catalogue, c'est celle du terrain qu'on croit — et elle
 * était fausse.
 *
 * IL NE CRÉE NI NE SUPPRIME. Ouvrir un marché engage la facturation et la conformité ; ces gestes
 * restent sur le web, où l'écran montre leurs conséquences. Le mobile sert à consulter et à ouvrir
 * ou fermer des métiers en déplacement.
 */
export function CatalogCountriesScreen() {
  const styles = stylesFor(useThemeColors());
  const navigation = useNavigation<{ navigate: (screen: string, params?: object) => void }>();

  const { data, isLoading, isError, error, refetch } = useResourceIndex('countries', { sort: 'name' });

  const supprimer = useResourceDelete('countries');
  const agir = useResourceAction('countries');


  const pays = data?.pages.flatMap((page) => page.rows) ?? [];

  if (isLoading) {
    return (
      <Screen>
        <View style={styles.chargement}>
          <Skeleton width="100%" height={64} />
          <Skeleton width="100%" height={64} />
          <Skeleton width="100%" height={64} />
        </View>
      </Screen>
    );
  }

  if (isError) {
    return (
      <Screen>
        <ErrorState message={messageDErreur(error, 'Impossible de charger les pays.')} onRetry={() => refetch()} />
      </Screen>
    );
  }

  return (
    <Screen>
      <View style={styles.entete}>
        <Text style={styles.intro}>
          Chaque pays contient ses zones, et chaque zone son propre catalogue de métiers.
        </Text>

        <Pressable
          onPress={() =>
            navigation.navigate('AdminResourceForm', { resource: 'countries', title: 'Nouveau pays' })
          }
          accessibilityRole="button"
          accessibilityLabel="Ajouter un pays"
          style={({ pressed }) => [styles.ajouter, pressed && styles.ajouterPresse]}
        >
          <Icon name="add" size={18} color={colors.surface[50]} />
          <Text style={styles.ajouterTexte}>Ajouter</Text>
        </Pressable>
      </View>

      <FlatList
        data={pays}
        keyExtractor={(item) => String(item.id)}
        showsVerticalScrollIndicator={false}
        renderItem={({ item }) => (
          <CountryRow
            row={item}
            onOpen={() =>
              navigation.navigate('AdminCatalogZones', {
                countryId: item.id,
                title: String(item.name ?? 'Zones'),
              })
            }
            onEdit={() =>
              navigation.navigate('AdminResourceForm', {
                resource: 'countries',
                title: String(item.name ?? 'Pays'),
                id: item.id,
              })
            }
            onToggle={() => agir.mutate({ id: item.id, action: 'toggle-active' })}
            onDelete={() => supprimer.mutate(item.id)}
          />
        )}
        ListEmptyComponent={
          <EmptyState
            title="Aucun pays"
            message="Ajoutez un pays depuis l’administration web pour commencer."
          />
        }
      />
    </Screen>
  );
}

function CountryRow({
  row,
  onOpen,
  onEdit,
  onToggle,
  onDelete,
}: {
  row: ResourceRow;
  onOpen: () => void;
  onEdit: () => void;
  onToggle: () => void;
  onDelete: () => void;
}) {
  const styles = stylesFor(useThemeColors());

  const actif = row.is_active === true || row.is_active === 1 || row.is_active === '1';

  return (
    <Pressable
      onPress={onOpen}
      accessibilityRole="button"
      accessibilityLabel={`Ouvrir les zones de ${String(row.name)}`}
      style={({ pressed }) => [styles.row, pressed && styles.rowPressed]}
    >
      <View style={styles.rowTexte}>
        <Text style={styles.rowTitre}>{String(row.name ?? '—')}</Text>
        <Text style={styles.rowMeta}>
          {String(row.iso_code ?? '')} · {String(row.currency_code ?? '')}
        </Text>
      </View>

      {/*
        L'état du pays vaut d'être vu ici : un pays inactif rend ses zones injoignables sans que
        leur réglage propre ait changé, ce qui se cherche longtemps si l'écran ne le dit pas.
      */}
      <Badge label={actif ? 'Actif' : 'Inactif'} variant={actif ? 'success' : 'neutral'} />

      <LigneActions
        sujet={String(row.name ?? 'ce pays')}
        actions={[
          { cle: 'edit', libelle: 'Modifier', executer: onEdit },
          { cle: 'toggle', libelle: actif ? 'Désactiver' : 'Activer', executer: onToggle },
          { cle: 'delete', libelle: 'Supprimer', destructive: true, executer: onDelete },
        ]}
      />

      <Icon name="chevron-forward" size={18} color={colors.surface[400]} />
    </Pressable>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  chargement: { gap: spacing.sm, paddingTop: spacing.md },
  entete: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: spacing.sm,
  },
  ajouter: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.xs,
    minHeight: 44,
    paddingHorizontal: spacing.md,
    borderRadius: 12,
    backgroundColor: colors.brand[500],
  },
  ajouterPresse: { opacity: 0.85 },
  ajouterTexte: { fontSize: typography.fontSize.sm, color: t.textOnBrand, fontWeight: '600' },

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
