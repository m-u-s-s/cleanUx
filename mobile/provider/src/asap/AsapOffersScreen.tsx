import React, { useState } from 'react';
/* Chemin direct plutot que le baril : des suites mockent les barils a la main. */
import { formatCentimes } from '@/format/money';
import { View, Text, StyleSheet, ScrollView } from 'react-native';
import { useNavigation } from '@react-navigation/native';
import { Screen, Button, Badge, EmptyState, Skeleton } from '@/ui';
import {spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useAsapOffers, useAcceptAsapOffer, useDeclineAsapOffer } from './hooks';
import type { AsapOffer } from './types';
import { useTraduction } from '@/i18n';

/**
 * Les courses immédiates proposées à ce prestataire.
 *
 * L'autre bout d'une boucle qui n'existait qu'à moitié : les points d'API étaient livrés depuis le
 * moteur de commande, et l'application ne les appelait nulle part. Un client pouvait demander une
 * intervention dans l'heure depuis son téléphone, et AUCUN prestataire utilisant l'application ne
 * pouvait l'accepter.
 *
 * Ce que l'écran doit donner tient en quatre choses — le métier, la distance, le montant,
 * l'attente — et deux boutons. Le prestataire est au volant ou sur un chantier ; tout le reste lui
 * coûte la course.
 */
export function AsapOffersScreen() {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  const navigation = useNavigation<any>();
  const { offers, isLoading } = useAsapOffers();
  const accept = useAcceptAsapOffer();
  const decline = useDeclineAsapOffer();
  const [notice, setNotice] = useState<string | null>(null);

  const handleAccept = (offer: AsapOffer) => {
    setNotice(null);
    accept.mutate(offer.asap_dispatch_request_id, {
      /*
       * On envoie vers la LISTE des missions, pas vers un écran de détail.
       *
       * L'acceptation rend un `booking_id` ; `MissionDetail` attend un `missionId`, qui est l'id
       * d'un modèle Mission — deux identifiants différents. Les confondre ouvrirait le détail
       * d'une autre intervention, ou de rien du tout : c'est exactement le défaut que signale le
       * commentaire de l'écran d'offre voisin, et la table `missions` est de surcroît vide en
       * pratique.
       *
       * Le paramètre imbriqué est nécessaire : l'onglet vit dans `MainTabs`, et sans lui le tap
       * ne ferait rien.
       */
      onSuccess: () => {
        navigation.navigate('MainTabs', { screen: 'Missions' });
      },
      /*
       * 409 = premier arrivé, premier servi. Ce n'est PAS une panne, et le dire en ces termes
       * évite qu'un prestataire croie l'application cassée et cesse d'ouvrir cet écran.
       */
      onError: (error: { status?: number }) => {
        setNotice(
          error?.status === 409
            ? tr('asap_offers.cette_course_vient_detre_prise')
            : tr('asap_offers.impossible_daccepter_cette_course_pour'),
        );
      },
    });
  };

  const handleDecline = (offer: AsapOffer) => {
    setNotice(null);
    decline.mutate({ requestId: offer.asap_dispatch_request_id }, {
      onError: () => setNotice('Impossible d’enregistrer votre refus. Réessayez.'),
    });
  };

  return (
    <Screen>
      <ScrollView contentContainerStyle={styles.body}>
        <Text style={styles.title}>{tr('asap_offers.courses_immediates')}</Text>
        <Text style={styles.subtitle}>
          {tr('asap_offers.un_client_attend_maintenant_la')}
        </Text>

        {notice ? (
          <Text style={styles.notice} accessibilityRole="alert">{notice}</Text>
        ) : null}

        {isLoading && offers.length === 0 ? <Skeleton width="100%" height={120} /> : null}

        {!isLoading && offers.length === 0 ? (
          <EmptyState
            icon="flash-outline"
            title={tr('asap_offers.aucune_course_pour_linstant')}
            message="Restez en ligne : les demandes urgentes arrivent ici dès qu’un client en lance une près de vous."
          />
        ) : null}

        {offers.map((offer) => (
          <View key={offer.id} style={styles.card}>
            <View style={styles.cardHead}>
              <Text style={styles.trade}>{offer.trade ?? 'Intervention'}</Text>
              {offer.waiting_seconds != null ? (
                <Badge label={`attend ${Math.max(0, Math.round(offer.waiting_seconds))} s`} variant="warning" />
              ) : null}
            </View>

            <Text style={styles.meta}>
              {offer.distance_km != null ? `à ${formatKm(offer.distance_km)} km` : 'distance inconnue'}
            </Text>

            {/* Le montant que le CLIENT a déjà accepté : c'est le chiffre qui engagera le
                prestataire, pas une estimation refaite de son côté. */}
            <Text style={styles.amount}>{formatRange(offer)}</Text>

            <View style={styles.actions}>
              <Button label={tr('asap_offers.accepter')} onPress={() => handleAccept(offer)} />
              <Button label={tr('asap_offers.passer')} variant="secondary" onPress={() => handleDecline(offer)} />
            </View>
          </View>
        ))}
      </ScrollView>
    </Screen>
  );
}

/** Virgule décimale : « 2,3 km », pas « 2.3 km ». */
function formatKm(km: number): string {
  return km.toFixed(1).replace('.', ',');
}

function formatRange(offer: AsapOffer): string {
  const min = offer.estimate_min_cents;
  const max = offer.estimate_max_cents;

  if (min == null && max == null) {
    return 'Montant à confirmer';
  }
  if (min != null && max != null && min !== max) {
    return `${euros(min)} – ${euros(max)}`;
  }

  return `${euros((min ?? max)!)}`;
}

/*
 * L'ARRONDI RESTE, LE SYMBOLE PART. Une fourchette d'offre se lit d'un coup d'oeil : les
 * centimes y sont du bruit. Mais le symbole vivait a l'exterieur, une seule fois pour
 * deux montants — donc jamais dans la devise de l'offre.
 */
function euros(cents: number): string {
  return formatCentimes(cents, undefined, 0);
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  body: { padding: spacing.md, gap: spacing.md },
  title: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
  },
  subtitle: { fontSize: typography.fontSize.sm, color: t.textSecondary },
  notice: {
    fontSize: typography.fontSize.sm,
    color: t.text,
    backgroundColor: t.inputBg,
    padding: spacing.md,
    borderRadius: radius.md,
  },
  card: {
    backgroundColor: t.cardSubtle,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: t.border,
    padding: spacing.md,
    gap: spacing.xs,
  },
  cardHead: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  trade: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
  },
  meta: { fontSize: typography.fontSize.sm, color: t.textSecondary },
  /* Chasse fixe sur le montant : c'est le chiffre qui décide, il ne doit pas sauter. */
  amount: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    fontVariant: ['tabular-nums'],
    color: t.text,
  },
  actions: { flexDirection: 'row', gap: spacing.sm, marginTop: spacing.sm },
});
