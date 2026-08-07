import React from 'react';
import { View, Text, ScrollView, StyleSheet } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Screen, Button, KPICard, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';

interface AccueilSociete {
  organization: { id: number; name: string };
  kpis: {
    missions_today: number;
    missions_active: number;
    missions_delayed: number;
    missions_unassigned: number;
    members_active: number;
    open_tasks: number;
  };
}

/**
 * L'ACCUEIL DE L'ESPACE SOCIÉTÉ PRESTATAIRE.
 *
 * Ce que regarde un gérant en ouvrant son application le matin : combien de missions aujourd'hui,
 * combien en retard, combien n'ont encore personne. Le dernier chiffre est le plus utile et c'est
 * celui qui manquait partout — une mission sans responsable la veille au soir est un client qui
 * découvrira l'absence le lendemain.
 *
 * Un seul appel, `/provider/company/overview` : reconstituer ces nombres depuis les autres points
 * demandait quatre requêtes et autant d'occasions de dériver de ce que l'écran web affiche.
 */
export function CompanyOverviewScreen() {
  const styles = stylesFor(useThemeColors());
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();

  const { data, refetch, isRefetching, isError } = useQuery<AccueilSociete | null>({
    queryKey: ['company', 'overview'],
    queryFn: async () => (await apiClient.get('/provider/company/overview')).data.data ?? null,
  });

  if (isError) {
    return (
      <Screen>
        <EmptyState
          title="Espace indisponible"
          message="Impossible de charger le résumé de votre société."
          actionLabel="Réessayer"
          onAction={() => void refetch()}
        />
      </Screen>
    );
  }

  const kpis = data?.kpis;

  return (
    <Screen>
      <ScrollView contentContainerStyle={styles.contenu} testID="company-overview">
        <Text style={styles.title}>{data?.organization.name ?? 'Ma société'}</Text>

        <View style={styles.grille}>
          <View style={styles.kpi}>
            <KPICard title="Aujourd’hui" value={kpis?.missions_today ?? 0} loading={!kpis} />
          </View>
          <View style={styles.kpi}>
            <KPICard title="En cours" value={kpis?.missions_active ?? 0} loading={!kpis} />
          </View>
          <View style={styles.kpi}>
            <KPICard
              title="En retard"
              value={kpis?.missions_delayed ?? 0}
              tone={kpis && kpis.missions_delayed > 0 ? 'danger' : 'neutral'}
              loading={!kpis}
            />
          </View>
          <View style={styles.kpi}>
            {/*
              Le chiffre le plus utile de cet écran : une mission du jour sans responsable est un
              client qui découvrira l'absence le matin même.
            */}
            <KPICard
              title="Sans personne"
              value={kpis?.missions_unassigned ?? 0}
              tone={kpis && kpis.missions_unassigned > 0 ? 'warning' : 'neutral'}
              loading={!kpis}
            />
          </View>
          <View style={styles.kpi}>
            <KPICard title="Équipe" value={kpis?.members_active ?? 0} loading={!kpis} />
          </View>
          <View style={styles.kpi}>
            <KPICard title="Tâches" value={kpis?.open_tasks ?? 0} loading={!kpis} />
          </View>
        </View>

        <View style={styles.action}>
          <Button
            label="Répartir les missions"
            fullWidth
            onPress={() => navigation.navigate('CompanyDispatch')}
          />
        </View>
        <View style={styles.action}>
          <Button
            label="Sites desservis"
            variant="secondary"
            fullWidth
            onPress={() => navigation.navigate('CompanySites')}
          />
        </View>
        <View style={styles.action}>
          <Button
            label="Équipe"
            variant="secondary"
            fullWidth
            onPress={() => navigation.navigate('CompanyMembers')}
          />
        </View>

        <View style={styles.action}>
          <Button
            label="Rafraîchir"
            variant="ghost"
            fullWidth
            disabled={isRefetching}
            onPress={() => void refetch()}
          />
        </View>
      </ScrollView>
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) =>
  StyleSheet.create({
    contenu: { paddingBottom: spacing.xl },
    title: {
      fontSize: typography.fontSize.xl,
      fontWeight: typography.fontWeight.bold,
      color: t.text,
      marginBottom: spacing.md,
    },
    grille: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
    kpi: { flexGrow: 1, flexBasis: '45%' },
    action: { marginTop: spacing.sm },
  });
