import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { Icon, Screen } from '@/ui';
import { colors, radius, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { ChosenClientSpace } from '@/company/space';

/**
 * Le choix d'un compte qui est à la fois particulier et membre d'une société.
 *
 * L'organisation est un rattachement du compte, pas un compte distinct : la responsable des locaux
 * d'une entreprise commande aussi son propre ménage. Deviner à sa place lui retirerait l'une des
 * deux moitiés — et c'est le sens du choix, pas une préférence esthétique.
 *
 * Une seule fois : le choix est retenu, et se change depuis le profil.
 */
export function ClientSpaceSwitcherScreen({
  onChoose,
}: {
  onChoose: (space: ChosenClientSpace) => void;
}) {
  const styles = stylesFor(useThemeColors());

  return (
    <Screen>
      <View testID="client-space-switcher" style={styles.container}>
        <Text style={styles.title}>Que souhaitez-vous gérer ?</Text>
        <Text style={styles.subtitle}>
          Votre compte donne accès aux deux espaces. Vous pourrez en changer à tout moment depuis
          votre profil.
        </Text>

        <Choice
          icon="business-outline"
          label="Mon entreprise"
          hint="Locaux, réservations, contrats, facturation"
          onPress={() => onChoose('clientCompany')}
        />
        <Choice
          icon="person-outline"
          label="Mes services perso"
          hint="Mes réservations à titre personnel"
          onPress={() => onChoose('personal')}
        />
      </View>
    </Screen>
  );
}

function Choice({
  icon,
  label,
  hint,
  onPress,
}: {
  icon: string;
  label: string;
  hint: string;
  onPress: () => void;
}) {
  const styles = stylesFor(useThemeColors());

  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={label}
      style={({ pressed }) => [styles.choice, pressed && styles.choicePressed]}
    >
      <Icon name={icon as never} size={26} color={colors.brand[500]} />
      <View style={{ flex: 1 }}>
        <Text style={styles.choiceLabel}>{label}</Text>
        <Text style={styles.choiceHint}>{hint}</Text>
      </View>
      <Icon name="chevron-forward" size={18} color={colors.surface[400]} />
    </Pressable>
  );
}

const stylesFor = (t: ThemeTokens) =>
  StyleSheet.create({
    container: { flex: 1, justifyContent: 'center', gap: spacing.md },
    title: { ...typography.preset.headline, color: t.text, textAlign: 'center' },
    subtitle: {
      fontSize: typography.fontSize.sm,
      color: t.textSecondary,
      textAlign: 'center',
      marginBottom: spacing.lg,
      lineHeight: 20,
    },
    choice: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: spacing.md,
      padding: spacing.lg,
      borderRadius: radius.lg,
      borderWidth: 1,
      borderColor: t.border,
      backgroundColor: t.cardSubtle,
    },
    choicePressed: { backgroundColor: t.inputBg },
    choiceLabel: { ...typography.preset.subhead, color: t.text },
    choiceHint: { fontSize: typography.fontSize.xs, color: t.textSecondary, marginTop: 2 },
  });
