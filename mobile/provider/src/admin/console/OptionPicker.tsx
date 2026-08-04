import React, { useState } from 'react';
import { Modal, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { Icon } from '@/ui';
import { radius, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { FilterOption } from './types';

/**
 * Le SEUL endroit qui sait présenter une liste de choix.
 *
 * Il sert le champ « select » d'un formulaire ET le filtre « select » d'une liste : ce sont les
 * mêmes options, servies par le même descripteur, et deux rendus auraient fini par diverger — l'un
 * traitant l'effacement, l'autre non.
 *
 * IL AFFICHE LE LIBELLÉ ET REND LA VALEUR. Le serveur valide avec `in:bronze,silver,gold` ; envoyer
 * ce que l'administrateur LIT — « Or » — échouerait en 422 sur une liste qu'on vient pourtant de
 * lui proposer. La distinction est la raison d'être de ce composant.
 *
 * L'OPTION VIDE EXISTE quand elle est permise. Un filtre posé par erreur, sans retour en arrière,
 * oblige à quitter l'écran pour l'annuler.
 */
export function OptionPicker({
  label,
  accessibilityLabel,
  options,
  value,
  onChange,
  effacable = false,
  libelleVide = 'Tous',
}: {
  label: string;
  /**
   * Le nom ANNONCÉ, quand il diffère du nom affiché.
   *
   * Un champ obligatoire s'affiche « Palier * » ; l'astérisque est une convention visuelle, et la
   * lecture vocale n'a pas à l'épeler. Le reste de la console fait déjà cette distinction.
   */
  accessibilityLabel?: string;
  options: FilterOption[];
  value: string | null | undefined;
  onChange: (value: string | null) => void;
  /** Autoriser le retour à « aucun choix ». Vrai pour un filtre, faux pour un champ obligatoire. */
  effacable?: boolean;
  libelleVide?: string;
}) {
  const styles = stylesFor(useThemeColors());
  const [ouvert, setOuvert] = useState(false);

  const choisie = options.find((o) => String(o.value) === String(value ?? ''));

  return (
    <View style={styles.bloc}>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={accessibilityLabel ?? label}
        onPress={() => setOuvert(true)}
        style={({ pressed }) => [styles.declencheur, pressed && styles.presse]}
      >
        <View>
          <Text style={styles.etiquette}>{label}</Text>
          <Text style={styles.valeur}>{choisie ? choisie.label : libelleVide}</Text>
        </View>

        <Icon name="chevron-down" size={16} color={styles.chevron.color} />
      </Pressable>

      <Modal visible={ouvert} transparent animationType="fade" onRequestClose={() => setOuvert(false)}>
        <Pressable style={styles.fond} onPress={() => setOuvert(false)}>
          <Pressable style={styles.feuille} onPress={(e) => e.stopPropagation()}>
            <Text style={styles.titre}>{label}</Text>

            <ScrollView showsVerticalScrollIndicator={false}>
              {effacable ? (
                <Pressable
                  accessibilityRole="button"
                  onPress={() => {
                    onChange(null);
                    setOuvert(false);
                  }}
                  style={styles.ligne}
                >
                  <Text style={styles.ligneTexte}>{libelleVide}</Text>
                </Pressable>
              ) : null}

              {options.map((option) => (
                <Pressable
                  key={String(option.value)}
                  accessibilityRole="button"
                  onPress={() => {
                    onChange(String(option.value));
                    setOuvert(false);
                  }}
                  style={styles.ligne}
                >
                  <Text style={styles.ligneTexte}>{option.label}</Text>

                  {choisie && String(choisie.value) === String(option.value) ? (
                    <Icon name="checkmark" size={16} color={styles.chevron.color} />
                  ) : null}
                </Pressable>
              ))}
            </ScrollView>
          </Pressable>
        </Pressable>
      </Modal>
    </View>
  );
}

const stylesFor = (t: ThemeTokens) =>
  StyleSheet.create({
    bloc: { paddingBottom: spacing.sm },
    declencheur: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',
      minHeight: 56,
      paddingHorizontal: spacing.sm,
      borderRadius: radius.md,
      borderWidth: StyleSheet.hairlineWidth,
      borderColor: t.border,
      backgroundColor: t.inputBg,
    },
    presse: { opacity: 0.7 },
    etiquette: { fontSize: typography.fontSize.xs, color: t.textMuted },
    valeur: { ...typography.preset.bodyReadable, color: t.text },
    chevron: { color: t.textMuted },
    fond: { flex: 1, justifyContent: 'flex-end', backgroundColor: 'rgba(0,0,0,0.5)' },
    feuille: {
      maxHeight: '70%',
      padding: spacing.md,
      borderTopLeftRadius: radius.lg,
      borderTopRightRadius: radius.lg,
      backgroundColor: t.card,
    },
    titre: { ...typography.preset.headline, color: t.text, paddingBottom: spacing.sm },
    ligne: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',
      minHeight: 52,
      borderBottomWidth: StyleSheet.hairlineWidth,
      borderBottomColor: t.border,
    },
    ligneTexte: { ...typography.preset.bodyReadable, color: t.text },
  });
