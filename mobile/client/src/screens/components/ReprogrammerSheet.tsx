import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { Button } from '@/ui';
import { useReprogrammer } from '@/booking/onsite';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';
import type { ThemeTokens } from '@/theme/useThemeColors';

/**
 * DÉPLACER UN RENDEZ-VOUS, DEPUIS SON DÉTAIL.
 *
 * `BookingRescheduleService` existait et n'était atteignable que du calendrier web et de la carte
 * de retard — donc jamais avant que le prestataire soit en route, c'est-à-dire au moment où l'on
 * déplace le plus naturellement.
 *
 * Les sept jours qui suivent, à la MÊME HEURE : sans sélecteur de date dans la trousse, proposer
 * des jours datés vaut mieux qu'un champ libre où l'on se trompe de format. Le serveur reste seul
 * juge de la disponibilité, et son refus s'affiche tel qu'il l'écrit.
 */
export function ReprogrammerSheet({
  bookingId,
  dateActuelle,
  heureActuelle,
  onReprogrammee,
  onFermer,
}: {
  bookingId: number;
  dateActuelle?: string | null;
  heureActuelle?: string | null;
  onReprogrammee: () => void;
  onFermer: () => void;
}) {
  const { t: tr } = useTraduction();
  const t = useThemeColors();
  const styles = stylesFor(t);

  const [jourChoisi, setJourChoisi] = React.useState<string | null>(null);
  const [refus, setRefus] = React.useState<string | null>(null);
  const reprogrammer = useReprogrammer(bookingId);

  const heure = heureActuelle ? heureActuelle.slice(0, 5) : undefined;
  const jours = React.useMemo(() => prochainsJours(dateActuelle), [dateActuelle]);

  const confirmer = () => {
    if (!jourChoisi) {
      return;
    }

    setRefus(null);
    reprogrammer.mutate(
      heure ? { date: jourChoisi, time: heure } : { date: jourChoisi },
      {
        onSuccess: onReprogrammee,
        // Le serveur sait POURQUOI il refuse — un creneau ferme, un delai trop court.
        onError: (e) => setRefus(e.message || tr('reprogrammation.creneau_refuse')),
      },
    );
  };

  return (
    <View style={styles.carte} testID="feuille-reprogrammation">
      <Text style={styles.titre}>{tr('reprogrammation.choisissez_un_nouveau_jour')}</Text>
      <Text style={styles.aide}>
        {heure
          ? tr('reprogrammation.garde_son_heure', { heure })
          : tr('reprogrammation.heure_a_confirmer')}
      </Text>

      <View style={styles.jours}>
        {jours.map((jour) => (
          <Button
            key={jour.valeur}
            label={jour.libelle}
            variant={jourChoisi === jour.valeur ? 'primary' : 'secondary'}
            onPress={() => {
              setRefus(null);
              setJourChoisi(jour.valeur);
            }}
            testID={`jour-${jour.valeur}`}
          />
        ))}
      </View>

      {refus ? <Text style={styles.refus}>{refus}</Text> : null}

      <Button
        label={tr('reprogrammation.confirmer')}
        onPress={confirmer}
        disabled={!jourChoisi || reprogrammer.isPending}
        loading={reprogrammer.isPending}
        fullWidth
        testID="confirmer-reprogrammation"
      />
      <Button label={tr('reprogrammation.renoncer')} variant="ghost" onPress={onFermer} fullWidth testID="fermer-reprogrammation" />
    </View>
  );
}

/** Les sept jours suivants, sans celui deja retenu. */
function prochainsJours(dateActuelle?: string | null): Array<{ valeur: string; libelle: string }> {
  const debut = new Date();
  debut.setHours(12, 0, 0, 0);

  const jours: Array<{ valeur: string; libelle: string }> = [];

  for (let i = 1; jours.length < 7 && i <= 10; i += 1) {
    const d = new Date(debut.getTime() + i * 24 * 60 * 60 * 1000);
    const valeur = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

    if (valeur === dateActuelle) {
      continue;
    }

    jours.push({
      valeur,
      libelle: d.toLocaleDateString('fr-BE', { weekday: 'short', day: 'numeric', month: 'short' }),
    });
  }

  return jours;
}

const stylesFor = (t: ThemeTokens) =>
  StyleSheet.create({
    carte: {
      backgroundColor: t.card,
      borderRadius: radius.lg,
      padding: spacing.lg,
      gap: spacing.sm,
    },
    titre: {
      fontSize: typography.fontSize.lg,
      fontWeight: typography.fontWeight.bold,
      color: t.text,
    },
    aide: {
      fontSize: typography.fontSize.sm,
      color: t.textSecondary,
      marginBottom: spacing.sm,
    },
    jours: {
      flexDirection: 'row',
      flexWrap: 'wrap',
      gap: spacing.xs,
      marginBottom: spacing.sm,
    },
    refus: {
      fontSize: typography.fontSize.sm,
      color: t.danger,
      marginBottom: spacing.sm,
    },
  });
