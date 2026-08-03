import React, { useMemo, useState } from 'react';
import { Pressable, SectionList, StyleSheet, Text, View } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { Badge, ErrorState, Icon, Screen, Skeleton, TextInput } from '@/ui';
import { colors, radius, spacing, typography } from '@/theme';
import { useAdminCatalog } from './hooks';
import { NATIVE_ADMIN_SCREENS } from './nativeScreens';
import type { AdminModule } from './types';

/**
 * L'annuaire des modules d'administration.
 *
 * IL AFFICHE TOUT, Y COMPRIS CE QUI N'EST PAS PRÊT. Le registre serveur recense les 99 pages de
 * l'administration web ; celles que le mobile ne sert pas encore nativement apparaissent marquées
 * « à venir » et ne sont pas navigables. Les masquer donnerait une application qui a l'air
 * complète et un chantier dont personne ne peut mesurer l'avancement — le compteur en tête dit le
 * chiffre exact.
 *
 * OUVRIR UN ÉCRAN VIDE SERAIT PIRE QUE L'ANNONCER : un module marqué ne réagit pas au toucher.
 */
export function AdminDirectoryScreen() {
  const navigation = useNavigation<{ navigate: (screen: string, params?: object) => void }>();
  const { data, isLoading, isError, refetch } = useAdminCatalog();
  const [query, setQuery] = useState('');

  const sections = useMemo(() => {
    const groups = data?.groups ?? [];
    const needle = query.trim().toLowerCase();

    return groups
      .map((group) => ({
        key: group.key,
        title: group.title,
        data: needle
          ? group.modules.filter((m) => m.title.toLowerCase().includes(needle))
          : group.modules,
      }))
      .filter((section) => section.data.length > 0);
  }, [data, query]);

  if (isLoading) {
    return (
      <Screen>
        <View testID="admin-directory-loading" style={{ paddingTop: spacing.md }}>
          {Array.from({ length: 8 }).map((_, index) => (
            <View key={index} style={{ paddingBottom: spacing.sm }}>
              <Skeleton width="100%" height={52} />
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
          message="L’annuaire n’a pas pu être chargé."
          onRetry={() => {
            void refetch();
          }}
        />
      </Screen>
    );
  }

  const counts = data?.counts ?? { total: 0, covered: 0, pending: 0 };

  /**
   * Ouvre un module : écran sur-mesure s'il en a un, moteur de console sinon.
   *
   * La clé de module EST la clé de ressource — le registre serveur refuse que les deux divergent,
   * et `nativeScreens.test.ts` refuse qu'un module annoncé sur-mesure n'ait pas son écran.
   */
  const ouvrir = (module: AdminModule) => {
    const surMesure = NATIVE_ADMIN_SCREENS[module.key];

    if (surMesure) {
      navigation.navigate(surMesure.screen, { title: module.title });

      return;
    }

    navigation.navigate('AdminResourceList', { resource: module.key, title: module.title });
  };

  return (
    <Screen>
      <View style={styles.header}>
        <Text style={styles.progress}>
          {counts.covered} / {counts.total} modules disponibles
        </Text>
        <TextInput
          label="Rechercher un module"
          value={query}
          onChangeText={setQuery}
          accessibilityLabel="Rechercher un module"
          autoCorrect={false}
        />
      </View>

      <SectionList
        sections={sections}
        keyExtractor={(item) => item.key}
        stickySectionHeadersEnabled
        showsVerticalScrollIndicator={false}
        renderSectionHeader={({ section }) => (
          <Text style={styles.sectionHeader}>{section.title}</Text>
        )}
        renderItem={({ item }) => (
          <ModuleRow
            module={item}
            onOpen={() => ouvrir(item)}
          />
        )}
        ListEmptyComponent={<Text style={styles.empty}>Aucun module ne correspond.</Text>}
      />
    </Screen>
  );
}

function ModuleRow({ module, onOpen }: { module: AdminModule; onOpen: () => void }) {
  const disponible = module.coverage !== 'pending';

  return (
    <Pressable
      onPress={disponible ? onOpen : undefined}
      disabled={!disponible}
      accessibilityRole="button"
      accessibilityState={{ disabled: !disponible }}
      style={({ pressed }) => [styles.row, pressed && disponible && styles.rowPressed]}
    >
      <Icon
        name={module.icon as never}
        size={22}
        color={disponible ? colors.brand[500] : colors.surface[400]}
      />
      <Text style={[styles.rowTitle, !disponible && styles.rowTitleMuted]}>{module.title}</Text>
      {disponible ? (
        <Icon name="chevron-forward" size={18} color={colors.surface[400]} />
      ) : (
        <Badge label="À venir" variant="neutral" />
      )}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  header: { paddingTop: spacing.md, paddingBottom: spacing.sm },
  progress: {
    ...typography.preset.subhead,
    color: colors.surface[500],
    marginBottom: spacing.sm,
  },
  sectionHeader: {
    ...typography.preset.subhead,
    color: colors.surface[600],
    backgroundColor: colors.surface[50],
    paddingVertical: spacing.xs,
    textTransform: 'uppercase',
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    paddingVertical: spacing.md,
    borderRadius: radius.md,
  },
  rowPressed: { backgroundColor: colors.surface[100] },
  rowTitle: { flex: 1, fontSize: typography.fontSize.base, color: colors.surface[900] },
  rowTitleMuted: { color: colors.surface[500] },
  empty: {
    paddingVertical: spacing.xl,
    textAlign: 'center',
    color: colors.surface[500],
  },
});
