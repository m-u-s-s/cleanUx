import React from 'react';
import { View, Text, StyleSheet, Alert } from 'react-native';
import { Button, CarteDeMission } from '@/ui';
import { useRetard, useReprogrammer } from '@/booking/onsite';
import { spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

/**
 * « VOTRE PRESTATAIRE A DU RETARD » — dit par la plateforme, pas découvert par le client.
 *
 * ── L'ATTENTE MUETTE EST LE VRAI PROBLÈME ────────────────────────────────────────────────────
 *
 * Ce n'est pas le retard qui fait annuler, c'est le silence : dix minutes sans nouvelle valent
 * une heure annoncée. Cette carte dit trois choses dans cet ordre — de combien, ce que le
 * prestataire répond, et ce qu'on peut faire.
 *
 * ── LES TROIS ISSUES, ET AUCUNE N'EST INVENTÉE ICI ───────────────────────────────────────────
 *
 * Attendre ne demande rien à personne : la carte disparaît d'elle-même au démarrage. Décaler
 * passe par le service de reprogrammation, qui portait déjà l'autorisation et l'historique.
 * Annuler passe par le questionnaire commun — et le bouton ne promet la gratuité que si le
 * serveur la donne, parce qu'un bouton « sans frais » suivi de frais est pire que pas de bouton.
 */
export function MissionRetardCard({
  bookingId,
  onAnnuler,
}: {
  bookingId: number;
  onAnnuler?: () => void;
}) {
  const { t: tr } = useTraduction();
  const t = useThemeColors();
  const styles = stylesFor(t);

  const { data: retard } = useRetard(bookingId);
  const reprogrammer = useReprogrammer(bookingId);

  if (!retard?.en_retard) {
    return null;
  }

  const decaler = (quand: { date: string; time?: string }) =>
    reprogrammer.mutate(quand, {
      onSuccess: () => Alert.alert('C’est décalé', 'Votre intervention a été replanifiée.'),
      onError: (e: { message?: string }) =>
        Alert.alert('Impossible', e.message ?? 'Le créneau n’a pas pu être déplacé.'),
    });

  const dansDeuxHeures = new Date(Date.now() + 2 * 60 * 60 * 1000);
  const demain = new Date(Date.now() + 24 * 60 * 60 * 1000);
  const jour = (d: Date) => d.toISOString().slice(0, 10);
  const heure = (d: Date) => d.toTimeString().slice(0, 5);

  return (
    <CarteDeMission
      ton="attention"
      titre={`${retard.minutes} min de retard`}
      testID="retard-prestataire"
    >

      {/*
        LA RÉPONSE DU PRESTATAIRE, ou son absence — et l'absence se dit.
        Laisser la ligne vide donnerait l'impression qu'on n'a pas demandé.
      */}
      <Text style={styles.annonce}>
        {retard.annonce?.arrivee_at
          ? `Le prestataire annonce son arrivée vers ${new Date(retard.annonce.arrivee_at)
              .toTimeString()
              .slice(0, 5)}${retard.annonce.motif ? ` — ${retard.annonce.motif}` : ''}.`
          : 'Le prestataire n’a pas encore répondu.'}
      </Text>

      <View style={styles.actions}>
        <Button
          label={tr('mission_retard.plus_tard_aujourdhui')}
          variant="secondary"
          onPress={() => decaler({ date: jour(dansDeuxHeures), time: heure(dansDeuxHeures) })}
          disabled={reprogrammer.isPending}
          testID="retard-decaler-aujourdhui"
        />
        <Button
          label={tr('mission_retard.demain_meme_heure')}
          variant="secondary"
          onPress={() => decaler({ date: jour(demain) })}
          disabled={reprogrammer.isPending}
          testID="retard-decaler-demain"
        />
      </View>

      {onAnnuler ? (
        <Button
          label={retard.annulation_gratuite ? "Annuler sans frais" : "Annuler l’intervention"}
          variant="ghost"
          onPress={onAnnuler}
          testID="retard-annuler"
        />
      ) : null}
    </CarteDeMission>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  annonce: { fontSize: typography.fontSize.sm, lineHeight: 20, color: t.mutedOnGlass },
  actions: { flexDirection: 'row', gap: spacing.sm, flexWrap: 'wrap' },
});
