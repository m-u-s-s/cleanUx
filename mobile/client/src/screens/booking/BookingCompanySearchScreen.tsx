import React, { useState } from 'react';
import { View, FlatList, Text, Pressable, StyleSheet } from 'react-native';
import { Screen, TextInput, Button, Avatar, Badge, Skeleton, EmptyState } from '@/ui';
import { useEligibleCompanies, useBooking } from '@/booking';
import type { EligibleCompany } from '@/booking';
import { colors, spacing, typography, radius } from '@/theme';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { BookingStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<BookingStackParamList, 'BookingCompanySearch'>;

/**
 * SP3 Task 9 (mobile) — premium client picks a provider COMPANY from the wizard.
 *
 * Mirror of BookingProviderSearchScreen (SP2): the screen lives inside the
 * BookingNavigator stack and shares the wizard's BookingProvider context. When
 * the client taps "Choisir" on a company, we dispatch SET_PREFERRED_COMPANY
 * (the `id` returned by /client/companies is the OrganizationAccount id) and
 * navigate back — the org id is then pinned in the wizard state, mutually
 * exclusive with any worker pick.
 *
 * Reuses useEligibleCompanies (GET /client/companies). The backend
 * (ProviderSelectionResolver) stays the authoritative premium + eligibility
 * gate; this screen is UX only.
 */
export function BookingCompanySearchScreen({ navigation, route }: Props) {
  const { dispatch } = useBooking();
  const serviceZoneId = route.params?.serviceZoneId ?? null;
  const serviceCatalogId = route.params?.serviceCatalogId;

  // The booking wizard does not yet carry a service zone id in state, so allow
  // a manual zone id as a fallback to keep the browse usable.
  const [manualZone, setManualZone] = useState('');
  const effectiveZoneId =
    serviceZoneId ?? (manualZone.trim() !== '' ? Number(manualZone.trim()) : null);

  const { companies, loading } = useEligibleCompanies(effectiveZoneId, serviceCatalogId);

  const choose = (organizationId: number) => {
    dispatch({ type: 'SET_PREFERRED_COMPANY', assignedProviderOrganizationId: organizationId });
    navigation.goBack();
  };

  const renderCard = ({ item }: { item: EligibleCompany }) => (
    <View style={styles.card}>
      <Avatar name={item.name} size={44} accessibilityLabel={item.name} />
      <View style={styles.cardContent}>
        <Text style={styles.companyName}>{item.name}</Text>
        <View style={styles.cardMeta}>
          <Text style={styles.rating}>
            {'⭐'} {(item.rating_avg ?? 0).toFixed(1)} ({item.rating_count})
          </Text>
        </View>
        <View style={styles.trades}>
          <Badge label={`${item.providers_count} prestataire${item.providers_count > 1 ? 's' : ''}`} variant="brand" />
        </View>
      </View>
      <Pressable
        testID={`choose-company-${item.id}`}
        accessibilityRole="button"
        onPress={() => choose(item.id)}
        style={styles.chooseBtn}
      >
        <Text style={styles.chooseLabel}>Choisir</Text>
      </Pressable>
    </View>
  );

  return (
    <Screen>
      <Text style={styles.title} accessibilityRole="header">
        Choisir une société
      </Text>
      <Text style={styles.subtitle}>
        Sélectionnez l'entreprise prestataire qui interviendra.
      </Text>

      {serviceZoneId == null ? (
        <View style={styles.filters}>
          <TextInput
            label="Zone de service"
            value={manualZone}
            onChangeText={setManualZone}
            placeholder="Identifiant de zone"
            keyboardType="numeric"
          />
        </View>
      ) : null}

      {loading ? (
        <View style={styles.skeletons}>
          {[1, 2, 3].map((i) => (
            <Skeleton key={i} width="100%" height={80} />
          ))}
        </View>
      ) : (
        <FlatList
          data={companies}
          keyExtractor={(item) => String(item.id)}
          renderItem={renderCard}
          contentContainerStyle={styles.list}
          accessibilityLabel="Liste des sociétés"
          ListEmptyComponent={
            <EmptyState
              title="Aucune société trouvée"
              message="Aucune entreprise prestataire éligible pour cette zone."
              icon="business-outline"
            />
          }
        />
      )}

      <View style={styles.footer}>
        <Button label="Annuler" variant="ghost" onPress={() => navigation.goBack()} fullWidth />
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
    marginBottom: spacing.xs,
  },
  subtitle: {
    fontSize: typography.fontSize.sm,
    color: colors.surface[500],
    marginBottom: spacing.md,
  },
  filters: { gap: spacing.sm, marginBottom: spacing.md },
  skeletons: { gap: spacing.sm },
  list: { gap: spacing.sm, paddingBottom: spacing.xl },
  card: {
    flexDirection: 'row',
    alignItems: 'center',
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.surface[200],
    backgroundColor: colors.surface[50],
    padding: spacing.md,
    gap: spacing.sm,
  },
  cardContent: { flex: 1 },
  companyName: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[900],
  },
  cardMeta: { flexDirection: 'row', gap: spacing.sm, marginTop: 2 },
  rating: { fontSize: typography.fontSize.xs, color: colors.surface[500] },
  trades: {
    flexDirection: 'row',
    gap: spacing.xs,
    marginTop: spacing.xs,
    flexWrap: 'wrap',
  },
  chooseBtn: {
    borderRadius: radius.sm,
    backgroundColor: colors.brand[500],
    paddingVertical: spacing.sm,
    paddingHorizontal: spacing.md,
  },
  chooseLabel: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[50],
  },
  footer: {
    marginTop: spacing.sm,
    marginBottom: spacing.lg,
  },
});
