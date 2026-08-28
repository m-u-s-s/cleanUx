import React, { useState } from 'react';
/* Chemin direct plutot que le baril : des suites mockent les barils a la main. */
import { formatMontant } from '@/format/money';
import { View, Text, Alert, StyleSheet, TouchableOpacity } from 'react-native';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { useNavigation } from '@react-navigation/native';
import { Screen, Button, Badge, Divider, TextInput } from '@/ui';
/*
 * IMPORT PAR CHEMIN DIRECT, ET C'EST DELIBERE.
 *
 * Trente-six suites portent un `jest.mock('@/ui', ...)` ecrit a la main : tout export
 * neuf y manque, `tsc` reste vert, et le composant arrive `undefined` au rendu — « Element
 * type is invalid ». Le depot contourne deja ce piege ainsi pour `glassBars` et `OsmMap`.
 */
import { GrilleDeCases } from '@/ui/GrilleDeCases';
import { useMissionDetail, useMissionLifecycle, useResendMissionCode, missionStatusLabel } from '@/missions';
import { BandeauRetard } from '@/screens/components/BandeauRetard';
import type { MissionLifecyclePayload, MissionPayoutAnnouncement } from '@/missions';
import { useArriveOnSite } from '@/tracking';
import { colors, spacing, typography, radius, shadows, useThemeColors } from '@/theme';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { RootStackParamList } from '@/navigation/types';
import { formatAdresse, formatDateHeure, messageDErreur } from '@brio/shared/format';
import { useTraduction } from '@/i18n';

type Props = NativeStackScreenProps<RootStackParamList, 'MissionDetail'>;

export function MissionDetailScreen({ route }: Props) {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  const { missionId } = route.params;
  const { data: mission, isLoading } = useMissionDetail(missionId);
  const lifecycle = useMissionLifecycle(missionId);
  const resendCode = useResendMissionCode(missionId);

  /**
   * Le retour est EXPLICITE, et porte le numéro masqué.
   *
   * « Envoyé » sans destinataire laisse le doute sur le bon client, et c'est ce doute qui fait
   * appuyer trois fois — ce qui épuise le plafond SMS et prive le client des codes SUIVANTS. Un
   * refus pour attente est dit tel quel : ce n'est pas une panne, c'est un garde-fou.
   */
  const handleResend = (type: 'start' | 'end') => {
    resendCode.mutate(type, {
      onSuccess: (r) =>
        Alert.alert(
          tr('mission_detail.code_renvoye'),
          r?.sent_to
            ? `Un nouveau code vient d’être envoyé au ${r.sent_to}. L’ancien n’est plus valide.`
            : 'Un nouveau code vient d’être envoyé. L’ancien n’est plus valide.',
        ),
      onError: (e: any) => Alert.alert(tr('mission_detail.impossible'), messageDErreur(e, 'Réessayez dans un instant.')),
    });
  };
  const arriveOnSite = useArriveOnSite(mission?.booking_id ?? null, missionId);
  const navigation = useNavigation<any>();
  const themeColors = useThemeColors();

  // Code de début, communiqué au client par SMS à l'arrivée du prestataire : c'est lui qui
  // atteste la présence sur place. Sans lui le serveur refuse le démarrage.
  //
  // Déclaré AVANT le retour anticipé de chargement : placé après, le hook n'existait pas au
  // premier rendu puis apparaissait au second, ce que React refuse — « Rendered more hooks than
  // during the previous render », et l'écran plantait.
  const [startCode, setStartCode] = useState('');

  // Même raison que ci-dessus pour le code de FIN : déclaré avant tout retour anticipé.
  const [endCode, setEndCode] = useState('');

  if (isLoading || !mission) {
    return (
      <Screen>
        <Text style={styles.loading}>{tr('mission_detail.chargement')}</Text>
      </Screen>
    );
  }

  /**
   * TOUTE ACTION DE CYCLE DE VIE REND SA RÉPONSE VISIBLE.
   *
   * `lifecycle.mutate(action)` était appelé sans `onError`. Or le serveur refuse la clôture par un
   * 422 « Le code de fin est requis pour clôturer cette mission » dès qu'un code de fin est en
   * attente — ce qui est le cas normal, puisqu'il naît à l'arrivée. Le prestataire appuyait sur
   * « Mission terminée », confirmait, et regardait un écran rigoureusement inchangé : ni erreur,
   * ni progression, aucun moyen de deviner qu'il manquait six chiffres.
   *
   * L'action passe désormais par une charge complète, code compris, et la réponse du serveur —
   * refus comme succès — est toujours affichée.
   */
  const handleAction = (payload: MissionLifecyclePayload, label: string) => {
    const action = typeof payload === 'string' ? payload : payload.action;

    Alert.alert(label, `Confirmer "${label}" ?`, [
      { text: 'Annuler', style: 'cancel' },
      {
        text: 'Confirmer',
        onPress: () =>
          lifecycle.mutate(payload, {
            onSuccess: (resultat) => {
              if (action !== 'complete') return;

              Alert.alert(tr('mission_detail.felicitations'), messageDeCloture(resultat?.payout));
            },
            onError: (e: any) =>
              Alert.alert(tr('mission_detail.impossible'), messageDErreur(e, 'Réessayez dans un instant.')),
          }),
      },
    ]);
  };

  /**
   * Annonce l'arrivée puis ouvre la preuve de présence.
   *
   * Idempotent de bout en bout : l'arrivée de la mission échoue en silence si elle a déjà eu
   * lieu, la session de suivi est réutilisée si elle existe. Le même geste sert donc à annoncer
   * l'arrivée ET à revenir au scanner depuis une mission déjà `arrived` — sans quoi quitter
   * l'écran laisserait le prestataire sans aucun chemin vers le code du client.
   */
  const handleArrival = () => {
    if (mission.booking_id == null) return;

    arriveOnSite.mutate(undefined, {
      onSuccess: (session) => navigation.navigate('PresenceScan', { sessionId: session.id }),
      onError: (e: any) => Alert.alert(tr('mission_detail.impossible'), messageDErreur(e, 'Réessayez.')),
    });
  };

  const badgeVariant =
    mission.status === 'completed'
      ? 'success'
      : mission.status === 'cancelled'
        ? 'danger'
        : 'brand';

  return (
    <Screen scroll>
      <View style={styles.header}>
        <Text style={styles.title}>{mission.service_name}</Text>
        <Badge label={missionStatusLabel(mission.status)} variant={badgeVariant} />
      </View>
      {/*
        LES REPERES EN CASES, LE RESTE EN LIGNES.

        Ces quatre informations etaient quatre lignes separees par des filets : sur un
        telephone, il fallait parcourir la carte de haut en bas pour retrouver l'heure.
        La date et le prix — les deux qu'on cherche en premier — passent en cases, que
        l'oeil attrape d'un coup.

        Le client et l'adresse RESTENT en lignes : ce sont des textes longs, et une
        adresse comprimee dans une case de 45 % de large se tronque au deuxieme mot.
      */}
      <GrilleDeCases
        colonnes={2}
        cases={[
          {
            libelle: 'Date',
            valeur: formatDateHeure(mission.scheduled_date, mission.scheduled_time),
            ton: 'accent',
          },
          ...(mission.total_price != null
            ? [{ libelle: 'Prix', valeur: formatMontant(mission.total_price), ton: 'bon' as const }]
            : []),
        ]}
        style={styles.grille}
      />

      <View style={[styles.card, { backgroundColor: themeColors.card }]}>
        <DetailRow label={tr('mission_detail.client')} value={mission.client_name} />
        <Divider />
        <DetailRow label={tr('mission_detail.adresse')} value={formatAdresse(mission.address, mission.city)} />
      </View>
      {/*
        LE RETARD PASSE AVANT LES ACTIONS.

        Sous les boutons, il serait lu apres avoir appuye — c'est-a-dire trop tard pour changer
        quoi que ce soit a la façon d'arriver.
      */}
      <BandeauRetard missionId={missionId} />

      <View style={styles.actions}>
        {mission.status === 'assigned' && (
          <Button
            label={tr('mission_detail.en_route')}
            onPress={() => handleAction('start', 'En route')}
            fullWidth
          />
        )}
        {mission.status === 'en_route' && (
          <>
            {/* Le suivi vit sur la RÉSERVATION, pas sur la mission : c'est elle qui porte la
                session GPS partagée avec le client. */}
            {mission.booking_id != null && (
              <Button
                label={tr('mission_detail.suivi_gps')}
                onPress={() => navigation.navigate('MissionTracking', {
                  missionId,
                  bookingId: mission.booking_id as number,
                })}
                variant="secondary"
                fullWidth
              />
            )}
            {/* La géo-barrière fait basculer la session toute seule à 150 m : c'est une
                proximité, pas une présence. Ce geste-ci ouvre la preuve à scanner chez le
                client. */}
            <Button
              label={tr('mission_detail.je_suis_arrive')}
              onPress={handleArrival}
              loading={arriveOnSite.isPending}
              fullWidth
            />
          </>
        )}
        {mission.status === 'arrived' && (
          <>
            {/* Le scan reste atteignable après l'arrivée : le prestataire qui quitte l'écran
                doit pouvoir y revenir tant que la présence n'est pas confirmée. */}
            <Button
              label={tr('mission_detail.confirmer_ma_presence')}
              onPress={handleArrival}
              loading={arriveOnSite.isPending}
              fullWidth
            />
            <TextInput
              label={tr('mission_detail.code_de_debut_donne_au')}
              value={startCode}
              onChangeText={setStartCode}
              keyboardType="number-pad"
              maxLength={6}
              placeholder="000000"
            />
            {/*
              LE RECOURS QUAND LE SMS N'ARRIVE PAS.

              Réseau du client, numéro mal saisi, message noyé, plafond d'envoi atteint : sans ce
              lien, l'intervention s'arrêtait là — le prestataire devant la porte, le client sans
              ses six chiffres, et pour seul recours l'annulation de la mission.
            */}
            <TouchableOpacity
              onPress={() => handleResend('start')}
              disabled={resendCode.isPending}
              accessibilityRole="button"
              accessibilityLabel={tr('mission_detail.renvoyer_le_code_de_debut')}
              testID="resend-start-code"
              style={styles.resendLink}
            >
              <Text style={styles.resendText}>
                {resendCode.isPending ? 'Envoi…' : 'Le client n’a rien reçu ? Renvoyer le SMS'}
              </Text>
            </TouchableOpacity>
            {/* `begin`, PAS `start` : `start` appelle setEnRoute côté serveur, et depuis
                `arrived` cette transition est invalide — l'ancien bouton recevait un 422. */}
            <Button
              label={tr('mission_detail.demarrer_mission')}
              onPress={() =>
                startCode.length === 6
                  ? lifecycle.mutate(
                      { action: 'begin', code: startCode },
                      {
                        /*
                         * SANS CECI, UN CODE REFUSÉ NE DISAIT RIEN.
                         *
                         * C'était le SEUL appel de cycle de vie sans `onError` : le prestataire
                         * saisissait six chiffres périmés, appuyait, et l'écran restait
                         * rigoureusement identique. Aucune erreur, aucune progression, aucun moyen
                         * de deviner qu'il fallait redemander un code. Constaté à l'écran.
                         */
                        onError: (e: any) =>
                          Alert.alert(tr('mission_detail.impossible'), messageDErreur(e, 'Réessayez dans un instant.')),
                      },
                    )
                  : Alert.alert(tr('mission_detail.code_requis'), tr('mission_detail.demandez_au_client_le_code'))
              }
              fullWidth
            />
          </>
        )}
        {/* `started`, PAS `in_progress` : ce dernier n'existe dans aucun statut du backend
            (MissionStatus), si bien qu'une mission démarrée n'affichait AUCUNE action — le
            prestataire ne pouvait ni ouvrir la mission terrain ni la clôturer. */}
        {/*
          `paused` AUTANT QUE `started`, comme le fait le web depuis toujours.

          `GET /provider/missions/active` sert les deux : une mission en pause apparaît donc dans
          la liste du prestataire. Ne traiter que `started` la laissait sans la moindre action à
          son ouverture — il la voyait, la touchait, et se retrouvait devant un écran vide. Le
          suivi web, lui, affiche déjà le code de fin pour `['started','paused']` : c'est la même
          intervention, seulement interrompue.
        */}
        {['started', 'paused'].includes(mission.status) && (
          <>
            <Button
              label={tr('mission_detail.mission_terrain')}
              onPress={() => navigation.navigate('MissionField', { missionId })}
              fullWidth
            />
            {/* Clôture par le code que le client affiche : c'est son accord explicite, et il
                déclenche l'encaissement. Le bouton rouge en dessous garde l'ancien chemin par
                code SMS pour les missions sans écran client sous la main. */}
            <Button
              label={tr('mission_detail.cloturer_avec_le_client')}
              onPress={() => navigation.navigate('PresenceScan', { purpose: 'completion', missionId })}
              fullWidth
            />
            {/* Le code de fin que le client lit sur son espace — ou reçoit par SMS. Il était
                réclamé par le serveur sans qu'aucun champ ne permette de le saisir. */}
            <TextInput
              label={tr('mission_detail.code_de_fin_affiche_sur')}
              value={endCode}
              onChangeText={setEndCode}
              keyboardType="number-pad"
              maxLength={6}
              placeholder="000000"
              testID="end-code-input"
            />
            <Button
              label={tr('mission_detail.mission_terminee')}
              onPress={() =>
                handleAction(
                  // Sans code saisi, on laisse le serveur trancher : il accepte la clôture
                  // lorsqu'aucun code de fin n'est en attente, et la refuse sinon — avec un
                  // message désormais visible.
                  endCode.length === 6 ? { action: 'complete', code: endCode } : 'complete',
                  'Terminer',
                )
              }
              variant="danger"
              fullWidth
            />
            {/* Le code de FIN voyage par le même SMS, et se perd de la même façon. */}
            <TouchableOpacity
              onPress={() => handleResend('end')}
              disabled={resendCode.isPending}
              accessibilityRole="button"
              accessibilityLabel={tr('mission_detail.renvoyer_le_code_de_fin')}
              testID="resend-end-code"
              style={styles.resendLink}
            >
              <Text style={styles.resendText}>
                {resendCode.isPending ? 'Envoi…' : 'Le client n’a rien reçu ? Renvoyer le SMS'}
              </Text>
            </TouchableOpacity>
          </>
        )}
      </View>
    </Screen>
  );
}

/**
 * Ce que le prestataire lit à l'instant où il clôture : combien, et quand.
 *
 * Le montant vient du serveur et n'est jamais recalculé ici — un partage 80/20 refait côté client
 * divergerait du jour où un taux négocié serait activé, et c'est le prestataire qui découvrirait
 * l'écart sur son relevé.
 *
 * Sans annonce exploitable (réservation sans montant, service indisponible), on félicite quand
 * même : la mission EST terminée, et taire le succès pour une somme manquante donnerait
 * exactement l'impression de panne que ce chantier corrige.
 */
function messageDeCloture(payout?: MissionPayoutAnnouncement | null): string {
  const felicitations = 'Félicitations, vous avez fini votre mission.';

  if (!payout) {
    return `${felicitations} Le montant vous sera transféré selon votre calendrier de versement habituel.`;
  }

  // Le serveur envoie `devise` depuis toujours ; l'ecran la jetait et comptait en euros.
  const montant = formatMontant(payout.montant_prestataire, payout.devise);

  return `${felicitations} ${montant} seront transférés sur votre compte le ${dateFr(payout.date_transfert)}.`;
}

/** « 2026-08-19 » → « 19/08/2026 ». Formatage manuel : Intl est incomplet sous Hermes/Android. */
function dateFr(iso: string): string {
  const [annee, mois, jour] = (iso ?? '').split('-');

  return annee && mois && jour ? `${jour}/${mois}/${annee}` : iso;
}

function DetailRow({ label, value }: { label: string; value: string }) {
  const styles = stylesFor(useThemeColors());

  return (
    <View style={styles.row}>
      <Text style={styles.rowLabel}>{label}</Text>
      <Text style={styles.rowValue}>{value}</Text>
    </View>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  resendLink: { alignSelf: 'flex-start', paddingVertical: spacing.xs },
  resendText: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: t.brandText,
    textDecorationLine: 'underline',
  },
  loading: {
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
  },
  grille: {
    marginBottom: spacing.md,
  },
  card: {
    borderRadius: radius.md,
    padding: spacing.md,
    ...shadows.soft,
    marginBottom: spacing.lg,
  },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingVertical: spacing.sm,
  },
  rowLabel: {
    fontSize: typography.fontSize.sm,
    color: t.textSecondary,
  },
  rowValue: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.medium,
    color: t.text,
    flex: 1,
    textAlign: 'right',
    marginLeft: spacing.sm,
  },
  actions: {
    gap: spacing.sm,
  },
});
