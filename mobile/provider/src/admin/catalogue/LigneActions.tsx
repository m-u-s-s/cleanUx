import React, { useState } from 'react';
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';
import { Icon } from '@/ui';
import { colors, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';

export interface ActionDeLigne {
  cle: string;
  libelle: string;
  /** Rouge et confirmée : suppression, archivage. */
  destructive?: boolean;
  executer: () => void | Promise<void>;
}

interface Props {
  /** Ce sur quoi portent les actions, pour l'annoncer aux lecteurs d'écran. */
  sujet: string;
  actions: ActionDeLigne[];
}

/**
 * Le menu d'actions d'une ligne : modifier, basculer, supprimer.
 *
 * POURQUOI UN MENU ET NON DES BOUTONS ALIGNÉS. Le web affiche quatre boutons par ligne ; sur un
 * téléphone, quatre cibles de 36 px côte à côte donnent des touchers manqués — et le bouton
 * « Supprimer » est alors voisin de « Modifier ». Le menu impose un geste de plus avant toute
 * action, et ce geste est précisément ce qui manque à un pouce pressé.
 *
 * UNE ACTION DESTRUCTIVE DEMANDE CONFIRMATION, toujours. Sur mobile il n'y a pas d'annulation, pas
 * de Ctrl+Z, et l'écran est petit : ce qu'on efface par erreur, on ne le voit même pas disparaître.
 */
export function LigneActions({ sujet, actions }: Props) {
  const styles = stylesFor(useThemeColors());
  const [ouvert, setOuvert] = useState(false);

  const lancer = (action: ActionDeLigne) => {
    setOuvert(false);

    if (!action.destructive) {
      void action.executer();

      return;
    }

    Alert.alert(
      action.libelle,
      `${sujet} — cette action ne s’annule pas.`,
      [
        { text: 'Annuler', style: 'cancel' },
        { text: action.libelle, style: 'destructive', onPress: () => void action.executer() },
      ],
    );
  };

  return (
    <View>
      <Pressable
        onPress={() => setOuvert((o) => !o)}
        accessibilityRole="button"
        accessibilityLabel={`Actions sur ${sujet}`}
        accessibilityState={{ expanded: ouvert }}
        hitSlop={8}
        style={styles.declencheur}
      >
        <Icon name="ellipsis-horizontal" size={20} color={colors.surface[500]} />
      </Pressable>

      {ouvert ? (
        <View style={styles.menu}>
          {actions.map((action) => (
            <Pressable
              key={action.cle}
              onPress={() => lancer(action)}
              accessibilityRole="button"
              style={({ pressed }) => [styles.entree, pressed && styles.entreePressee]}
            >
              <Text style={[styles.libelle, action.destructive && styles.libelleDestructif]}>
                {action.libelle}
              </Text>
            </Pressable>
          ))}
        </View>
      ) : null}
    </View>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  declencheur: {
    width: 44,
    height: 44,
    alignItems: 'center',
    justifyContent: 'center',
  },
  menu: {
    position: 'absolute',
    right: 0,
    top: 44,
    zIndex: 10,
    minWidth: 200,
    borderRadius: 12,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: t.border,
    backgroundColor: t.card,
    paddingVertical: spacing.xs,
  },
  entree: { minHeight: 44, justifyContent: 'center', paddingHorizontal: spacing.sm },
  entreePressee: { backgroundColor: t.inputBg },
  libelle: { fontSize: typography.fontSize.sm, color: t.text },
  libelleDestructif: { color: t.danger },
});
