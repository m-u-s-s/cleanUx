import React from 'react';
/* Chemin direct plutot que le baril : des suites mockent les barils a la main,
   et un export neuf y manque sans que `tsc` bronche. */
import { formatMontant } from '@/format/money';
import { View, Text, StyleSheet } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import type {
  NativeStackNavigationProp,
  NativeStackScreenProps,
} from '@react-navigation/native-stack';
import { Screen, Button, Badge, Divider, DetailRow, EmptyState, ErrorState } from '@/ui';
/* Chemin direct : trente-six suites mockent `@/ui` a la main, et un export neuf y manque
   sans que `tsc` bronche — le composant arrive alors `undefined` au rendu. */
import { GrilleDeCases } from '@/ui/GrilleDeCases';
import { useBookingDetail } from '@/booking';
import { useCompletionCode } from '@/tracking';
import type { CompletionCode } from '@/tracking';
import { colors, spacing, typography, radius, shadows } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { libelleStatut, formatDateHeure } from '@/lib/format';
import type { RootStackParamList } from '@/navigation/types';
import { useTraduction } from '@/i18n';

type Props = NativeStackScreenProps<RootStackParamList, 'BookingDetail'>;

/**
 * Ce qu'on montre au client quand le code de fin est refusé.
 *
 * Le message du SERVEUR d'abord : lui seul sait pourquoi il refuse, et il le dit en français.
 * Ensuite le code HTTP, traduit. JAMAIS `error.message` : c'est le texte interne de la
 * bibliothèque HTTP, en anglais — « Request failed with status code 404 » s'affichait tel quel.
 */
function messageDeRefus(erreur: any): string {
  const duServeur = erreur?.response?.data?.message;

  if (typeof duServeur === 'string' && duServeur.trim() !== '') {
    return duServeur;
  }

  switch (erreur?.response?.status) {
    case 404:
      return 'Aucun code n’est disponible pour le moment : le prestataire n’a pas encore démarré l’intervention.';
    case 403:
      return 'Ce code ne vous est pas destiné.';
    case 429:
      return 'Trop de demandes coup sur coup. Réessayez dans une minute.';
    default:
      return 'Impossible d’obtenir le code pour le moment. Réessayez dans un instant.';
  }
}

export function BookingDetailScreen({ route }: Props) {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  const { bookingId } = route.params;
  const { data: booking, isLoading, isError, refetch } = useBookingDetail(bookingId);
  const navigation = useNavigation<NativeStackNavigationProp<RootStackParamList>>();

  /*
   * LE CODE DE FIN, DEMANDABLE DEPUIS « MES RENDEZ-VOUS ».
   *
   * Le prestataire ne peut pas clôturer sans six chiffres que le client seul détient. Ils
   * n'existaient nulle part dans cet écran : le client devait espérer un SMS, que le plafond de
   * cinq envois par heure et par numéro bloque justement au moment où l'on en a besoin.
   *
   * Les hooks sont déclarés AVANT les retours anticipés de chargement et d'erreur : placés après,
   * ils n'existeraient pas au premier rendu puis apparaîtraient au second, ce que React refuse.
   */
  const demandeCode = useCompletionCode(bookingId);
  const [codeDeFin, setCodeDeFin] = React.useState<CompletionCode | null>(null);
  const [refusCode, setRefusCode] = React.useState<string | null>(null);

  const demanderLeCode = () => {
    setRefusCode(null);
    demandeCode.mutate(undefined, {
      onSuccess: setCodeDeFin,
      /*
       * LE REFUS DU SERVEUR EST AFFICHÉ QUAND IL EST LISIBLE — jamais celui d'axios.
       *
       * Le repli était `e.message`, c'est-à-dire le texte interne de la bibliothèque HTTP. Quand
       * le serveur répond 404 sans corps, le client lisait « Request failed with status code
       * 404 » — en rouge, en anglais, au milieu d'une app française. Vu à l'écran en déroulant le
       * parcours.
       *
       * On garde le message du serveur, qui sait pourquoi il refuse, et on traduit le silence.
       */
      onError: (e: any) => setRefusCode(messageDeRefus(e)),
    });
  };

  if (isLoading) {
    return (
      <Screen>
        <Text style={styles.loading}>{tr('booking_detail.chargement')}</Text>
      </Screen>
    );
  }

  if (isError) {
    return (
      <Screen>
        <ErrorState
          message="Impossible de charger cette réservation."
          onRetry={() => void refetch()}
        />
      </Screen>
    );
  }

  if (!booking) {
    return (
      <Screen>
        <EmptyState
          title={tr('booking_detail.reservation_introuvable')}
          message="Cette réservation n'existe plus ou n'est pas accessible."
        />
      </Screen>
    );
  }

  /*
    L'ÉTAT NORMALISÉ, PAS LE STATUT BRUT.

    `status` porte le vocabulaire du domaine — `termine`, `annule`, `en_attente`, `sur_place` —
    tandis que `state` porte les six valeurs anglaises que le reste de cet écran compare.
    Comparer `status` à 'completed' était donc toujours faux sur des données françaises :
    « Évaluer la prestation » et « Laisser un pourboire » ne s'affichaient jamais, et la carte
    « Code de fin » s'affichait au contraire sur des réservations annulées ou en attente.
  */
  const etat = booking.state ?? booking.status;
  const isCompleted = etat === 'completed';
  const canStart = etat === 'confirmed';
  const canEnd = etat === 'in_progress';
  const canTrack = ['confirmed', 'in_progress'].includes(etat);

  const statusVariant =
    etat === 'completed'
      ? 'success'
      : etat === 'cancelled'
      ? 'danger'
      : 'brand';

  return (
    <Screen scroll>
      <View style={styles.header}>
        <Text style={styles.title}>{booking.service_name}</Text>
        <Badge label={libelleStatut(etat)} variant={statusVariant} />
      </View>

      {booking.contract_covered ? (
        <View style={styles.badgeRow} testID="contract-coverage-badge">
          <Badge label={tr('booking_detail.couvert_par_votre_contrat')} variant="info" />
        </View>
      ) : null}

      {/*
        LES REPERES EN CASES, LES TEXTES LONGS EN LIGNES.

        Quatre lignes separees par des filets : il fallait parcourir la carte de haut en bas
        pour retrouver l'heure. L'heure et le prix — les deux qu'on cherche en premier —
        passent en cases, que l'oeil attrape d'un coup.

        LA DATE COMPLETE RESTE EN LIGNE, et ce n'est pas un demi-choix : « 10 juin 2026 a
        09h00 » comprime dans une case de 45 % de large se tronque au deuxieme mot. Une case
        porte une valeur COURTE ; une phrase y perd sa fin.
      */}
      <GrilleDeCases
        colonnes={2}
        style={styles.grille}
        cases={[
          {
            libelle: 'Heure',
            valeur: booking.scheduled_time ? booking.scheduled_time.slice(0, 5) : '—',
            ton: 'accent',
          },
          ...(booking.estimated_price != null
            ? [{ libelle: 'Prix', valeur: formatMontant(booking.estimated_price, booking.currency), ton: 'bon' as const }]
            : []),
        ]}
      />

      <View style={styles.card}>
        <DetailRow
          label={tr('booking_detail.date')}
          value={formatDateHeure(booking.scheduled_date, booking.scheduled_time)}
        />
        <Divider />
        <DetailRow
          label={tr('booking_detail.adresse')}
          value={`${booking.address}, ${booking.city}`}
        />
        {booking.provider_name ? (
          <>
            <Divider />
            <DetailRow label={tr('booking_detail.prestataire')} value={booking.provider_name} />
          </>
        ) : null}
      </View>

      {/*
        LE CODE DE FIN, ICI ET PAS AILLEURS.

        C'est cet écran que le client ouvre quand le prestataire lui demande ses six chiffres. La
        carte reste visible pendant toute la mission : le serveur, lui, sait si elle a démarré et
        refuse proprement sinon. Reconstruire cette condition ici serait faux — la réservation
        reste `confirme` pendant toute la durée de l'intervention.

        MAIS PAS SUR UNE DEMANDE QUE PERSONNE N'A ENCORE ACCEPTÉE. `pending` signifie qu'aucun
        prestataire n'est assigné : il n'existe alors ni mission, ni code, et le bouton ne pouvait
        que échouer. Ce n'est pas deviner la règle du serveur, c'est ne pas proposer un geste dont
        on sait qu'il n'a pas d'objet.
      */}
      {!isCompleted && etat !== 'cancelled' && etat !== 'pending' && (
        <View style={styles.card} testID="carte-code-de-fin">
          <Text style={styles.codeTitre}>{tr('booking_detail.code_de_fin')}</Text>

          {codeDeFin ? (
            <>
              <Text style={styles.codeChiffres}>{codeDeFin.code}</Text>
              <Text style={styles.codeAide}>
                {tr('booking_detail.donnez_ce_code_au_prestataire')}
              </Text>
              <Button
                label={tr('booking_detail.en_generer_un_nouveau')}
                onPress={demanderLeCode}
                loading={demandeCode.isPending}
                variant="secondary"
                fullWidth
              />
            </>
          ) : (
            <>
              <Text style={styles.codeAide}>
                Le prestataire a besoin de six chiffres pour clôturer. Affichez-les au moment où il
                vous les demande : ils ne restent valables que vingt minutes.
              </Text>
              <Button
                label={tr('booking_detail.afficher_mon_code_de_fin')}
                onPress={demanderLeCode}
                loading={demandeCode.isPending}
                fullWidth
              />
            </>
          )}

          {refusCode ? <Text style={styles.codeRefus}>{refusCode}</Text> : null}
        </View>
      )}

      <View style={styles.actions}>
        {['pending', 'confirmed'].includes(etat) && booking.estimated_price != null && (
          <Button
            label={`Payer ${formatMontant(booking.estimated_price, booking.currency)}`}
            onPress={() => navigation.navigate('PaymentCheckout', { bookingId })}
            fullWidth
          />
        )}
        {canTrack && (
          <Button
            label={tr('booking_detail.suivre_en_direct')}
            onPress={() => navigation.navigate('MissionTracking', { bookingId })}
            variant="secondary"
            fullWidth
          />
        )}
        {canStart && (
          <Button
            label={tr('booking_detail.scanner_qr_demarrer')}
            onPress={() =>
              navigation.navigate('QRScan', { bookingId, action: 'start' })
            }
            fullWidth
          />
        )}
        {canEnd && (
          <Button
            label={tr('booking_detail.scanner_qr_terminer')}
            onPress={() =>
              navigation.navigate('QRScan', { bookingId, action: 'end' })
            }
            variant="danger"
            fullWidth
          />
        )}
        {isCompleted && (
          <Button
            label={tr('booking_detail.evaluer_la_prestation')}
            onPress={() => navigation.navigate('Rating', { bookingId })}
            variant="secondary"
            fullWidth
          />
        )}
        {isCompleted && (
          <Button
            label={tr('booking_detail.laisser_un_pourboire')}
            onPress={() => navigation.navigate('Tips', { bookingId })}
            variant="secondary"
            fullWidth
          />
        )}
      </View>
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  loading: {
    fontSize: typography.fontSize.base,
    color: t.textSecondary,
    textAlign: 'center',
    marginTop: spacing.xl,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: spacing.lg,
    marginTop: spacing.md,
  },
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
    flex: 1,
    marginRight: spacing.sm,
  },
  badgeRow: {
    flexDirection: 'row',
    marginTop: -spacing.sm,
    marginBottom: spacing.md,
  },
  grille: {
    marginBottom: spacing.md,
  },
  card: {
    backgroundColor: t.card,
    borderRadius: radius.md,
    padding: spacing.md,
    ...shadows.soft,
    marginBottom: spacing.lg,
  },
  actions: {
    gap: spacing.sm,
  },
  codeTitre: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
    marginBottom: spacing.xs,
  },
  codeChiffres: {
    fontSize: typography.fontSize['2xl'],
    fontWeight: typography.fontWeight.bold,
    letterSpacing: 8,
    color: t.text,
    marginVertical: spacing.sm,
  },
  codeAide: {
    fontSize: typography.fontSize.sm,
    color: t.textSecondary,
    marginBottom: spacing.sm,
  },
  codeRefus: {
    fontSize: typography.fontSize.sm,
    color: t.danger,
    marginTop: spacing.sm,
  },
});
