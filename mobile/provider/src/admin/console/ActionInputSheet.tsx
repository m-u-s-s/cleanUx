import React, { useState } from 'react';
import { Modal, ScrollView, StyleSheet, Text, View } from 'react-native';
import { Button } from '@/ui';
import { colors, radius, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { FieldInput } from './FieldInput';
import type { ResourceAction } from './types';

/**
 * La feuille de saisie d'une action qui exige des valeurs.
 *
 * POURQUOI ELLE EXISTE. Tous les refus de la plateforme — litige, KYC, KYB, approbation
 * d'entreprise — demandent un motif écrit. Écrire un écran par file aurait produit quatre fois
 * la même feuille, avec quatre fois l'occasion d'oublier la validation. L'action DÉCLARE ce
 * qu'elle exige ; cette feuille le demande, quelle que soit la file.
 *
 * LE TEXTE DE CONFIRMATION EST AFFICHÉ AU-DESSUS DE LA SAISIE, pas dans une alerte préalable :
 * on lit ce qu'on s'apprête à faire pendant qu'on écrit le motif, plutôt que de valider une
 * boîte de dialogue puis de l'oublier.
 *
 * LES ERREURS VIENNENT DU SERVEUR. Le mobile ne connaît que le type et le caractère obligatoire —
 * assez pour dessiner, pas assez pour juger. Un bouton grisé tant que le champ est vide donnerait
 * l'illusion d'une validation locale ; on laisse envoyer, et le serveur répond.
 */
export function ActionInputSheet({
  action,
  visible,
  submitting,
  errors,
  onCancel,
  onSubmit,
}: {
  action: ResourceAction | null;
  visible: boolean;
  submitting: boolean;
  errors: Record<string, string>;
  onCancel: () => void;
  onSubmit: (values: Record<string, unknown>) => void;
}) {
  const styles = stylesFor(useThemeColors());

  const [valeurs, setValeurs] = useState<Record<string, unknown>>({});

  if (!action) {
    return null;
  }

  return (
    <Modal
      visible={visible}
      transparent
      animationType="slide"
      onRequestClose={onCancel}
      testID="action-input-sheet"
    >
      <View style={styles.backdrop}>
        <View style={styles.sheet}>
          <ScrollView keyboardShouldPersistTaps="handled">
            <Text style={styles.title}>{action.label}</Text>

            {action.confirm ? <Text style={styles.confirm}>{action.confirm}</Text> : null}

            {action.fields.map((field) => (
              <FieldInput
                key={field.key}
                field={field}
                value={valeurs[field.key]}
                error={errors[field.key]}
                onChange={(value) => setValeurs((v) => ({ ...v, [field.key]: value }))}
              />
            ))}

            <View style={styles.actions}>
              {/*
                Le bouton d'envoi porte son propre testID : le libellé de l'action apparaît aussi
                sur le bouton du détail qui a ouvert la feuille, et cibler par texte reviendrait à
                parier sur un ordre de rendu.
              */}
              <View testID="action-input-submit">
                <Button
                  label={action.label}
                  variant={action.destructive ? 'danger' : 'primary'}
                  loading={submitting}
                  fullWidth
                  onPress={() => onSubmit(valeurs)}
                />
              </View>
              <Button
                label="Annuler"
                variant="ghost"
                fullWidth
                onPress={() => {
                  setValeurs({});
                  onCancel();
                }}
              />
            </View>
          </ScrollView>
        </View>
      </View>
    </Modal>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  backdrop: { flex: 1, justifyContent: 'flex-end', backgroundColor: 'rgba(0,0,0,0.5)' },
  sheet: {
    backgroundColor: t.bg,
    borderTopLeftRadius: radius.lg,
    borderTopRightRadius: radius.lg,
    padding: spacing.lg,
    maxHeight: '85%',
  },
  title: { ...typography.preset.headline, color: t.text, marginBottom: spacing.sm },
  confirm: {
    fontSize: typography.fontSize.sm,
    color: t.danger,
    marginBottom: spacing.md,
    lineHeight: 20,
  },
  actions: { gap: spacing.sm, paddingTop: spacing.md },
});
