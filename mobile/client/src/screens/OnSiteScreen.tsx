import React from 'react';
import { View, Text, StyleSheet, Image, ScrollView } from 'react-native';
import { Screen, Badge, Skeleton, EmptyState, ProgressBar, Divider } from '@/ui';
import {
  useOnSiteTimeline,
  useOnSiteMedia,
  useOnSiteIncidents,
  useLiveOnSite,
} from '@/booking/onsite';
import type { OnSiteMedia, OnSiteTimelineEntry } from '@/booking/onsite';
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

  // Le canal est indexé sur la MISSION : son identifiant vient du fil, le client n'en dispose pas
  // autrement.
  useLiveOnSite(bookingId, fil?.mission_id ?? null);

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
      {fil.estimated_end_at && (
        <Text style={styles.finEstimee}>
          Fin estimée vers {heure(fil.estimated_end_at)}
        </Text>
      )}

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
