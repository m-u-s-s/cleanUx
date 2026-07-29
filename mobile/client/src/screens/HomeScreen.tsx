import React, { useCallback, useRef } from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type GorhomBottomSheet from '@gorhom/bottom-sheet';
import { Screen, Button, Avatar, Badge, Skeleton, Icon } from '@/ui';
import { useAuth } from '@/auth';
import { useBookings } from '@/booking';
import { HomeActionsSheet } from '@/screens/components/HomeActionsSheet';
import { colors, spacing, typography, radius, shadows, useThemeColors } from '@/theme';
import type { RootStackParamList } from '@/navigation/types';

type Nav = NativeStackNavigationProp<RootStackParamList>;

/**
 * Accueil client, sur la structure du tableau de bord prestataire.
 *
 * Les deux écrans avaient le même en-tête et divergeaient ensuite complètement : le prestataire
 * consacre tout l'espace à un élément focal — sa carte — avec ses commandes en surimpression et
 * ses actions secondaires dans une feuille ; le client empilait indicateurs, pavés d'accès rapide
 * et réservations, si bien que ce qu'il vient réellement voir, sa mission en cours, arrivait tout
 * en bas de la page.
 *
 * Même structure adoptée ici, avec l'élément focal qui convient à un client : sa mission du
 * moment. Un prestataire a besoin de voir ce qui l'entoure, un client a besoin de voir OÙ EN EST
 * la sienne. Les accès rapides passent dans la feuille, les indicateurs avec eux.
 */
export function HomeScreen() {
  const { user } = useAuth();
  const { data: bookings, isLoading } = useBookings();
  const navigation = useNavigation<Nav>();
  const themeColors = useThemeColors();
  const sheetRef = useRef<GorhomBottomSheet>(null);

  const openSheet = useCallback(() => sheetRef.current?.expand(), []);

  const activeBookings = bookings?.filter(b => ['pending', 'confirmed', 'in_progress'].includes(b.status)) ?? [];
  const completedCount = bookings?.filter(b => b.status === 'completed').length ?? 0;
  const isFirstTime = !isLoading && activeBookings.length === 0 && completedCount === 0;

  /**
   * Une mission démarrée est suivie en direct : c'est elle qui devient l'élément focal, comme la
   * carte l'est pour le prestataire. À défaut, la prochaine réservation prend sa place.
   */
  const liveBooking = activeBookings.find(b => b.status === 'in_progress');
  const focus = liveBooking ?? activeBookings[0];

  return (
    <Screen testID="home-screen">
      <View style={styles.hero}>
        <View style={styles.heroLeft}>
          <Text style={[styles.greeting, { color: themeColors.text }]}>
            Bonjour{user?.name ? `, ${user.name.split(' ')[0]}` : ''}
          </Text>
          <Text style={[styles.role, { color: themeColors.textMuted }]}>{user?.email}</Text>
        </View>
        <Avatar name={user?.name ?? '?'} size={48} accessibilityLabel={user?.name ?? 'Profil'} />
      </View>

      <View style={styles.focusWrap}>
        {isLoading ? (
          <Skeleton width="100%" height={180} />
        ) : isFirstTime ? (
          <View style={[styles.welcomeCard, { backgroundColor: themeColors.card }]} testID="home-welcome">
            <Icon name="home-outline" size={48} color={colors.brand[400]} />
            <Text style={[styles.welcomeTitle, { color: themeColors.text }]}>Bienvenue sur brio</Text>
            <Text style={[styles.welcomeText, { color: themeColors.textSecondary }]}>
              Réservez votre premier service et découvrez une nouvelle façon de gérer votre maison.
            </Text>
          </View>
        ) : focus ? (
          <TouchableOpacity
            style={[styles.focusCard, { backgroundColor: themeColors.card }]}
            onPress={() =>
              liveBooking
                ? navigation.navigate('MissionTracking', { bookingId: focus.id })
                : navigation.navigate('BookingDetail', { bookingId: focus.id })
            }
            accessibilityRole="button"
            accessibilityLabel={`${focus.service_name} — ${liveBooking ? 'suivre en direct' : 'voir le détail'}`}
            testID="home-focus-booking"
          >
            <View style={styles.focusHeader}>
              <Text style={[styles.focusService, { color: themeColors.text }]}>{focus.service_name}</Text>
              <Badge label={focus.status} variant={liveBooking ? 'success' : 'brand'} />
            </View>
            <Text style={[styles.focusDate, { color: themeColors.textSecondary }]}>
              {focus.scheduled_date} à {focus.scheduled_time}
            </Text>
            <Text style={[styles.focusAddress, { color: themeColors.textMuted }]}>
              {focus.address}, {focus.city}
            </Text>

            {/* Le suivi en direct est la seule chose qui compte pendant une mission : on le dit
                explicitement plutôt que de compter sur l'utilisateur pour tenter le tap. */}
            <View style={styles.focusCta}>
              <Icon
                name={liveBooking ? 'navigate-outline' : 'chevron-forward'}
                size={18}
                color={colors.brand[600]}
              />
              <Text style={styles.focusCtaText}>
                {liveBooking ? 'Suivre en direct' : 'Voir le détail'}
              </Text>
            </View>
          </TouchableOpacity>
        ) : (
          <View style={[styles.welcomeCard, { backgroundColor: themeColors.card }]} testID="home-no-active">
            <Icon name="calendar-outline" size={40} color={colors.brand[400]} />
            <Text style={[styles.welcomeText, { color: themeColors.textSecondary }]}>
              Aucune réservation en cours.
            </Text>
          </View>
        )}

        {activeBookings.length > 1 ? (
          <Text style={[styles.moreLabel, { color: themeColors.textMuted }]}>
            {activeBookings.length - 1} autre{activeBookings.length > 2 ? 's' : ''} réservation
            {activeBookings.length > 2 ? 's' : ''} en cours
          </Text>
        ) : null}
      </View>

      {/* Commandes en surimpression, comme la pastille de présence et le bouton d'actions du
          tableau de bord prestataire : l'action principale reste atteignable au pouce, quel que
          soit le contenu affiché au-dessus. */}
      <View style={styles.floating} pointerEvents="box-none">
        <Button
          label="Réserver un service"
          onPress={() => navigation.navigate('BookingWizard')}
          fullWidth
          size="lg"
        />
        <Button label="Actions" onPress={openSheet} variant="secondary" fullWidth />
      </View>

      <HomeActionsSheet ref={sheetRef} />
    </Screen>
  );
}

const styles = StyleSheet.create({
  hero: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: spacing.md,
    marginBottom: spacing.lg,
  },
  heroLeft: { flex: 1 },
  greeting: { fontSize: typography.fontSize['2xl'], fontWeight: typography.fontWeight.bold },
  role: { fontSize: typography.fontSize.sm, marginTop: 2 },
  focusWrap: { flex: 1, gap: spacing.sm },
  focusCard: {
    borderRadius: radius.md,
    padding: spacing.lg,
    gap: spacing.xs,
    ...shadows.soft,
  },
  focusHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  focusService: { fontSize: typography.fontSize.lg, fontWeight: typography.fontWeight.semibold, flex: 1 },
  focusDate: { fontSize: typography.fontSize.sm, marginTop: spacing.xs },
  focusAddress: { fontSize: typography.fontSize.xs },
  focusCta: { flexDirection: 'row', alignItems: 'center', gap: spacing.xs, marginTop: spacing.sm },
  focusCtaText: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: colors.brand[600],
  },
  moreLabel: { fontSize: typography.fontSize.xs, textAlign: 'center' },
  welcomeCard: {
    borderRadius: radius.md,
    padding: spacing.lg,
    ...shadows.soft,
    alignItems: 'center',
    gap: spacing.sm,
  },
  welcomeTitle: { fontSize: typography.fontSize.xl, fontWeight: typography.fontWeight.bold, textAlign: 'center' },
  welcomeText: { fontSize: typography.fontSize.sm, textAlign: 'center', lineHeight: 20 },
  floating: { position: 'absolute', left: spacing.md, right: spacing.md, bottom: spacing.lg, gap: spacing.sm },
});
