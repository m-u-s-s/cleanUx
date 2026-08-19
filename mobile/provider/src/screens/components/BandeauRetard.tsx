import React, { useState } from 'react';
import { View, Text, StyleSheet, Alert } from 'react-native';
import { Button, TextInput } from '@/ui';
import { useMonRetard, useAnnoncerMonRetard } from '@/missions';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

/**
 * « VOUS AVEZ 22 MIN DE RETARD — ET LE CLIENT LE SAIT DEPUIS 14 H 12 ».
 *
 * ── CE QUE LE PRESTATAIRE IGNORE, CE N'EST PAS L'HEURE ───────────────────────────────────────
 *
 * Il a une montre. Ce qu'il ignore, c'est que la plateforme a déjà prévenu son client — et qu'il
 * arrivera donc en s'excusant d'un retard dont l'autre parlait depuis vingt minutes. Ce bandeau
 * lui dit exactement cela, et lui donne le seul geste qui change la suite.
 *
 * ── TROIS BOUTONS, PAS UN FORMULAIRE ─────────────────────────────────────────────────────────
 *
 * On conduit. Un sélecteur d'heure sur la route ne se remplit pas : trois durées et un motif
 * facultatif se touchent à un feu rouge. Le motif reste court par construction — le serveur le
 * tronque à 180 caractères, on veut « embouteillage », pas un récit.
 */
export function BandeauRetard({ missionId }: { missionId: number }) {
  const t = useThemeColors();
  const styles = stylesFor(t);

  const { data: retard } = useMonRetard(missionId);
  const annoncer = useAnnoncerMonRetard(missionId);
  const [motif, setMotif] = useState('');

  if (!retard?.en_retard) {
    return null;
  }

  const envoyer = (minutes: number) =>
    annoncer.mutate(
      { minutes, reason: motif.trim() === '' ? undefined : motif.trim() },
      {
        onSuccess: () => Alert.alert('Client prévenu', `Votre arrivée est annoncée dans ${minutes} min.`),
        onError: (e: { message?: string }) =>
          Alert.alert('Impossible', e.message ?? 'L’annonce n’est pas partie.'),
      },
    );

  const heureAnnoncee = retard.annonce?.arrivee_at
    ? new Date(retard.annonce.arrivee_at).toTimeString().slice(0, 5)
    : null;

  return (
    <View style={styles.bandeau} testID="bandeau-retard">
      <Text style={styles.titre}>{retard.minutes} min de retard</Text>

      {/*
        CE QUE LE CLIENT SAIT DÉJÀ. C'est l'information qui manque vraiment : elle change la
        façon dont on arrive.
      */}
      <Text style={styles.etat}>
        {retard.prevenu_at
          ? `Le client a été prévenu à ${new Date(retard.prevenu_at).toTimeString().slice(0, 5)}.`
          : 'Le client n’a pas encore été prévenu.'}
        {retard.annulation_gratuite ? ' Il peut annuler sans frais.' : ''}
      </Text>

      {heureAnnoncee ? (
        <Text style={styles.etat} testID="retard-deja-annonce">
          Vous avez annoncé votre arrivée vers {heureAnnoncee}.
        </Text>
      ) : null}

      <TextInput
        label="Motif (facultatif)"
        value={motif}
        onChangeText={setMotif}
        placeholder="Embouteillage, chantier précédent…"
        testID="retard-motif"
      />

      <View style={styles.boutons}>
        {[10, 20, 30].map((minutes) => (
          <Button
            key={minutes}
            label={`+${minutes} min`}
            variant="secondary"
            onPress={() => envoyer(minutes)}
            disabled={annoncer.isPending}
            testID={`retard-annoncer-${minutes}`}
          />
        ))}
      </View>
    </View>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  bandeau: {
    gap: spacing.sm,
    padding: spacing.md,
    borderRadius: radius.md,
    backgroundColor: t.tint.warning,
  },
  titre: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.semibold,
    color: t.text,
  },
  etat: { fontSize: typography.fontSize.sm, lineHeight: 20, color: t.textSecondary },
  boutons: { flexDirection: 'row', gap: spacing.sm, flexWrap: 'wrap' },
});
