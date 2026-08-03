import React from 'react';
import { RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/api';
import { ErrorState, KPICard, Screen, Skeleton } from '@/ui';
import {spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { formatCell } from './format';

interface Params {
  report: string;
  title: string;
}

type Tone = 'neutral' | 'success' | 'warning' | 'danger';

interface ReportTile {
  key: string;
  label: string;
  value: number | string;
  format: 'number' | 'money';
  hint: string | null;
  tone: Tone;
  /** `false` quand la tuile n'a pas pu être mesurée — à distinguer d'un zéro mesuré. */
  available: boolean;
}

interface ReportSection {
  title: string;
  tiles: ReportTile[];
}

function useReport(report: string) {
  return useQuery<ReportSection[]>({
    queryKey: ['admin', 'console', 'report', report],
    queryFn: async () =>
      (await apiClient.get(`/admin/console/reports/${report}`)).data.sections ?? [],
  });
}

/**
 * Un module d'administration servi comme SYNTHÈSE plutôt que comme liste.
 *
 * Dix pages n'ont aucune table derrière elles : l'accueil, les alertes, la préparation de la
 * plateforme, la santé financière. Les rendre en liste aurait montré un écran vide en prétendant
 * couvrir le domaine — le mensonge que le registre de couverture sert justement à empêcher.
 *
 * UNE TUILE NON MESURABLE AFFICHE « — », JAMAIS « 0 ». Le serveur distingue les deux cas, et
 * l'écran doit tenir cette distinction : un « 0 litige ouvert » montré parce que la requête a
 * échoué ferait croire à un calme qui n'existe pas, et personne n'irait vérifier.
 */
export function ReportScreen({ route }: { route: { params: Params } }) {
  const styles = stylesFor(useThemeColors());

  const { report, title } = route.params;
  const { data, isLoading, isError, refetch, isRefetching } = useReport(report);

  if (isLoading) {
    return (
      <Screen>
        <View testID="report-loading" style={styles.grid}>
          {Array.from({ length: 4 }).map((_, index) => (
            <View key={index} style={styles.cell}>
              <Skeleton width="100%" height={86} />
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

  return (
    <Screen>
      <ScrollView
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl refreshing={isRefetching} onRefresh={() => void refetch()} />
        }
      >
        {(data ?? []).map((section) => (
          <View key={section.title}>
            <Text style={styles.section}>{section.title}</Text>

            <View style={styles.grid}>
              {section.tiles.map((tile) => (
                <View key={tile.key} style={styles.cell}>
                  <KPICard
                    title={tile.label}
                    value={
                      tile.available
                        ? formatCell(tile.value, tile.format === 'money' ? 'money' : 'number')
                        : '—'
                    }
                    hint={tile.available ? (tile.hint ?? undefined) : 'non mesurable'}
                    tone={tile.available ? tile.tone : 'neutral'}
                  />
                </View>
              ))}
            </View>
          </View>
        ))}
      </ScrollView>
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  section: {
    ...typography.preset.subhead,
    color: t.textSecondary,
    textTransform: 'uppercase',
    marginTop: spacing.md,
    marginBottom: spacing.sm,
  },
  grid: { flexDirection: 'row', flexWrap: 'wrap', marginHorizontal: -spacing.xs },
  cell: { width: '50%', paddingHorizontal: spacing.xs, paddingBottom: spacing.sm },
});
