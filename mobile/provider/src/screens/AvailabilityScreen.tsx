import React, { useMemo, useState } from 'react';
import { View, Text, ScrollView, StyleSheet, Modal, Pressable, Alert, ActivityIndicator } from 'react-native';
import { Screen, Button, Badge, Skeleton, ErrorState } from '@/ui';
import { colors, spacing, typography, radius, shadows } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import {
  useAvailability,
  useCreateSlot,
  useUpdateSlot,
  useDeleteSlot,
  useCloseDay,
  useDeleteException,
  WEEK_ORDER,
  weekdayLabel,
  hhmm,
  formatDate,
} from '@/availability';
import type { AvailabilitySlot } from '@/availability';
import { useTraduction } from '@/i18n';

/**
 * LA SEMAINE TYPE, ET LES JOURS QU'ON FERME.
 *
 * L'écran précédent était en lecture seule et ne montrait rien : il lisait `res.data.data`, une
 * clé absente de la réponse, et `item.day_of_week` quand la colonne s'appelle `weekday`. Il
 * affichait « Aucune disponibilité » à des prestataires qui en avaient.
 *
 * Deux notions distinctes, et elles ne se mélangent pas :
 *   - la SEMAINE TYPE (`availability_slots`), récurrente, « tous les mardis de 8 h à 17 h » ;
 *   - les EXCEPTIONS (`availability_exceptions`), datées, « ce jeudi-là je suis fermé ».
 * Les confondre, c'est ce que fait la page web : son bouton « Bloquer » SUPPRIME les créneaux au
 * lieu de poser une exception, donc ferme tous les mardis à venir pour fermer un mardi.
 *
 * PAS DE SÉLECTEUR D'HEURE NATIF, et c'est un choix : aucun paquet de date n'est installé, et en
 * ajouter un pour choisir entre trente-cinq demi-heures coûterait un module natif, une
 * compilation, et un comportement différent sur les deux plateformes. Des pastilles font le même
 * travail en moins de gestes.
 */
const HEURES = Array.from({ length: 35 }, (_, i) => {
  const minutes = 6 * 60 + i * 30; // 06:00 → 23:00

  return `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
});

type Edition =
  | { mode: 'creation'; weekday: number; start: string; end: string }
  | { mode: 'modification'; id: number; weekday: number; start: string; end: string };

export function AvailabilityScreen() {
  const { t: tr } = useTraduction();
  const t = useThemeColors();
  const styles = stylesFor(t);

  const { data, isLoading, isError, refetch } = useAvailability();
  const creer = useCreateSlot();
  const modifier = useUpdateSlot();
  const supprimer = useDeleteSlot();
  const fermerJour = useCloseDay();
  const rouvrirJour = useDeleteException();

  const [edition, setEdition] = useState<Edition | null>(null);
  const [fermeture, setFermeture] = useState(false);

  const parJour = useMemo(() => {
    const groupes: Record<number, AvailabilitySlot[]> = {};

    for (const slot of data?.slots ?? []) {
      (groupes[slot.weekday] ??= []).push(slot);
    }

    for (const liste of Object.values(groupes)) {
      liste.sort((a, b) => a.start_time.localeCompare(b.start_time));
    }

    return groupes;
  }, [data?.slots]);

  const joursFermes = useMemo(
    () => (data?.exceptions ?? []).filter(e => e.exception_type === 'closed'),
    [data?.exceptions],
  );

  /** Les quatorze prochains jours, pour fermer une date sans sélecteur natif. */
  const prochainsJours = useMemo(() => {
    const base = new Date();

    return Array.from({ length: 14 }, (_, i) => {
      const d = new Date(base);
      d.setDate(base.getDate() + i);

      return d.toISOString().slice(0, 10);
    });
  }, []);

  const enregistrer = () => {
    if (!edition) return;

    if (edition.end <= edition.start) {
      Alert.alert(tr('availability.horaire_impossible'), tr('availability.la_fin_doit_venir_apres'));

      return;
    }

    const payload = { weekday: edition.weekday, start_time: edition.start, end_time: edition.end };
    const apres = { onSuccess: () => setEdition(null) };

    if (edition.mode === 'creation') {
      creer.mutate(payload, apres);
    } else {
      modifier.mutate({ id: edition.id, ...payload }, apres);
    }
  };

  if (isLoading) {
    return (
      <Screen scroll>
        <View style={styles.chargement}>
          <Skeleton width="55%" height={26} />
          {[1, 2, 3, 4].map(i => <Skeleton key={i} width="100%" height={80} />)}
        </View>
      </Screen>
    );
  }

  if (isError) {
    return (
      <Screen>
        <ErrorState message="Impossible de charger vos disponibilités." onRetry={() => refetch()} />
      </Screen>
    );
  }

  return (
    <Screen scroll>
      <Text style={styles.titre} accessibilityRole="header">{tr('availability.mes_disponibilites')}</Text>
      <Text style={styles.sousTitre}>
        Vous recevez des missions pendant ces plages. Fermer un jour précis se fait plus bas, sans
        toucher à votre semaine.
      </Text>

      <Text style={styles.sectionTitre}>{tr('availability.semaine_type')}</Text>

      {WEEK_ORDER.map(jour => {
        const creneaux = parJour[jour] ?? [];

        return (
          <View key={jour} style={styles.carteJour} testID={`jour-${jour}`}>
            <View style={styles.enTeteJour}>
              <Text style={styles.nomJour}>{weekdayLabel(jour)}</Text>
              {creneaux.length === 0 && <Badge label={tr('availability.ferme')} variant="neutral" />}
            </View>

            {creneaux.map(slot => (
              <View key={slot.id} style={styles.ligneCreneau}>
                <Text style={styles.heures}>{hhmm(slot.start_time)} — {hhmm(slot.end_time)}</Text>

                <View style={styles.actionsCreneau}>
                  <Button
                    label={tr('availability.modifier')}
                    size="sm"
                    variant="ghost"
                    testID={`modifier-${slot.id}`}
                    onPress={() => setEdition({
                      mode: 'modification',
                      id: slot.id,
                      weekday: slot.weekday,
                      start: hhmm(slot.start_time),
                      end: hhmm(slot.end_time),
                    })}
                  />
                  <Button
                    label={tr('availability.retirer')}
                    size="sm"
                    variant="ghost"
                    testID={`supprimer-${slot.id}`}
                    onPress={() => Alert.alert(
                      tr('availability.retirer_ce_creneau'),
                      `${weekdayLabel(slot.weekday)} ${hhmm(slot.start_time)} — ${hhmm(slot.end_time)}, toutes les semaines.`,
                      [
                        { text: 'Annuler', style: 'cancel' },
                        { text: 'Retirer', style: 'destructive', onPress: () => supprimer.mutate(slot.id) },
                      ],
                    )}
                  />
                </View>
              </View>
            ))}

            <Button
              label={tr('availability.ajouter_un_creneau')}
              size="sm"
              variant="secondary"
              testID={`ajouter-${jour}`}
              onPress={() => setEdition({ mode: 'creation', weekday: jour, start: '08:00', end: '17:00' })}
            />
          </View>
        );
      })}

      <Text style={styles.sectionTitre}>{tr('availability.jours_fermes')}</Text>
      <Text style={styles.sousTitre}>
        {tr('availability.une_date_fermee_lemporte_sur')}
      </Text>

      {joursFermes.length === 0 ? (
        <Text style={styles.vide}>{tr('availability.aucun_jour_ferme')}</Text>
      ) : (
        joursFermes.map(exception => (
          <View key={exception.id} style={styles.ligneException}>
            <View style={styles.min0}>
              <Text style={styles.dateException}>{formatDate(exception.date)}</Text>
              {!!exception.reason && <Text style={styles.motif}>{exception.reason}</Text>}
            </View>
            <Button
              label={tr('availability.rouvrir')}
              size="sm"
              variant="ghost"
              testID={`rouvrir-${exception.id}`}
              onPress={() => rouvrirJour.mutate(exception.id)}
            />
          </View>
        ))
      )}

      <Button
        label={tr('availability.fermer_une_date')}
        variant="secondary"
        fullWidth
        testID="ouvrir-fermeture"
        onPress={() => setFermeture(true)}
      />

      {/* ── Édition d'un créneau ────────────────────────────────────────────── */}
      <Modal visible={edition !== null} animationType="slide" transparent onRequestClose={() => setEdition(null)}>
        <View style={styles.fondModale}>
          <View style={styles.modale}>
            <Text style={styles.titreModale}>
              {edition?.mode === 'creation' ? tr('availability.nouveau_creneau') : tr('availability.modifier_le_creneau')}
              {edition ? ` — ${weekdayLabel(edition.weekday)}` : ''}
            </Text>

            <Text style={styles.libelleChamp}>{tr('availability.debut')}</Text>
            <ChoixHeure
              valeur={edition?.start ?? ''}
              onChoisir={h => setEdition(e => (e ? { ...e, start: h } : e))}
              styles={styles}
              prefixeTest="debut"
            />

            <Text style={styles.libelleChamp}>Fin</Text>
            <ChoixHeure
              valeur={edition?.end ?? ''}
              onChoisir={h => setEdition(e => (e ? { ...e, end: h } : e))}
              styles={styles}
              prefixeTest="fin"
            />

            <View style={styles.actionsModale}>
              <Button label={tr('availability.annuler')} variant="ghost" onPress={() => setEdition(null)} />
              <Button
                label={tr('availability.enregistrer')}
                testID="enregistrer-creneau"
                loading={creer.isPending || modifier.isPending}
                onPress={enregistrer}
              />
            </View>
          </View>
        </View>
      </Modal>

      {/* ── Fermeture d'une date ────────────────────────────────────────────── */}
      <Modal visible={fermeture} animationType="slide" transparent onRequestClose={() => setFermeture(false)}>
        <View style={styles.fondModale}>
          <View style={styles.modale}>
            <Text style={styles.titreModale}>{tr('availability.fermer_une_date')}</Text>
            <Text style={styles.sousTitre}>{tr('availability.vos_creneaux_recurrents_restent_intacts')}</Text>

            <ScrollView style={styles.listeDates}>
              {prochainsJours.map(date => (
                <Pressable
                  key={date}
                  style={styles.ligneDate}
                  testID={`fermer-${date}`}
                  accessibilityRole="button"
                  onPress={() => fermerJour.mutate({ date }, { onSuccess: () => setFermeture(false) })}
                >
                  <Text style={styles.texteDate}>{formatDate(date)}</Text>
                </Pressable>
              ))}
            </ScrollView>

            {fermerJour.isPending && <ActivityIndicator color={colors.brand[500]} />}

            <View style={styles.actionsModale}>
              <Button label={tr('availability.fermer_la_fenetre')} variant="ghost" onPress={() => setFermeture(false)} />
            </View>
          </View>
        </View>
      </Modal>
    </Screen>
  );
}

function ChoixHeure({
  valeur,
  onChoisir,
  styles,
  prefixeTest,
}: {
  valeur: string;
  onChoisir: (heure: string) => void;
  styles: ReturnType<typeof stylesFor>;
  prefixeTest: string;
}) {
  return (
    <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.rangeeHeures}>
      {HEURES.map(heure => (
        <Pressable
          key={heure}
          onPress={() => onChoisir(heure)}
          accessibilityRole="button"
          accessibilityState={{ selected: heure === valeur }}
          testID={`${prefixeTest}-${heure}`}
          style={[styles.pastilleHeure, heure === valeur && styles.pastilleHeureActive]}
        >
          <Text style={[styles.texteHeure, heure === valeur && styles.texteHeureActive]}>{heure}</Text>
        </Pressable>
      ))}
    </ScrollView>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  chargement: { gap: spacing.md },
  titre: {
    fontSize: typography.fontSize.xl,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
  },
  sousTitre: {
    fontSize: typography.fontSize.xs,
    color: t.textSecondary,
    marginTop: spacing.xs,
    marginBottom: spacing.md,
    lineHeight: 18,
  },
  sectionTitre: {
    fontSize: typography.fontSize.xs,
    fontWeight: typography.fontWeight.bold,
    color: t.textSecondary,
    textTransform: 'uppercase',
    letterSpacing: 0.6,
    marginTop: spacing.lg,
    marginBottom: spacing.sm,
  },
  carteJour: {
    padding: spacing.md,
    borderRadius: radius.md,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: t.border,
    backgroundColor: t.card,
    marginBottom: spacing.sm,
    gap: spacing.xs,
    ...shadows.xs,
  },
  enTeteJour: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  nomJour: {
    fontSize: typography.fontSize.sm,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
  },
  ligneCreneau: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: spacing.sm,
    paddingVertical: spacing.xs,
  },
  heures: { fontSize: typography.fontSize.sm, color: t.text, flexShrink: 1 },
  actionsCreneau: { flexDirection: 'row', gap: spacing.xs },
  vide: { fontSize: typography.fontSize.sm, color: t.textMuted, marginBottom: spacing.sm },
  ligneException: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: spacing.sm,
    padding: spacing.md,
    borderRadius: radius.md,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: t.border,
    backgroundColor: t.card,
    marginBottom: spacing.sm,
  },
  min0: { flexShrink: 1 },
  dateException: { fontSize: typography.fontSize.sm, color: t.text },
  motif: { fontSize: typography.fontSize.xs, color: t.textSecondary, marginTop: 2 },
  fondModale: {
    flex: 1,
    justifyContent: 'flex-end',
    backgroundColor: 'rgba(2, 6, 23, 0.55)',
  },
  modale: {
    backgroundColor: t.cardElevated,
    borderTopLeftRadius: radius.lg,
    borderTopRightRadius: radius.lg,
    padding: spacing.lg,
    gap: spacing.xs,
    maxHeight: '80%',
  },
  titreModale: {
    fontSize: typography.fontSize.base,
    fontWeight: typography.fontWeight.bold,
    color: t.text,
    marginBottom: spacing.xs,
  },
  libelleChamp: {
    fontSize: typography.fontSize.xs,
    color: t.textSecondary,
    marginTop: spacing.sm,
  },
  rangeeHeures: { flexGrow: 0 },
  pastilleHeure: {
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs,
    borderRadius: radius.pill,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: t.border,
    marginRight: spacing.xs,
  },
  pastilleHeureActive: { backgroundColor: colors.brand[600], borderColor: colors.brand[600] },
  texteHeure: { fontSize: typography.fontSize.sm, color: t.text },
  texteHeureActive: { color: t.textOnBrand, fontWeight: typography.fontWeight.semibold },
  actionsModale: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    gap: spacing.sm,
    marginTop: spacing.md,
  },
  listeDates: { marginTop: spacing.sm },
  ligneDate: {
    paddingVertical: spacing.sm,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: t.border,
  },
  texteDate: { fontSize: typography.fontSize.sm, color: t.text },
});
