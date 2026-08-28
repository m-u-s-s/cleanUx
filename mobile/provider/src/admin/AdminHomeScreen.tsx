import React from 'react';
import { RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { ErrorState, KPICard, Screen, Skeleton } from '@/ui';
import {spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useAdminOverview } from './hooks';
import type { AdminKpi } from './types';
import { useTraduction } from '@/i18n';

/**
 * L'accueil de la console d'administration.
 *
 * SEPT NOMBRES, PAS UN TABLEAU DE BORD. Un accueil de téléphone se lit en trois secondes ; les
 * analyses fines restent sur les pages dédiées de l'annuaire.
 *
 * UN COMPTEUR NON MESURABLE S'AFFICHE « — », JAMAIS « 0 ». Le serveur distingue les deux cas
 * (`available`), et l'écran doit tenir cette distinction : un « 0 litige ouvert » montré parce
 * que la requête a échoué ferait croire à un calme qui n'existe pas, et personne n'irait vérifier.
 */
export function AdminHomeScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  const { data, isLoading, isError, refetch, isRefetching } = useAdminOverview();

  if (isLoading) {
    return (
      <Screen>
        <View testID="admin-home-loading" style={styles.grid}>
          {Array.from({ length: 6 }).map((_, index) => (
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
          message="Les indicateurs n’ont pas pu être chargés."
          onRetry={() => {
            void refetch();
          }}
        />
      </Screen>
    );
  }

  const kpis = data ?? [];

  return (
    <Screen>
      <ScrollView
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl refreshing={isRefetching} onRefresh={() => void refetch()} />
        }
      >
        <Text style={styles.heading}>{tr('admin_home.vue_densemble')}</Text>

        <View style={styles.grid}>
          {kpis.map((kpi: AdminKpi) => (
            <View key={kpi.key} style={styles.cell}>
              <KPICard
                title={kpi.label}
                value={kpi.available ? kpi.value : '—'}
                hint={kpi.available ? undefined : 'non mesurable'}
                tone={toneFor(kpi)}
              />
            </View>
          ))}
        </View>
      </ScrollView>
    </Screen>
  );
}

/**
 * La couleur suit ce qui APPELLE UNE ACTION, pas la taille du nombre.
 *
 * Une file d'attente non vide (litiges, KYC, prestataires à valider) est une charge de travail :
 * elle s'annonce. Un compte d'utilisateurs, lui, n'a aucune raison d'être alarmant.
 */
function toneFor(kpi: AdminKpi): 'neutral' | 'warning' | 'danger' {
  if (!kpi.available) {
    return 'neutral';
  }

  if (kpi.key === 'claims_open' && kpi.value > 0) {
    return 'danger';
  }

  const files = ['bookings_pending', 'kyc_pending', 'providers_pending'];

  return files.includes(kpi.key) && kpi.value > 0 ? 'warning' : 'neutral';
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  heading: {
    ...typography.preset.headline,
    color: t.text,
    marginTop: spacing.md,
    marginBottom: spacing.md,
  },
  grid: { flexDirection: 'row', flexWrap: 'wrap', marginHorizontal: -spacing.xs },
  cell: { width: '50%', paddingHorizontal: spacing.xs, paddingBottom: spacing.sm },
});
