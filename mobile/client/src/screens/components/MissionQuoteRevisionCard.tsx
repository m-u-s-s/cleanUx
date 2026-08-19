import React, { useState } from 'react';
import { View, Text, StyleSheet, Alert } from 'react-native';
import { Button, CarteDeMission } from '@/ui';
import { useRevisionDeDevis, useRepondreALaRevision } from '@/booking/onsite';
import type { QuoteRevision } from '@/booking/onsite';
import { spacing, typography, radius } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

/**
 * LE NOUVEAU DEVIS, VU DU SALON.
 *
 * ── LES DEUX TOTAUX, ET LA REMISE QUI SURVIT ─────────────────────────────────────────────────
 *
 * Un chiffre plus élevé sans explication se refuse par réflexe. On montre donc l'ancien, le
 * nouveau, et le nom du code de réduction quand il y en a un — parce que le client se demandera
 * d'abord où est passée sa remise. Le serveur l'a réappliquée ; encore faut-il le dire.
 *
 * ── LE REFUS OUVRE UNE QUESTION, IL NE TRANCHE PAS ───────────────────────────────────────────
 *
 * « Continuez au prix d'origine » et « arrêtez tout » n'ont pas le même coût pour le client, et ce
 * n'est pas à l'application de choisir. Le second geste annule l'intervention — gratuitement les
 * deux premières fois — et le dit avant d'être pressé.
 */
export function MissionQuoteRevisionCard({ bookingId }: { bookingId: number }) {
  const t = useThemeColors();
  const styles = stylesFor(t);

  const { data: revision } = useRevisionDeDevis(bookingId);
  const repondre = useRepondreALaRevision(bookingId);
  const [choixOuvert, setChoixOuvert] = useState(false);

  if (!revision || !revision.awaiting_client) {
    return null;
  }

  const envoyer = (accepte: boolean, decision?: 'continue' | 'stop') =>
    repondre.mutate(
      { revisionId: revision.id, accepte, decision },
      {
        onSuccess: () => setChoixOuvert(false),
        // LE MOTIF DU SERVEUR, TEL QUEL : « le complément n'a pas pu être autorisé » dit au client
        // que sa carte a refusé, là où un « erreur » le ferait réessayer à l'identique.
        onError: (e: { message?: string }) =>
          Alert.alert('Impossible', e.message ?? 'Votre réponse n’a pas pu être enregistrée.'),
      },
    );

  return (
    <CarteDeMission ton="decision" titre="Nouveau devis proposé" testID="revision-de-devis">

      <View style={styles.montants}>
        <View>
          <Text style={styles.libelle}>Devis d’origine</Text>
          <Text style={styles.ancien}>{montant(revision.original_total, revision.currency)}</Text>
        </View>
        <View>
          <Text style={styles.libelle}>Nouveau devis</Text>
          <Text style={styles.nouveau} testID="revision-nouveau-total">
            {montant(revision.revised_total, revision.currency)}
          </Text>
        </View>
      </View>

      <RemiseAppliquee breakdown={revision.breakdown} styles={styles} />

      <Text style={styles.motif}>{revision.reason_text}</Text>

      {choixOuvert ? (
        <View style={styles.choix} testID="revision-choix">
          <Text style={styles.choixTitre}>Que voulez-vous faire ?</Text>
          <Text style={styles.choixNote}>
            Arrêter met fin à l’intervention. Le prestataire n’a pas commencé : vous ne payez rien.
          </Text>

          <Button
            label="Continuez au prix d’origine"
            onPress={() => envoyer(false, 'continue')}
            loading={repondre.isPending}
            fullWidth
            testID="revision-continuer"
          />
          <Button
            label="Arrêtez l’intervention"
            variant="danger"
            onPress={() => envoyer(false, 'stop')}
            loading={repondre.isPending}
            fullWidth
            testID="revision-arreter"
          />
        </View>
      ) : (
        <View style={styles.actions}>
          <Button
            label="Accepter"
            onPress={() => envoyer(true)}
            loading={repondre.isPending}
            testID="revision-accepter"
          />
          <Button
            label="Refuser"
            variant="secondary"
            onPress={() => setChoixOuvert(true)}
            testID="revision-refuser"
          />
        </View>
      )}
    </CarteDeMission>
  );
}

/**
 * LA REMISE, NOMMÉE.
 *
 * Le client cherche d'abord ce qu'est devenu son code promo. Le taire ferait croire qu'il a été
 * avalé par la révision, et c'est un refus assuré.
 */
function RemiseAppliquee({
  breakdown,
  styles,
}: {
  breakdown: QuoteRevision['breakdown'];
  styles: ReturnType<typeof stylesFor>;
}) {
  const promo = (breakdown as { promo?: { code?: string; discount_cents?: number } } | null)?.promo;

  if (!promo?.code) {
    return null;
  }

  return (
    <Text style={styles.remise} testID="revision-remise">
      Votre code {promo.code} reste appliqué
      {typeof promo.discount_cents === 'number' ? ` — ${(promo.discount_cents / 100).toFixed(2)} € de remise` : ''}.
    </Text>
  );
}

/** Un montant EN PROVENANCE DU SERVEUR, mis en forme. Rien n'est calculé ici. */
function montant(valeur: number, devise: string): string {
  return new Intl.NumberFormat('fr-BE', { style: 'currency', currency: devise || 'EUR' }).format(valeur);
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  // Ton « decision » et non « attention » : une révision n'est pas un incident, c'est une décision
  // à prendre. La signaler comme un problème ferait refuser par réflexe.
  montants: { flexDirection: 'row', justifyContent: 'space-between', gap: spacing.md },
  libelle: { fontSize: typography.fontSize.xs, color: t.textSecondary },
  ancien: {
    fontSize: typography.fontSize.base,
    color: t.textSecondary,
    textDecorationLine: 'line-through',
  },
  nouveau: { fontSize: typography.fontSize.xl, fontWeight: '700', color: t.text },
  remise: { fontSize: typography.fontSize.xs, color: t.textSecondary },
  motif: { fontSize: typography.fontSize.sm, color: t.text, lineHeight: 19 },
  actions: { flexDirection: 'row', gap: spacing.sm, marginTop: spacing.xs },
  choix: { gap: spacing.sm, marginTop: spacing.xs },
  choixTitre: { fontSize: typography.fontSize.sm, fontWeight: '600', color: t.text },
  choixNote: { fontSize: typography.fontSize.xs, color: t.textSecondary, lineHeight: 17 },
});
