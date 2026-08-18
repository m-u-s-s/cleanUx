import React from 'react';
import { View, Text, StyleSheet, Image, ScrollView, Alert } from 'react-native';
import { Screen, Badge, Skeleton, EmptyState, ProgressBar, Divider, Button } from '@/ui';
import {
  useOnSiteTimeline,
  useOnSiteMedia,
  useOnSiteIncidents,
  useOnSiteExtras,
  useRepondreAuSupplement,
  useProlongerLesHeures,
  useLiveOnSite,
} from '@/booking/onsite';
import type { OnSiteExtra, OnSiteMedia, OnSiteTimelineEntry } from '@/booking/onsite';
import { MissionClockBar, useMissionClock, AnnulerLaMissionSheet } from '@brio/shared';
import { MissionQuoteRevisionCard } from '@/screens/components/MissionQuoteRevisionCard';
import { MissionTodoCard } from '@/screens/components/MissionTodoCard';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import type { RootStackParamList } from '@/navigation/types';

type Props = NativeStackScreenProps<RootStackParamList, 'OnSite'>;

/**
 * L'INTERVENTION VUE DU SALON — ce qui est fait, ce qui a été photographié, ce qui cloche.
 *
 * Le suivi cartographique s'arrête à la porte : il montre un trajet, puis un point immobile
 * pendant deux heures. C'est exactement le moment où le client se demande ce qui se passe chez
 * lui — et où, faute de réponse, il appelle.
 *
 * Écran SÉPARÉ de la carte, et non un panneau de plus par-dessus : le suivi de trajet doit rester
 * lisible d'un coup d'œil pendant qu'on attend, et cette page-ci se lit posément. Elle s'ouvre
 * depuis le suivi et depuis le détail de la réservation.
 */
export function OnSiteScreen({ route }: Props) {
  const t = useThemeColors();
  const styles = stylesFor(t);

  const { bookingId } = route.params;
  const { data: fil, isLoading } = useOnSiteTimeline(bookingId);
  const { data: photos } = useOnSiteMedia(bookingId);
  const { data: imprevus } = useOnSiteIncidents(bookingId);
  const { data: supplements } = useOnSiteExtras(bookingId);
  const repondre = useRepondreAuSupplement(bookingId);

  /*
   * Le compteur défile sur l'appareil ; l'échéance et le montant viennent du fil, qui se
   * rafraîchit déjà toutes les minutes et à chaque événement temps réel.
   */
  const horloge = useMissionClock(fil?.clock);
  const prolongation = fil?.extension ?? null;
  const prolonge = useProlongerLesHeures(bookingId);
  const [choixOuvert, setChoixOuvert] = React.useState(false);
  const [annulationOuverte, setAnnulationOuverte] = React.useState(false);

  /**
   * PROLONGER — et DIRE pourquoi quand ça ne passe pas.
   *
   * Le serveur répond 422 avec une phrase écrite pour le client (« le temps supplémentaire est
   * déjà en cours de facturation »). L'afficher telle quelle est tout l'intérêt : un « une erreur
   * est survenue » sur un bouton qui engage de l'argent fait appuyer une deuxième fois.
   */
  const prolonger = (minutes: number) => {
    prolonge.mutate(minutes, {
      onSuccess: () => setChoixOuvert(false),
      onError: (erreur: { message?: string }) =>
        Alert.alert('Impossible', erreur.message ?? 'La prolongation n’a pas pu être enregistrée.'),
    });
  };

  // Le canal est indexé sur la MISSION : son identifiant vient du fil, le client n'en dispose pas
  // autrement.
  useLiveOnSite(bookingId, fil?.mission_id ?? null);

  /**
   * Répondre en un geste — un appui, une décision, pas de confirmation en deux temps.
   *
   * L'erreur est DITE : un supplément auquel on a déjà répondu depuis un autre appareil doit
   * s'expliquer, sinon le bouton semble ne rien faire et la personne appuie trois fois.
   */
  const repondreAuSupplement = (extraId: number, accepte: boolean) => {
    repondre.mutate(
      { extraId, accepte },
      {
        onError: (erreur: { message?: string }) =>
          Alert.alert('Impossible', erreur.message ?? 'Votre réponse n’a pas pu être enregistrée.'),
      },
    );
  };

  if (isLoading) {
    return (
      <Screen>
        <Skeleton width="100%" height={80} />
        <Skeleton width="100%" height={200} />
      </Screen>
    );
  }

  if (!fil || fil.mission_id === null) {
    return (
      <Screen>
        <EmptyState
          icon="time-outline"
          title="L’intervention n’a pas encore commencé"
          message="Vous verrez ici l’avancement, les photos et tout imprévu dès que le prestataire sera sur place."
        />
      </Screen>
    );
  }

  return (
    <Screen scroll>
      <View style={styles.entete}>
        <Text style={styles.titre}>Avancement</Text>
        <Badge
          label={`${fil.progress.done}/${fil.progress.total}`}
          variant={fil.progress.percent === 100 ? 'success' : 'brand'}
        />
      </View>
      {/* Rien à montrer quand aucune checklist n'est rattachée : « Étape 0 sur 1 » se lirait
          comme un travail qui n'avance pas, alors qu'il n'y a simplement rien à cocher. */}
      {fil.progress.total > 0 && (
        <ProgressBar step={fil.progress.done} totalSteps={fil.progress.total} />
      )}
      {/*
        L'ESTIMATION ET L'ENGAGEMENT SONT DEUX PHRASES DISTINCTES, et l'ordre compte.

        « Fin estimée vers 16 h » est une prévision sans conséquence. Le compteur en dessous dit ce
        qui a été ACHETÉ, et au-delà de quoi ça coûte. Les fondre en une seule ligne ferait passer
        une échéance contractuelle pour une estimation — et personne ne comprendrait la ligne
        supplémentaire sur sa facture.

        Le compteur se rend nul de lui-même sur toute prestation au forfait.
      */}
      {fil.estimated_end_at && (
        <Text style={styles.finEstimee}>
          Fin estimée vers {heure(fil.estimated_end_at)}
        </Text>
      )}

      <MissionClockBar
        clock={horloge}
        audience="client"
        style={styles.horloge}
        /*
          LE BOUTON N'APPARAÎT QUE SI LE SERVEUR L'AUTORISE, et il OUVRE un choix au lieu de
          prolonger d'un coup : ce geste engage de l'argent, il ne se déclenche pas par un appui
          distrait sur un écran qu'on consulte d'une main.
        */
        onExtend={prolongation?.allowed ? () => setChoixOuvert((ouvert) => !ouvert) : undefined}
        extendLabel={choixOuvert ? 'Fermer' : 'Prolonger'}
      />

      {choixOuvert && prolongation?.allowed ? (
        <View style={styles.prolongation} testID="panneau-prolongation">
          <Text style={styles.prolongationTitre}>Ajouter du temps</Text>
          <Text style={styles.prolongationNote}>
            Au tarif normal, sans majoration. Le montant s’ajoute à votre facture ; seules les
            heures réellement prestées sont dues.
          </Text>

          <View style={styles.prolongationChoix}>
            {prolongation.options.map((option) => (
              <Text
                key={option.minutes}
                testID={`prolonger-${option.minutes}`}
                accessibilityRole="button"
                onPress={() => prolonger(option.minutes)}
                style={[styles.prolongationOption, prolonge.isPending && styles.prolongationOptionInactive]}
              >
                +{option.label}
                {option.amount_cents !== null ? `  ·  ${euros(option.amount_cents)}` : ''}
              </Text>
            ))}
          </View>
        </View>
      ) : null}

      {/*
        LE MOTIF DU REFUS SE MONTRE, il ne se tait pas. Un client qui déborde doit comprendre
        pourquoi il ne peut plus prolonger — sinon la ligne majorée sur sa facture arrive sans
        explication, et c'est un litige.
      */}
      {horloge.applies && prolongation && !prolongation.allowed && prolongation.reason ? (
        <Text style={styles.prolongationRefus}>{prolongation.reason}</Text>
      ) : null}

      {/*
        LE NOUVEAU DEVIS AVANT TOUT LE RESTE.
 
        C'est la seule chose de cet écran qui engage le PRIX de la prestation, et elle attend une
        réponse pendant que le prestataire est devant la porte. Les suppléments, juste en dessous,
        ajoutent une ligne ; celui-ci remplace le total.
      */}
      <MissionQuoteRevisionCard bookingId={bookingId} />

      {/*
        LES SUPPLÉMENTS ENSUITE, avant l'avancement et les photos (F12).

        C'est la seule chose de cet écran qui ATTEND quelque chose du client, et elle l'attend
        maintenant : le prestataire est chez lui, à l'instant. Une réponse qui arrive après son
        départ ne sert plus à rien — la placer sous les photos reviendrait à la faire manquer.
      */}
      {(supplements ?? []).filter((s) => s.awaiting_client).map((supplement: OnSiteExtra) => (
        <View key={supplement.id} style={styles.supplement} testID={`supplement-${supplement.id}`}>
          <Text style={styles.supplementTitre}>{supplement.label}</Text>
          <Text style={styles.supplementPrix}>
            {supplement.price} {supplement.currency}
          </Text>
          {supplement.description ? (
            <Text style={styles.imprevuTexte}>{supplement.description}</Text>
          ) : null}
          {/* La phrase qui lève l'inquiétude vient AVANT les boutons, pas après. */}
          <Text style={styles.supplementNote}>
            Votre devis initial ne change pas : c’est une ligne séparée.
          </Text>

          <View style={styles.supplementActions}>
            <Button
              label="Accepter"
              onPress={() => repondreAuSupplement(supplement.id, true)}
              loading={repondre.isPending}
            />
            <Button
              label="Refuser"
              variant="secondary"
              onPress={() => repondreAuSupplement(supplement.id, false)}
              loading={repondre.isPending}
            />
          </View>
        </View>
      ))}

      {/*
        MA LISTE — après ce qui attend une réponse, avant ce qui se contemple.
 
        Elle se modifie pendant que le prestataire travaille, et c'est elle qui décide de sa
        clôture : elle a sa place au milieu de l'écran, pas en bas avec les photos.
      */}
      <MissionTodoCard bookingId={bookingId} />

      {(imprevus ?? []).length > 0 && (
        <View style={styles.section}>
          <Text style={styles.sectionTitre}>Imprévus signalés</Text>
          {(imprevus ?? []).map((imprevu) => (
            <View key={imprevu.id} style={styles.imprevu}>
              <Text style={styles.imprevuTitre}>{imprevu.label}</Text>
              <Text style={styles.imprevuTexte}>{imprevu.description}</Text>
            </View>
          ))}
        </View>
      )}

      <ComparateurAvantApres avant={photos?.before ?? []} apres={photos?.after ?? []} />

      <Divider />

      <View style={styles.section}>
        <Text style={styles.sectionTitre}>Déroulé</Text>
        {fil.entries.length === 0 ? (
          <Text style={styles.vide}>Rien à afficher pour l’instant.</Text>
        ) : (
          fil.entries.map((entree) => <LigneDeFil key={entree.key} entree={entree} />)
        )}
      </View>

      {/*
        ANNULER — en dernier, et c'est voulu.

        C'est le geste dont on ne veut pas qu'il soit le premier vu. Il reste atteignable sans
        chercher, mais après tout ce qui peut encore sauver l'intervention : le devis à accepter, la
        liste à corriger, le supplément à valider.
      */}
      <View style={styles.section}>
        {annulationOuverte ? (
          <AnnulerLaMissionSheet
            audience="client"
            bookingId={bookingId}
            onAnnulee={() => setAnnulationOuverte(false)}
            onFermer={() => setAnnulationOuverte(false)}
          />
        ) : (
          <Button
            label="Annuler la mission"
            variant="secondary"
            onPress={() => setAnnulationOuverte(true)}
            fullWidth
            testID="ouvrir-annulation"
          />
        )}
      </View>
    </Screen>
  );
}

/**
 * Avant / après, côte à côte.
 *
 * Les deux bandes défilent SÉPARÉMENT plutôt qu'appariées deux à deux : le prestataire prend
 * rarement le même nombre de clichés des deux côtés, et un appariement forcé afficherait des
 * couples qui ne montrent pas le même endroit.
 */
function ComparateurAvantApres({ avant, apres }: { avant: OnSiteMedia[]; apres: OnSiteMedia[] }) {
  const styles = stylesFor(useThemeColors());

  if (avant.length === 0 && apres.length === 0) {
    return null;
  }

  return (
    <View style={styles.section}>
      <Text style={styles.sectionTitre}>Avant / après</Text>
      <BandeDePhotos legende="Avant" photos={avant} />
      <BandeDePhotos legende="Après" photos={apres} />
    </View>
  );
}

function BandeDePhotos({ legende, photos }: { legende: string; photos: OnSiteMedia[] }) {
  const styles = stylesFor(useThemeColors());

  if (photos.length === 0) {
    return null;
  }

  return (
    <View style={styles.bande}>
      <Text style={styles.bandeTitre}>{legende}</Text>
      <ScrollView horizontal showsHorizontalScrollIndicator={false}>
        {photos.map((photo) => (
          <Image
            key={photo.id}
            source={{ uri: photo.url ?? undefined }}
            style={styles.vignette}
            accessibilityLabel={`${photo.label} de votre intervention`}
          />
        ))}
      </ScrollView>
    </View>
  );
}

function LigneDeFil({ entree }: { entree: OnSiteTimelineEntry }) {
  const styles = stylesFor(useThemeColors());

  return (
    <View style={styles.ligne}>
      <Text style={styles.ligneHeure}>{entree.at ? heure(entree.at) : '—'}</Text>
      <Text style={styles.ligneTexte}>{entree.label}</Text>
    </View>
  );
}

/** Une heure locale, sans la date : le fil se lit dans la journée où il se déroule. */
function heure(iso: string): string {
  const d = new Date(iso);

  if (Number.isNaN(d.getTime())) {
    return '—';
  }

  return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}

/**
 * Un montant EN PROVENANCE DU SERVEUR, mis en forme. Rien n'est calculé ici — cette fonction ne
 * fait que placer une virgule et un symbole sur des centimes reçus tels quels.
 */
function euros(centimes: number): string {
  return new Intl.NumberFormat('fr-BE', { style: 'currency', currency: 'EUR' }).format(centimes / 100);
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  entete: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: spacing.md,
    marginBottom: spacing.sm,
  },
  titre: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
  },
  finEstimee: {
    fontSize: typography.fontSize.sm,
    color: t.textSecondary,
    marginTop: spacing.xs,
  },
  horloge: { marginTop: spacing.md },
  prolongation: {
    marginTop: spacing.sm,
    padding: spacing.md,
    borderRadius: radius.md,
    backgroundColor: t.tint.brand,
    gap: spacing.xs,
  },
  prolongationTitre: {
    fontSize: typography.fontSize.base,
    fontWeight: '600',
    color: t.text,
  },
  prolongationNote: {
    fontSize: typography.fontSize.sm,
    lineHeight: 19,
    color: t.textSecondary,
  },
  prolongationChoix: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.sm,
    marginTop: spacing.xs,
  },
  prolongationOption: {
    fontSize: typography.fontSize.sm,
    fontWeight: '600',
    color: t.text,
    backgroundColor: t.card,
    borderRadius: radius.pill,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    overflow: 'hidden',
  },
  // Pendant l'envoi : on estompe SANS retirer le choix de l'ecran, pour qu'il ne saute pas sous
  // le doigt de quelqu'un qui hesitait entre deux options.
  prolongationOptionInactive: { opacity: 0.5 },
  prolongationRefus: {
    marginTop: spacing.sm,
    fontSize: typography.fontSize.sm,
    lineHeight: 19,
    color: t.textSecondary,
  },
  section: { marginTop: spacing.lg },
  sectionTitre: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
    marginBottom: spacing.sm,
  },
  vide: {
    fontSize: typography.fontSize.sm,
    color: t.textSecondary,
  },
  imprevu: {
    backgroundColor: t.tint.warning,
    borderRadius: radius.md,
    padding: spacing.sm,
    marginBottom: spacing.xs,
  },
  // Le fond « brand » et non « warning » : un supplément proposé n'est pas un incident, c'est une
  // décision à prendre. Le signaler comme un problème ferait refuser par réflexe.
  supplement: {
    backgroundColor: t.tint.brand,
    borderRadius: radius.md,
    padding: spacing.md,
    marginBottom: spacing.sm,
  },
  supplementTitre: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
  },
  supplementPrix: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
  },
  supplementNote: {
    fontSize: typography.fontSize.xs,
    color: t.textMuted,
    marginTop: spacing.xs,
  },
  supplementActions: {
    flexDirection: 'row',
    gap: spacing.sm,
    marginTop: spacing.sm,
  },
  imprevuTitre: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
  },
  imprevuTexte: {
    fontSize: typography.fontSize.xs,
    color: t.textSecondary,
    marginTop: 2,
  },
  bande: { marginBottom: spacing.sm },
  bandeTitre: {
    fontSize: typography.fontSize.xs,
    fontWeight: typography.fontWeight.semibold,
    color: t.textSecondary,
    marginBottom: spacing.xs,
  },
  vignette: {
    width: 104,
    height: 104,
    borderRadius: radius.sm,
    marginRight: spacing.xs,
    backgroundColor: t.border,
  },
  ligne: {
    flexDirection: 'row',
    paddingVertical: spacing.xs,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: t.border,
  },
  ligneHeure: {
    width: 52,
    fontSize: typography.fontSize.xs,
    color: t.textSecondary,
  },
  ligneTexte: {
    flex: 1,
    fontSize: typography.fontSize.sm,
    color: t.text,
  },
});
