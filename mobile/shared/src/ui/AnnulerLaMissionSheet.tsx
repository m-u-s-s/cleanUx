import React, { useState } from 'react';
import { View, Text, StyleSheet, Alert, TouchableOpacity } from 'react-native';
import { Button } from './Button';
import { TextInput } from './TextInput';
import {
  useQuestionnaireDAnnulation,
  useDevisDAnnulation,
  useAnnulerLaReservation,
} from '../cancellation';
import type { AudienceAnnulation, OptionDAnnulation } from '../cancellation';
import { spacing, typography, radius } from '../theme';
import { useThemeColors } from '../theme/useThemeColors';
import type { ThemeTokens } from '../theme/useThemeColors';

/**
 * ANNULER, SUR MOBILE — le même questionnaire que le web, la même règle.
 *
 * ── L'AIGUILLAGE SE DIT AVANT LE GESTE ───────────────────────────────────────────────────────
 *
 * Certaines réponses ne mènent PAS à une annulation : « le travail ne correspond pas » renvoie vers
 * le nouveau devis, « le chantier est trop gros » vers le renfort, « le client ne répond pas » vers
 * le no-show — qui porte une indemnité qu'une annulation ferait perdre. On le montre à l'instant où
 * la personne s'apprête à faire le mauvais geste ; après, elle a déjà annulé. Aucun bouton de
 * confirmation n'apparaît alors : la seule issue offerte est la bonne.
 *
 * ── LE MONTANT VIENT DU SERVEUR, ET IL DÉPEND DU MOTIF ───────────────────────────────────────
 *
 * Un motif exempté met les frais à zéro ; son plafond par personne peut les ramener au palier
 * normal. Afficher un montant sans motif ferait mentir la confirmation.
 */
export function AnnulerLaMissionSheet({
  audience,
  bookingId,
  onAnnulee,
  onFermer,
}: {
  audience: AudienceAnnulation;
  bookingId: number;
  onAnnulee: () => void;
  onFermer: () => void;
}) {
  const t = useThemeColors();
  const styles = stylesFor(t);

  const [choisie, setChoisie] = useState<OptionDAnnulation | null>(null);
  const [precision, setPrecision] = useState('');

  const { data: questions } = useQuestionnaireDAnnulation(audience, bookingId);
  const { data: devis } = useDevisDAnnulation(
    audience,
    bookingId,
    choisie && !choisie.redirects ? choisie.code : null,
  );
  const annuler = useAnnulerLaReservation(audience, bookingId);

  const confirmer = () => {
    if (!choisie || choisie.redirects) {
      return;
    }

    if (choisie.requires_text && precision.trim() === '') {
      Alert.alert('Incomplet', 'Dites en une phrase ce qui se passe.');

      return;
    }

    annuler.mutate(
      { reasonCode: choisie.code, reasonText: precision.trim() || choisie.label },
      {
        onSuccess: onAnnulee,
        onError: (e: { message?: string }) =>
          Alert.alert('Impossible', e.message ?? 'L’annulation n’a pas pu être enregistrée.'),
      },
    );
  };

  return (
    <View style={styles.corps} testID="annuler-la-mission">
      <View style={styles.entete}>
        <Text style={styles.titre} accessibilityRole="header">Annuler la mission</Text>
        <Text onPress={onFermer} style={styles.fermer} accessibilityRole="button">Fermer</Text>
      </View>

      {(questions ?? []).map((question) => (
        <View key={question.code} style={styles.question}>
          <Text style={styles.questionLabel}>{question.label}</Text>
          {question.help_text ? <Text style={styles.aide}>{question.help_text}</Text> : null}

          {question.options.map((option) => {
            const active = choisie?.code === option.code;

            return (
              <TouchableOpacity
                key={option.code}
                onPress={() => setChoisie(option)}
                accessibilityRole="radio"
                accessibilityState={{ selected: active }}
                style={[styles.option, active && styles.optionActive]}
                testID={`motif-${option.code}`}
              >
                <Text style={styles.optionLabel}>{option.label}</Text>
              </TouchableOpacity>
            );
          })}
        </View>
      ))}

      {choisie?.redirects ? (
        <View style={styles.aiguillage} testID="aiguillage">
          <Text style={styles.aiguillageTexte}>{messageDAiguillage(choisie.outcome)}</Text>
        </View>
      ) : null}

      {choisie && !choisie.redirects ? (
        <>
          {choisie.requires_text ? (
            <TextInput
              label="Précisez"
              value={precision}
              onChangeText={setPrecision}
              multiline
              testID="annuler-precision"
            />
          ) : null}

          {devis ? (
            <View style={styles.devis} testID="devis-annulation">
              {devis.fee_amount_cents === 0 ? (
                <Text style={styles.devisGratuit}>Aucun frais d’annulation.</Text>
              ) : (
                <Text style={styles.devisTexte}>
                  Frais d’annulation : {montant(devis.fee_amount_cents, devis.currency)}
                </Text>
              )}
              {devis.exempt_applied ? (
                <Text style={styles.devisNote}>Motif exonérant appliqué.</Text>
              ) : null}
            </View>
          ) : null}

          <Button
            label="Confirmer l’annulation"
            variant="danger"
            onPress={confirmer}
            loading={annuler.isPending}
            fullWidth
            testID="confirmer-annulation"
          />
        </>
      ) : null}
    </View>
  );
}

/** Ce que l'aiguillage propose à la place — dit en clair, jamais en code. */
function messageDAiguillage(issue: string): string {
  switch (issue) {
    case 'redirect_requote':
      return 'Ce n’est pas une annulation : proposez un nouveau devis depuis votre page terrain. Le client accepte ou refuse, et vous gardez la mission.';
    case 'redirect_reinforcement':
      return 'Ce n’est pas une annulation : demandez du renfort. Un collègue vient, et l’intervention se fait.';
    case 'redirect_noshow':
      return 'Ce n’est pas une annulation : déclarez l’absence du client depuis votre page terrain, une fois le délai écoulé. Vous y gagnez l’indemnité qu’une annulation vous ferait perdre.';
    default:
      return 'Cette réponse demande un examen : notre équipe vous répond.';
  }
}

/** Un montant EN PROVENANCE DU SERVEUR, mis en forme. Rien n'est calculé ici. */
function montant(centimes: number, devise: string): string {
  return new Intl.NumberFormat('fr-BE', { style: 'currency', currency: devise || 'EUR' }).format(
    centimes / 100,
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  corps: { gap: spacing.md, paddingHorizontal: spacing.md, paddingBottom: spacing.lg },
  entete: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  titre: { fontSize: typography.fontSize.lg, fontWeight: '700', color: t.text },
  fermer: { fontSize: typography.fontSize.sm, color: t.textSecondary },
  question: { gap: spacing.xs },
  questionLabel: { fontSize: typography.fontSize.sm, fontWeight: '600', color: t.text },
  aide: { fontSize: typography.fontSize.xs, color: t.textSecondary },
  // La ligne entière est la cible tactile, bien au-delà des 44 pt recommandés.
  option: {
    borderRadius: radius.md,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: t.border,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    minHeight: 48,
    justifyContent: 'center',
  },
  optionActive: { borderColor: t.text, backgroundColor: t.tint.brand },
  optionLabel: { fontSize: typography.fontSize.sm, color: t.text },
  aiguillage: {
    backgroundColor: t.tint.brand,
    borderRadius: radius.md,
    padding: spacing.md,
  },
  aiguillageTexte: { fontSize: typography.fontSize.sm, lineHeight: 19, color: t.text },
  devis: { backgroundColor: t.card, borderRadius: radius.md, padding: spacing.md, gap: 2 },
  devisTexte: { fontSize: typography.fontSize.sm, fontWeight: '600', color: t.text },
  devisGratuit: { fontSize: typography.fontSize.sm, fontWeight: '600', color: t.textSecondary },
  devisNote: { fontSize: typography.fontSize.xs, color: t.textSecondary },
});
