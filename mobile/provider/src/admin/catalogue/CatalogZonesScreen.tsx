import React from 'react';
import { FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { useNavigation, useRoute } from '@react-navigation/native';
import { Badge, EmptyState, ErrorState, Icon, Screen, Skeleton } from '@/ui';
import { colors, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { messageDErreur } from './erreur';
import { useResourceAction, useResourceDelete, useResourceIndex } from '../console/hooks';
import type { ResourceRow } from '../console/types';
import { LigneActions } from './LigneActions';
import { useTraduction } from '@/i18n';

/**
 * Deuxième niveau : les zones d'un pays.
 *
 * LE CLOISONNEMENT EST FAIT PAR LE SERVEUR. Le filtre `country_id` vit dans le descripteur, pas
 * ici : filtrer à l'affichage laisse passer les actions, et l'écran des zones belges montrerait
 * Paris dès qu'un second marché ouvrirait. Cet écran ne fait que transmettre le pays.
 */
export function CatalogZonesScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());
  const navigation = useNavigation<{ navigate: (screen: string, params?: object) => void }>();
  const route = useRoute<{ key: string; name: string; params: { countryId: number; title?: string } }>();

  const { countryId } = route.params;

  const { data, isLoading, isError, error, refetch } = useResourceIndex('zones', {
    filters: { country_id: String(countryId) },
    sort: 'name',
  });

  const supprimer = useResourceDelete('zones');
  const agir = useResourceAction('zones');

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
        <ErrorState message={messageDErreur(error, 'Impossible de charger les zones.')} onRetry={() => refetch()} />
      </Screen>
    );
  }

  return (
    <Screen>
      <View style={styles.entete}>
        <Text style={styles.intro}>
          {tr('catalog_zones.ouvrez_une_zone_pour_voir')}
        </Text>

        <Pressable
          onPress={() =>
            navigation.navigate('AdminResourceForm', {
              resource: 'zones',
              title: 'Nouvelle zone',
              // Le pays vient du CONTEXTE : le redemander exposerait à créer la zone dans le
              // mauvais marché, erreur qu'on ne voit qu'en cherchant une zone disparue.
              prefill: { country_id: countryId },
            })
          }
          accessibilityRole="button"
          accessibilityLabel={tr('catalog_zones.ajouter_une_zone')}
          style={({ pressed }) => [styles.ajouter, pressed && styles.ajouterPresse]}
        >
          <Icon name="add" size={18} color={colors.surface[50]} />
          <Text style={styles.ajouterTexte}>{tr('catalog_zones.ajouter')}</Text>
        </Pressable>
      </View>

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
            onEdit={() =>
              navigation.navigate('AdminResourceForm', {
                resource: 'zones',
                title: String(item.name ?? 'Zone'),
                id: item.id,
              })
            }
            onToggle={() => agir.mutate({ id: item.id, action: 'toggle-bookable' })}
            onDelete={() => supprimer.mutate(item.id)}
          />
        )}
        ListEmptyComponent={
          <EmptyState
            title={tr('catalog_zones.aucune_zone')}
            message="Ce pays n’a pas encore de zone. Créez-en une depuis l’administration web."
          />
        }
      />
    </Screen>
  );
}

function ZoneRow({
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
      <LigneActions
        sujet={String(row.name ?? 'cette zone')}
        actions={[
          { cle: 'edit', libelle: 'Modifier', executer: onEdit },
          {
            cle: 'toggle',
            libelle: reservable ? 'Fermer aux réservations' : 'Ouvrir aux réservations',
            executer: onToggle,
          },
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
