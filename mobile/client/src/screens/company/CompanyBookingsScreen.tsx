import React from 'react';
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

/** Les statuts proposés au filtre, dans l'ordre où un gestionnaire les consulte. */
const FILTRES: Array<{ label: string; valeur: string | null }> = [
  { label: 'Toutes', valeur: null },
  { label: 'À approuver', valeur: 'pending_approval' },
  { label: 'Confirmées', valeur: 'confirmed' },
  { label: 'En cours', valeur: 'in_progress' },
  { label: 'Terminées', valeur: 'completed' },
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
          title="Réservations indisponibles"
          message="Impossible de charger les réservations de votre société."
          actionLabel="Réessayer"
          onAction={() => void refetch()}
        />
      </Screen>
    );
  }

  return (
    <Screen>
      <Text style={styles.title}>Réservations</Text>

      <View style={styles.filtres}>
        {FILTRES.map(({ label, valeur }) => (
          <Button
            key={label}
            label={label}
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
                {item.estimated_price !== null ? ` · ${item.estimated_price.toFixed(2)} €` : ''}
              </Text>
            </View>

            <Badge label={item.status} variant={TON_PAR_STATUT[item.status] ?? 'neutral'} />

            {/*
              Le détail est l'écran EXISTANT de l'application. En ouvrir un second, propre à la
              société, dupliquerait le suivi, les photos et le litige — et les deux dériveraient.
            */}
            <Button
              label="Ouvrir"
              size="sm"
              variant="ghost"
              onPress={() => navigation.navigate('BookingDetail', { bookingId: item.id })}
            />
          </View>
        )}
        ListEmptyComponent={
          <EmptyState
            title="Aucune réservation"
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
