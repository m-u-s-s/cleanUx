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
import type { ReservationSociete } from './types';

interface Accueil {
  organization: { id: number; name: string };
  kpis: {
    sites_count: number;
    bookings_active: number;
    bookings_month: number;
    pending_approval: number;
    members_count: number;
  };
  recent_bookings: ReservationSociete[];
}

/**
 * L'ACCUEIL DE L'ESPACE SOCIÉTÉ CLIENTE.
 *
 * `config/parity.php` déclarait ce module — et les cinq autres — depuis longtemps, en `webview`.
 * L'application cliente n'en montrait aucun : ni écran, ni lien, et `ModuleHubScreen`, seule porte
 * générique vers les modules web, n'était monté dans aucun navigateur. Ce hub a été supprimé une
 * fois ces écrans natifs en place : le garder aurait laissé une seconde route vers les mêmes
 * modules, servie en WebView, que rien ne tenait à jour.
 *
 * Cet écran est aussi la table des matières des cinq autres : c'est par lui qu'ils sont atteints.
 * Un écran de plus sans chemin qui y mène aurait reproduit le défaut qu'on corrige.
 */
export function CompanyOverviewScreen() {
  const styles = stylesFor(useThemeColors());
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();

  const { data, refetch, isRefetching, isError } = useQuery<Accueil | null>({
    queryKey: ['client-company', 'overview'],
    queryFn: async () => (await apiClient.get('/client/company/overview')).data.data ?? null,
  });

  const kpis = data?.kpis;

  const RACCOURCIS: Array<{ label: string; screen: keyof RootStackParamList }> = [
    { label: 'Mes locaux', screen: 'CompanySites' },
    { label: 'Réservations', screen: 'CompanyBookings' },
    { label: 'Membres', screen: 'CompanyMembers' },
    { label: 'Contrats', screen: 'CompanyContracts' },
    { label: 'Facturation', screen: 'CompanyBilling' },
  ];

  if (isError) {
    return (
      <Screen>
        <EmptyState
          title="Espace indisponible"
          message="Impossible de charger votre espace entreprise. Réessayez dans un instant."
          actionLabel="Réessayer"
          onAction={() => void refetch()}
        />
      </Screen>
    );
  }

  return (
    <Screen>
      <ScrollView
        contentContainerStyle={styles.contenu}
        onScrollBeginDrag={undefined}
        refreshControl={undefined}
        testID="company-overview"
      >
        <Text style={styles.title}>{data?.organization.name ?? 'Espace entreprise'}</Text>

        <View style={styles.grilleKpis}>
          <View style={styles.kpi}>
            <KPICard title="Locaux" value={kpis?.sites_count ?? 0} loading={!kpis} />
          </View>
          <View style={styles.kpi}>
            <KPICard title="En cours" value={kpis?.bookings_active ?? 0} loading={!kpis} />
          </View>
          <View style={styles.kpi}>
            <KPICard title="Ce mois" value={kpis?.bookings_month ?? 0} loading={!kpis} />
          </View>
          <View style={styles.kpi}>
            <KPICard
              title="À approuver"
              value={kpis?.pending_approval ?? 0}
              tone={kpis && kpis.pending_approval > 0 ? 'warning' : 'neutral'}
              loading={!kpis}
            />
          </View>
          <View style={styles.kpi}>
            <KPICard title="Membres" value={kpis?.members_count ?? 0} loading={!kpis} />
          </View>
        </View>

        <Text style={styles.sousTitre}>Votre espace</Text>
        {RACCOURCIS.map(({ label, screen }) => (
          <View key={screen} style={styles.raccourci}>
            <Button
              label={label}
              variant="secondary"
              fullWidth
              onPress={() => navigation.navigate(screen as never)}
            />
          </View>
        ))}

        <Text style={styles.sousTitre}>Dernières réservations</Text>
        {(data?.recent_bookings ?? []).length === 0 ? (
          <Text style={styles.vide}>Aucune réservation pour le moment.</Text>
        ) : (
          (data?.recent_bookings ?? []).map((r) => (
            <View key={r.id} style={styles.ligne} testID={`reservation-${r.id}`}>
              <Text style={styles.nom} numberOfLines={1}>
                {r.site ?? 'Sans local'}
              </Text>
              <Text style={styles.detail} numberOfLines={1}>
                {r.status} · {r.provider ?? 'Prestataire à confirmer'}
              </Text>
            </View>
          ))
        )}

        <View style={styles.rafraichir}>
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
    contenu: {
      paddingBottom: spacing.xl,
    },
    title: {
      fontSize: typography.fontSize.xl,
      fontWeight: typography.fontWeight.bold,
      color: t.text,
      marginBottom: spacing.md,
    },
    sousTitre: {
      fontSize: typography.fontSize.base,
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
      marginTop: spacing.lg,
      marginBottom: spacing.sm,
    },
    grilleKpis: {
      flexDirection: 'row',
      flexWrap: 'wrap',
      gap: spacing.sm,
    },
    kpi: {
      flexGrow: 1,
      flexBasis: '45%',
    },
    raccourci: {
      marginBottom: spacing.sm,
    },
    ligne: {
      paddingVertical: spacing.sm,
      borderBottomWidth: StyleSheet.hairlineWidth,
      borderBottomColor: t.border,
    },
    nom: {
      fontSize: typography.fontSize.base,
      fontWeight: typography.fontWeight.semibold,
      color: t.text,
    },
    detail: {
      fontSize: typography.fontSize.sm,
      color: t.textMuted,
    },
    vide: {
      fontSize: typography.fontSize.sm,
      color: t.textMuted,
    },
    rafraichir: {
      marginTop: spacing.lg,
    },
  });
