import React from 'react';
/* Chemin direct plutot que le baril : des suites mockent les barils a la main. */
import { formatMontant } from '@/format/money';
import { View, FlatList, Text, StyleSheet } from 'react-native';
import { useQuery } from '@tanstack/react-query';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import { Screen, Button, Badge, EmptyState } from '@/ui';
import { apiClient } from '@/api';
import { spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';
import type { ReservationSociete } from './types';
import { useTraduction } from '@/i18n';

/** Les statuts proposés au filtre, dans l'ordre où un gestionnaire les consulte. */
// Hors composant : la constante porte la CLE, l'ecran traduit au rendu.
const FILTRES: Array<{ libelleCle: string; valeur: string | null }> = [
  { libelleCle: 'company_bookings.toutes', valeur: null },
  { libelleCle: 'company_overview.a_approuver', valeur: 'pending_approval' },
  { libelleCle: 'company_bookings.confirmees', valeur: 'confirmed' },
  { libelleCle: 'company_overview.en_cours', valeur: 'in_progress' },
  { libelleCle: 'company_bookings.terminees', valeur: 'completed' },
];

const TON_PAR_STATUT: Record<string, 'success' | 'warning' | 'neutral'> = {
  confirmed: 'success',
  in_progress: 'success',
  pending_approval: 'warning',
  pending: 'warning',
};

/**
 * LES RÉSERVATIONS DE LA SOCIÉTÉ, TOUS LOCAUX CONFONDUS.
 *
 * L'onglet Réservations de l'application montre celles du COMPTE. Un responsable multi-sites ne s'y
 * retrouve pas : ce qu'il suit, ce sont les interventions de son organisation, quel que soit le
 * membre qui les a demandées. C'est une liste différente, servie par une requête différente
 * (`customer_organization_id`), pas une variante d'affichage.
 */
export function CompanyBookingsScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();

  const [statut, setStatut] = React.useState<string | null>(null);

  const { data: reservations, refetch, isRefetching, isError } = useQuery<ReservationSociete[]>({
    queryKey: ['client-company', 'bookings', statut],
    queryFn: async () =>
      (await apiClient.get('/client/company/bookings', { params: statut ? { status: statut } : {} }))
        .data.data ?? [],
  });

  if (isError) {
    return (
      <Screen>
        <EmptyState
          title={tr('company_bookings.reservations_indisponibles')}
          message="Impossible de charger les réservations de votre société."
          actionLabel="Réessayer"
          onAction={() => void refetch()}
        />
      </Screen>
    );
  }

  return (
    <Screen>
      <Text style={styles.title}>{tr('company_bookings.reservations')}</Text>

      <View style={styles.filtres}>
        {FILTRES.map(({ libelleCle, valeur }) => (
          <Button
            key={libelleCle}
            label={tr(libelleCle)}
            size="sm"
            variant={statut === valeur ? 'primary' : 'ghost'}
            onPress={() => setStatut(valeur)}
          />
        ))}
      </View>

      <FlatList
        data={reservations ?? []}
        keyExtractor={(r) => String(r.id)}
        onRefresh={refetch}
        refreshing={isRefetching}
        renderItem={({ item }) => (
          <View style={styles.ligne} testID={`reservation-${item.id}`}>
            <View style={styles.identite}>
              <Text style={styles.nom} numberOfLines={1}>
                {item.site ?? 'Sans local'}
              </Text>
              <Text style={styles.detail} numberOfLines={1}>
                {item.provider ?? 'Prestataire à confirmer'}
                {item.estimated_price !== null ? ` · ${formatMontant(item.estimated_price)}` : ''}
              </Text>
            </View>

            <Badge label={item.status} variant={TON_PAR_STATUT[item.status] ?? 'neutral'} />

            {/*
              Le détail est l'écran EXISTANT de l'application. En ouvrir un second, propre à la
              société, dupliquerait le suivi, les photos et le litige — et les deux dériveraient.
            */}
            <Button
              label={tr('company_bookings.ouvrir')}
              size="sm"
              variant="ghost"
              onPress={() => navigation.navigate('BookingDetail', { bookingId: item.id })}
            />
          </View>
        )}
        ListEmptyComponent={
          <EmptyState
            title={tr('company_bookings.aucune_reservation')}
            message="Les interventions demandées par les membres de votre société apparaîtront ici."
          />
        }
      />
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) =>
  StyleSheet.create({
    title: {
      fontSize: typography.fontSize.xl,
      fontWeight: typography.fontWeight.bold,
      color: t.text,
      marginBottom: spacing.md,
    },
    filtres: {
      flexDirection: 'row',
      flexWrap: 'wrap',
      gap: spacing.xs,
      marginBottom: spacing.md,
    },
    ligne: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: spacing.sm,
      paddingVertical: spacing.sm,
      borderBottomWidth: StyleSheet.hairlineWidth,
      borderBottomColor: t.border,
    },
    identite: {
      flex: 1,
      minWidth: 0,
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
  });
