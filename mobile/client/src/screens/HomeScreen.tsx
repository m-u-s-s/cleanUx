import React, { useCallback, useRef } from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type GorhomBottomSheet from '@gorhom/bottom-sheet';
import { Screen, Button, Avatar, Badge, Skeleton, Icon, CarteDeMission, GlassSurface, AnimatedListItem } from '@/ui';
import { useAuth } from '@/auth';
import { useBookings } from '@/booking';
import { useLiveBookingIds } from '@/tracking';
import { HomeActionsSheet } from '@/screens/components/HomeActionsSheet';
import { HomeMissionMap } from '@/screens/components/HomeMissionMap';
import { colors, spacing, typography, radius, useThemeColors } from '@/theme';
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
            {user?.name
              ? tr('commun.bonjour_prenom', { prenom: user.name.split(' ')[0] ?? '' })
              : tr('commun.bonjour')}
          </Text>
          {/*
              L'ADRESSE E-MAIL N'APPRENAIT RIEN À PERSONNE.

              Elle occupait la ligne la plus lue de l'application pour redire ce que l'onglet
              Profil dit déjà. À sa place, la seule chose qu'un client vient vérifier en ouvrant
              l'accueil : combien d'interventions sont en cours. Rien pendant le chargement et
              rien quand il n'y en a aucune — la carte en dessous le dit alors elle-même, et
              deux fois la même phrase se lit comme un défaut.
          */}
          {!isLoading && activeBookings.length > 0 ? (
            <Text style={styles.role}>
              {activeBookings.length === 1
                ? tr('home.une_reservation_en_cours')
                : tr('home.n_reservations_en_cours', { n: activeBookings.length })}
            </Text>
          ) : null}
        </View>
        <Avatar name={user?.name ?? '?'} size={48} accessibilityLabel={user?.name ?? tr('commun.profil')} />
      </View>

      <View style={styles.focusWrap}>
        {isLoading ? (
          <Skeleton width="100%" height={180} />
        ) : isFirstTime ? (
          <GlassSurface strong radius={radius.lg} style={styles.welcomeCard} testID="home-welcome">
            <Icon name="home-outline" size={48} color={colors.brand[400]} />
            <Text style={styles.welcomeTitle}>{tr('home.bienvenue_sur_brio')}</Text>
            <Text style={styles.welcomeText}>
              {tr('home.reservez_votre_premier_service')}
            </Text>
          </GlassSurface>
        ) : focus ? (
          <TouchableOpacity
            activeOpacity={0.85}
            onPress={() =>
              focusIsLive
                ? navigation.navigate('MissionTracking', { bookingId: focus.id })
                : navigation.navigate('BookingDetail', { bookingId: focus.id })
            }
            accessibilityRole="button"
            accessibilityLabel={`${focus.service_name} — ${focusIsLive ? tr('home.suivre_en_direct') : tr('home.voir_le_detail')}`}
            testID="home-focus-booking"
          >
            {/*
                LA PLAQUE DU PROJET, PAS UN APLAT À ELLE.

                Cette carte se peignait son propre fond — `t.card` et une ombre douce — alors que
                tout le reste de l'application est en verre sur la toile nuit. Sur l'écran le plus
                vu, c'était le seul morceau qui se lisait comme rapporté d'ailleurs.

                Le rail dit ce que la carte engage : `decision` quand le prestataire est en route,
                parce que c'est là que le client a quelque chose à suivre.
            */}
            <CarteDeMission
              ton={focusIsLive ? 'decision' : 'neutre'}
              style={styles.carteSansMarge}
              testID="home-focus-carte"
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
                  {focusIsLive ? tr('home.suivre_en_direct_2') : tr('home.voir_le_detail_2')}
                </Text>
              </View>
            </CarteDeMission>
          </TouchableOpacity>
        ) : (
          <GlassSurface strong radius={radius.lg} style={styles.welcomeCard} testID="home-no-active">
            <Icon name="calendar-outline" size={40} color={colors.brand[400]} />
            <Text style={styles.welcomeText}>
              {tr('home.aucune_reservation_en_cours')}
            </Text>
          </GlassSurface>
        )}

        {/*
            TOUTES LES MISSIONS EN COURS, PAS UN DÉCOMPTE.

            L'accueil n'affichait qu'une carte focale et une ligne « 2 autres réservations en
            cours » — un chiffre sur lequel on ne peut pas appuyer. Un client qui a deux
            interventions le même jour ne pouvait atteindre la seconde que par un autre onglet.
        */}
        {autresBookings.length > 0 ? (
          <View style={styles.othersWrap} testID="home-other-bookings">
            <Text style={styles.moreLabel}>{autresBookings.length === 1
                ? tr('home.une_autre_en_cours')
                : tr('home.n_autres_en_cours', { n: autresBookings.length })}</Text>

            {/* L'entrée décalée est celle des listes du projet — et elle disparaît d'elle-même
                quand l'appareil a réduit les mouvements. */}
            {autresBookings.map((b, i) => (
              <AnimatedListItem key={b.id} index={i}>
                <TouchableOpacity
                  activeOpacity={0.85}
                  onPress={() =>
                    isLive(b)
                      ? navigation.navigate('MissionTracking', { bookingId: b.id })
                      : navigation.navigate('BookingDetail', { bookingId: b.id })
                  }
                  accessibilityRole="button"
                  accessibilityLabel={`${b.service_name} — ${isLive(b) ? tr('home.suivre_en_direct') : tr('home.voir_le_detail')}`}
                  testID={`home-other-booking-${b.id}`}
                >
                  <CarteDeMission
                    ton={isLive(b) ? 'decision' : 'neutre'}
                    style={styles.carteSecondaire}
                  >
                    <View style={styles.otherRow}>
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
                    </View>
                  </CarteDeMission>
                </TouchableOpacity>
              </AnimatedListItem>
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
  /* `CarteDeMission` porte sa marge haute pour le parcours mission ; sur l'accueil, c'est la
     grille de l'écran qui espace, pas la carte. */
  carteSansMarge: { marginTop: 0 },
  carteSecondaire: { marginTop: 0 },
  focusHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  focusService: { color: t.textOnGlass, fontSize: typography.fontSize.lg, fontWeight: typography.fontWeight.semibold, flex: 1 },
  focusDate: { color: t.textOnGlass, fontSize: typography.fontSize.sm },
  focusAddress: { color: t.mutedOnGlass, fontSize: typography.fontSize.xs },
  focusCta: { flexDirection: 'row', alignItems: 'center', gap: spacing.xs, marginTop: spacing.sm },
  focusCtaText: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: t.brandText,
  },
  moreLabel: { color: t.textMuted, fontSize: typography.fontSize.xs, textAlign: 'center' },
  othersWrap: { gap: spacing.xs },
  otherRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  otherText: { flex: 1 },
  otherService: { color: t.textOnGlass, fontSize: typography.fontSize.sm, fontWeight: typography.fontWeight.semibold },
  otherMeta: { color: t.mutedOnGlass, fontSize: typography.fontSize.xs, marginTop: 2 },
  /* Le fond vient de `GlassSurface` : un aplat posé ici le masquerait entièrement. */
  welcomeCard: {
    padding: spacing.lg,
    alignItems: 'center',
    gap: spacing.sm,
  },
  welcomeTitle: { color: t.textOnGlass, fontSize: typography.fontSize.xl, fontWeight: typography.fontWeight.bold, textAlign: 'center' },
  welcomeText: { color: t.mutedOnGlass, fontSize: typography.fontSize.sm, textAlign: 'center', lineHeight: 20 },
  floating: { position: 'absolute', left: spacing.md, right: spacing.md, bottom: spacing.lg, gap: spacing.sm },
});
