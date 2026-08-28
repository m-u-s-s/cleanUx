import React, { useCallback, useRef } from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type GorhomBottomSheet from '@gorhom/bottom-sheet';
import { Screen, Button, Avatar, Badge, Skeleton, Icon } from '@/ui';
import { useAuth } from '@/auth';
import { useBookings } from '@/booking';
import { useLiveBookingIds } from '@/tracking';
import { HomeActionsSheet } from '@/screens/components/HomeActionsSheet';
import { HomeMissionMap } from '@/screens/components/HomeMissionMap';
import { colors, spacing, typography, radius, shadows, useThemeColors } from '@/theme';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { formatAdresse, formatDateHeure, libelleStatut } from '@/lib/format';
import type { RootStackParamList } from '@/navigation/types';
import { useTraduction } from '@/i18n';

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
 * la sienne. La feuille porte l'entrée de réservation — immédiate, sur rendez-vous, ou multi-services —
 * ainsi que les accès rapides et les indicateurs.
 */
export function HomeScreen() {
  const { t: tr } = useTraduction();
  const { user } = useAuth();
  const { data: bookings, isLoading } = useBookings();
  const navigation = useNavigation<Nav>();
  const themeColors = useThemeColors();
  const styles = stylesFor(themeColors);
  const sheetRef = useRef<GorhomBottomSheet>(null);

  const openSheet = useCallback(() => sheetRef.current?.expand(), []);

  // `state` est l'état normalisé par le serveur ; `status` reste la valeur brute du domaine, en
  // français, que filtrer directement laissait passer à côté de la moitié des réservations.
  const stateOf = (b: { state?: string; status: string }) => b.state ?? b.status;

  const activeBookings = bookings?.filter(b => ['pending', 'confirmed', 'in_progress'].includes(stateOf(b))) ?? [];
  const completedCount = bookings?.filter(b => stateOf(b) === 'completed').length ?? 0;
  const isFirstTime = !isLoading && activeBookings.length === 0 && completedCount === 0;

  /**
   * VIVANT SE DÉCIDE SUR LE TERRAIN, PAS SUR LE STATUT.
   *
   * `in_progress` n'arrive qu'une fois l'intervention DÉMARRÉE. Pendant tout le trajet du
   * prestataire — précisément le moment où le client regarde son téléphone — la réservation reste
   * `confirmed` : l'accueil n'affichait donc ni carte ni suivi, et l'écran qui porte le code de
   * présence restait injoignable. La session de suivi, elle, naît quand le prestataire prend la
   * route.
   */
  const liveIds = useLiveBookingIds(activeBookings.map(b => b.id));
  const isLive = (b: { id: number; state?: string; status: string }) =>
    stateOf(b) === 'in_progress' || liveIds.has(b.id);

  // La mission vivante devient l'élément focal, comme la carte l'est pour le prestataire. À
  // défaut, la prochaine réservation prend sa place.
  const liveBooking = activeBookings.find(isLive);
  const focus = liveBooking ?? activeBookings[0];
  const focusIsLive = focus ? isLive(focus) : false;
  const autresBookings = activeBookings.filter(b => b.id !== focus?.id);

  /** Date et ville, assemblées : le tiret n'apparaît que s'il sépare deux morceaux réels. */
  const ligneMeta = (b: { scheduled_date?: string; scheduled_time?: string; city?: string }) =>
    [formatDateHeure(b.scheduled_date, b.scheduled_time), b.city].filter(Boolean).join(' — ');

  return (
    <Screen testID="home-screen">
      <View style={styles.hero}>
        <View style={styles.heroLeft}>
          <Text style={styles.greeting}>
            Bonjour{user?.name ? `, ${user.name.split(' ')[0]}` : ''}
          </Text>
          <Text style={styles.role}>{user?.email}</Text>
        </View>
        <Avatar name={user?.name ?? '?'} size={48} accessibilityLabel={user?.name ?? 'Profil'} />
      </View>

      <View style={styles.focusWrap}>
        {isLoading ? (
          <Skeleton width="100%" height={180} />
        ) : isFirstTime ? (
          <View style={styles.welcomeCard} testID="home-welcome">
            <Icon name="home-outline" size={48} color={colors.brand[400]} />
            <Text style={styles.welcomeTitle}>{tr('home.bienvenue_sur_brio')}</Text>
            <Text style={styles.welcomeText}>
              Réservez votre premier service et découvrez une nouvelle façon de gérer votre maison.
            </Text>
          </View>
        ) : focus ? (
          <TouchableOpacity
            style={styles.focusCard}
            onPress={() =>
              focusIsLive
                ? navigation.navigate('MissionTracking', { bookingId: focus.id })
                : navigation.navigate('BookingDetail', { bookingId: focus.id })
            }
            accessibilityRole="button"
            accessibilityLabel={`${focus.service_name} — ${focusIsLive ? 'suivre en direct' : 'voir le détail'}`}
            testID="home-focus-booking"
          >
            <View style={styles.focusHeader}>
              <Text style={styles.focusService}>{focus.service_name}</Text>
              {/* Le statut technique de l'API ne s'affiche pas tel quel : « pending » n'est pas
                  une promesse qu'on fait à un client. */}
              <Badge label={libelleStatut(stateOf(focus))} variant={focusIsLive ? 'success' : 'brand'} />
            </View>
            {/* Pas de date connue, pas de ligne : `formatDateHeure` rend une chaîne vide quand
                la réservation n'a pas encore d'horaire, et la carte affichait alors un blanc
                entre le titre et l'adresse — un trou qui ressemble à un défaut d'affichage. */}
            {formatDateHeure(focus.scheduled_date, focus.scheduled_time) ? (
              <Text style={styles.focusDate}>
                {formatDateHeure(focus.scheduled_date, focus.scheduled_time)}
              </Text>
            ) : null}
            <Text style={styles.focusAddress}>
              {formatAdresse(focus.address, focus.city)}
            </Text>

            {/* La carte apparaît dès que le prestataire a pris la route : c'est là qu'il y a
                quelque chose à situer, et c'est là qu'un client la regarde. L'attendre au démarrage
                de l'intervention la faisait apparaître au moment où elle n'apprend plus rien. */}
            {focusIsLive ? <HomeMissionMap bookingId={focus.id} /> : null}

            {/* Le suivi en direct est la seule chose qui compte pendant une mission : on le dit
                explicitement plutôt que de compter sur l'utilisateur pour tenter le tap. */}
            <View style={styles.focusCta}>
              <Icon
                name={focusIsLive ? 'navigate-outline' : 'chevron-forward'}
                size={18}
                color={colors.brand[600]}
              />
              <Text style={styles.focusCtaText}>
                {focusIsLive ? 'Suivre en direct' : 'Voir le détail'}
              </Text>
            </View>
          </TouchableOpacity>
        ) : (
          <View style={styles.welcomeCard} testID="home-no-active">
            <Icon name="calendar-outline" size={40} color={colors.brand[400]} />
            <Text style={styles.welcomeText}>
              {tr('home.aucune_reservation_en_cours')}
            </Text>
          </View>
        )}

        {/*
            TOUTES LES MISSIONS EN COURS, PAS UN DÉCOMPTE.

            L'accueil n'affichait qu'une carte focale et une ligne « 2 autres réservations en
            cours » — un chiffre sur lequel on ne peut pas appuyer. Un client qui a deux
            interventions le même jour ne pouvait atteindre la seconde que par un autre onglet.
        */}
        {autresBookings.length > 0 ? (
          <View style={styles.othersWrap} testID="home-other-bookings">
            <Text style={styles.moreLabel}>
              {autresBookings.length} autre{autresBookings.length > 1 ? 's' : ''} en cours
            </Text>

            {autresBookings.map(b => (
              <TouchableOpacity
                key={b.id}
                style={styles.otherCard}
                onPress={() =>
                  isLive(b)
                    ? navigation.navigate('MissionTracking', { bookingId: b.id })
                    : navigation.navigate('BookingDetail', { bookingId: b.id })
                }
                accessibilityRole="button"
                accessibilityLabel={`${b.service_name} — ${isLive(b) ? 'suivre en direct' : 'voir le détail'}`}
                testID={`home-other-booking-${b.id}`}
              >
                <View style={styles.otherText}>
                  <Text style={styles.otherService} numberOfLines={1}>
                    {b.service_name}
                  </Text>
                  {/* LE SÉPARATEUR NE S'AFFICHE QUE S'IL SÉPARE QUELQUE CHOSE.

                      Écrit « {date} — {ville} » en dur, il restait seul quand ni l'une ni
                      l'autre n'était connue : la carte se réduisait à un tiret au milieu du
                      vide. On assemble ce qui existe, et on ne rend rien s'il n'y a rien. */}
                  {ligneMeta(b) ? (
                    <Text style={styles.otherMeta} numberOfLines={1}>
                      {ligneMeta(b)}
                    </Text>
                  ) : null}
                </View>
                <Icon
                  name={isLive(b) ? 'navigate-outline' : 'chevron-forward'}
                  size={18}
                  color={colors.brand[600]}
                />
              </TouchableOpacity>
            ))}
          </View>
        ) : null}
      </View>

      {/* Commandes en surimpression, comme la pastille de présence et le bouton d'actions du
          tableau de bord prestataire : l'action principale reste atteignable au pouce, quel que
          soit le contenu affiché au-dessus. */}
      <View style={styles.floating} pointerEvents="box-none">
        {/* Un seul bouton : deux appels côte à côte obligeaient à choisir avant d'avoir vu les
            options. Il ouvre la feuille, qui pose la vraie question — quel type de mission. */}
        <Button label={tr('home.reserver_un_service')} onPress={openSheet} fullWidth size="lg" />
      </View>

      <HomeActionsSheet ref={sheetRef} />
    </Screen>
  );
}

/*
 * LES COULEURS VIVENT DANS LES STYLES, PLUS A COTE. Quinze rattrapages en ligne posaient
 * ici ce que la fonction de styles savait deja faire, et `focusCtaText` gardait un indigo
 * fige — 4,47 sur le blanc, 3,13 sur la nuit.
 */
const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  hero: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: spacing.md,
    marginBottom: spacing.lg,
  },
  heroLeft: { flex: 1 },
  greeting: { color: t.text, fontSize: typography.fontSize['2xl'], fontWeight: typography.fontWeight.bold },
  role: { color: t.textMuted, fontSize: typography.fontSize.sm, marginTop: 2 },
  focusWrap: { flex: 1, gap: spacing.sm },
  focusCard: { backgroundColor: t.card,
    borderRadius: radius.md,
    padding: spacing.lg,
    gap: spacing.xs,
    ...shadows.soft,
  },
  focusHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  focusService: { color: t.text, fontSize: typography.fontSize.lg, fontWeight: typography.fontWeight.semibold, flex: 1 },
  focusDate: { color: t.textSecondary, fontSize: typography.fontSize.sm, marginTop: spacing.xs },
  focusAddress: { color: t.textMuted, fontSize: typography.fontSize.xs },
  focusCta: { flexDirection: 'row', alignItems: 'center', gap: spacing.xs, marginTop: spacing.sm },
  focusCtaText: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: t.brandText,
  },
  moreLabel: { color: t.textMuted, fontSize: typography.fontSize.xs, textAlign: 'center' },
  othersWrap: { gap: spacing.xs },
  otherCard: { backgroundColor: t.card,
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    ...shadows.soft,
  },
  otherText: { flex: 1 },
  otherService: { color: t.text, fontSize: typography.fontSize.sm, fontWeight: typography.fontWeight.semibold },
  otherMeta: { color: t.textMuted, fontSize: typography.fontSize.xs, marginTop: 2 },
  welcomeCard: { backgroundColor: t.card,
    borderRadius: radius.md,
    padding: spacing.lg,
    ...shadows.soft,
    alignItems: 'center',
    gap: spacing.sm,
  },
  welcomeTitle: { color: t.text, fontSize: typography.fontSize.xl, fontWeight: typography.fontWeight.bold, textAlign: 'center' },
  welcomeText: { color: t.textSecondary, fontSize: typography.fontSize.sm, textAlign: 'center', lineHeight: 20 },
  floating: { position: 'absolute', left: spacing.md, right: spacing.md, bottom: spacing.lg, gap: spacing.sm },
});
