import React from 'react';
import type { ReactNode } from 'react';
import { View, Text, Pressable, StyleSheet } from 'react-native';
import type { AccessibilityRole, AccessibilityState } from 'react-native';
import { Screen, Button, ProgressBar } from '@/ui';
import { useAuth } from '@/auth';
import { useBooking, useBookingFavorites } from '@/booking';
import type { ProviderTypePreference } from '@/booking';
import { colors, spacing, typography, radius } from '@/theme';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { BookingStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<BookingStackParamList, 'BookingStepProvider'>;

const TYPE_OPTIONS: Array<{ value: ProviderTypePreference; title: string; subtitle: string }> = [
  { value: 'any', title: 'Peu importe', subtitle: 'Le meilleur prestataire disponible' },
  { value: 'independent', title: 'Indépendant', subtitle: 'Un prestataire particulier' },
  { value: 'company', title: 'Société', subtitle: 'Une entreprise prestataire' },
];

type SelectableCardProps = {
  selected: boolean;
  onPress: () => void;
  children: ReactNode;
  testID?: string;
  accessibilityRole?: AccessibilityRole;
  accessibilityState?: AccessibilityState;
  /** Lay out children in a row (used by the favourite cards). */
  row?: boolean;
};

/**
 * A tappable card with a unified resting/selected look (selected → brand tokens).
 * Local to this screen: it backs both the provider-type selector and the
 * favourite re-book rows so they share the exact same visual language.
 */
function SelectableCard({
  selected,
  onPress,
  children,
  testID,
  accessibilityRole,
  accessibilityState,
  row,
}: SelectableCardProps) {
  return (
    <Pressable
      testID={testID}
      accessibilityRole={accessibilityRole}
      accessibilityState={accessibilityState}
      onPress={onPress}
      style={[
        styles.selectableCard,
        row && styles.selectableCardRow,
        selected && styles.selectableCardSelected,
      ]}
    >
      {children}
    </Pressable>
  );
}

/**
 * SP2 — Booking wizard provider-selection step (mobile parity with web Task 6).
 *
 * Three tiers:
 *  1. Provider TYPE selector (Indépendant / Société / Peu importe — default 'any').
 *  2. Re-book a FAVOURITE (sets preferredProviderUserId).
 *  3. If premium: "Choisir un prestataire" → browse; else an upsell encart.
 *
 * The authoritative premium gate is the backend (customerProfile->isPremium()).
 * The mobile reads user.is_premium optimistically when present and otherwise
 * shows the upsell; the backend stays free to refuse a non-favourite pick.
 */
export function BookingStepProvider({ navigation }: Props) {
  const { state, dispatch } = useBooking();
  const { user } = useAuth();
  const favoritesQuery = useBookingFavorites();
  const favorites = favoritesQuery.data ?? [];
  const isPremium = user?.is_premium === true;

  const selectType = (value: ProviderTypePreference) => {
    dispatch({ type: 'SET_PROVIDER_TYPE', providerTypePreference: value });
  };

  const rebookFavorite = (providerUserId: number) => {
    dispatch({ type: 'SET_PREFERRED_PROVIDER', preferredProviderUserId: providerUserId });
  };

  const clearPreferred = () => {
    dispatch({ type: 'SET_PREFERRED_PROVIDER', preferredProviderUserId: null });
  };

  const clearCompany = () => {
    dispatch({ type: 'SET_PREFERRED_COMPANY', assignedProviderOrganizationId: null });
  };

  const openCompanyBrowse = () => {
    // SP3 Task 9 — open the in-stack company search in SELECTION mode. It shares
    // this wizard's BookingProvider context, so tapping "Choisir" there dispatches
    // SET_PREFERRED_COMPANY and goes back: the org id lands in state
    // (assignedProviderOrganizationId), mutually exclusive with the worker pick.
    navigation.navigate('BookingCompanySearch', {
      postalCode: state.coordinates?.postalCode || undefined,
      serviceCatalogId: state.serviceId ?? undefined,
    });
  };

  const openProviderBrowse = () => {
    // SP2 palier 3 — open the in-stack provider search in SELECTION mode.
    // It shares this wizard's BookingProvider context, so tapping "Choisir"
    // there dispatches SET_PREFERRED_PROVIDER and goes back: the pick lands
    // straight in state.preferredProviderUserId (pinned card below).
    navigation.navigate('BookingProviderSearch');
  };

  return (
    <Screen scroll>
      <ProgressBar step={5} totalSteps={6} />
      <Text style={styles.title}>Prestataire</Text>
      <Text style={styles.subtitle}>
        Choisissez qui interviendra. « Peu importe » laisse notre système trouver le meilleur
        prestataire disponible.
      </Text>

      {/* Tier 1 — type selector */}
      <Text style={styles.sectionLabel}>Type de prestataire</Text>
      <View style={styles.typeGroup}>
        {TYPE_OPTIONS.map((opt) => {
          const selected = state.providerTypePreference === opt.value;
          return (
            <SelectableCard
              key={opt.value}
              testID={`provider-type-${opt.value}`}
              accessibilityRole="radio"
              accessibilityState={{ selected }}
              selected={selected}
              onPress={() => selectType(opt.value)}
            >
              <Text style={styles.typeTitle}>{opt.title}</Text>
              <Text style={styles.typeSubtitle}>{opt.subtitle}</Text>
            </SelectableCard>
          );
        })}
      </View>

      {/* Currently pinned provider */}
      {state.preferredProviderUserId ? (
        <View style={styles.pinnedCard}>
          <Text style={styles.pinnedText}>
            Prestataire choisi (#{state.preferredProviderUserId})
          </Text>
          <Pressable testID="clear-preferred-provider" onPress={clearPreferred}>
            <Text style={styles.pinnedClear}>Retirer</Text>
          </Pressable>
        </View>
      ) : null}

      {/* Currently pinned company */}
      {state.assignedProviderOrganizationId ? (
        <View style={styles.pinnedCard}>
          <Text style={styles.pinnedText}>
            Société choisie (#{state.assignedProviderOrganizationId})
          </Text>
          <Pressable testID="clear-preferred-company" onPress={clearCompany}>
            <Text style={styles.pinnedClear}>Retirer</Text>
          </Pressable>
        </View>
      ) : null}

      {/* Tier 2 — favourites */}
      {favorites.length > 0 ? (
        <View style={styles.section}>
          <Text style={styles.sectionLabel}>Mes prestataires favoris</Text>
          {favorites.map((fav) => {
            const providerId = fav.preferred_provider?.id ?? null;
            if (providerId == null) return null;
            const selected = state.preferredProviderUserId === providerId;
            return (
              <SelectableCard
                key={fav.id}
                testID={`favorite-${fav.id}`}
                selected={selected}
                onPress={() => rebookFavorite(providerId)}
                row
              >
                <Text style={styles.favoriteName}>
                  {fav.label || fav.preferred_provider?.name || `Prestataire #${providerId}`}
                </Text>
                <Text style={styles.favoriteAction}>Re-réserver</Text>
              </SelectableCard>
            );
          })}
        </View>
      ) : null}

      {/* Tier 3 — premium pick OR upsell */}
      {isPremium ? (
        <View style={styles.section}>
          <Button
            label="Choisir un prestataire"
            variant="secondary"
            onPress={openProviderBrowse}
            fullWidth
          />
          {state.providerTypePreference === 'company' ? (
            <View style={styles.companyPickSpacer}>
              <Button
                label="Choisir une société"
                variant="secondary"
                onPress={openCompanyBrowse}
                fullWidth
              />
            </View>
          ) : null}
        </View>
      ) : (
        <View testID="premium-upsell" style={styles.upsellCard}>
          <Text style={styles.upsellText}>
            <Text style={styles.upsellStrong}>Premium : </Text>
            choisissez un prestataire spécifique parmi tout le catalogue. Réservé au pack
            Premium (vos favoris restent disponibles pour tous).
          </Text>
        </View>
      )}

      <View style={styles.footer}>
        <Button
          label="Continuer"
          onPress={() => navigation.navigate('BookingStep5')}
          fullWidth
          size="lg"
        />
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  title: {
    fontSize: typography.fontSize['2xl'],
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
    marginTop: spacing.md,
    marginBottom: spacing.xs,
  },
  subtitle: {
    fontSize: typography.fontSize.sm,
    color: colors.surface[500],
    marginBottom: spacing.lg,
  },
  sectionLabel: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[700],
    marginBottom: spacing.sm,
  },
  section: {
    marginTop: spacing.lg,
  },
  companyPickSpacer: {
    marginTop: spacing.sm,
  },
  typeGroup: {
    gap: spacing.sm,
  },
  selectableCard: {
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.surface[200],
    backgroundColor: colors.surface[50],
    paddingVertical: spacing.md,
    paddingHorizontal: spacing.md,
  },
  selectableCardRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: spacing.sm,
  },
  selectableCardSelected: {
    borderColor: colors.brand[500],
    backgroundColor: colors.brand[50],
  },
  typeTitle: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.bold,
    color: colors.surface[900],
  },
  typeSubtitle: {
    fontSize: typography.fontSize.xs,
    color: colors.surface[500],
    marginTop: 2,
  },
  pinnedCard: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.success[500],
    backgroundColor: colors.success[50],
    paddingVertical: spacing.sm,
    paddingHorizontal: spacing.md,
    marginTop: spacing.md,
  },
  pinnedText: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: colors.success[700],
  },
  pinnedClear: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: colors.success[700],
    textDecorationLine: 'underline',
  },
  favoriteName: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[900],
    flexShrink: 1,
  },
  favoriteAction: {
    fontSize: typography.fontSize.xs,
    fontWeight: typography.fontWeight.semibold,
    color: colors.brand[600],
    marginLeft: spacing.sm,
  },
  upsellCard: {
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.warning[500],
    backgroundColor: colors.warning[50],
    padding: spacing.md,
    marginTop: spacing.lg,
  },
  upsellStrong: {
    fontWeight: typography.fontWeight.bold,
    color: colors.warning[700],
  },
  upsellText: {
    fontSize: typography.fontSize.sm,
    color: colors.warning[700],
  },
  footer: {
    marginTop: spacing.xl,
    marginBottom: spacing.lg,
  },
});
