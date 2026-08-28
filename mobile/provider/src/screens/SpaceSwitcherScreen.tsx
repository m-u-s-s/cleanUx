import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { Icon, Screen } from '@/ui';
import { colors, radius, spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import type { ChosenSpace } from '@/admin/space';
import { useTraduction } from '@/i18n';

/**
 * Le choix d'espace d'un compte qui porte les deux casquettes.
 *
 * Un administrateur qui intervient aussi sur le terrain existe, et le forcer à tenir deux comptes
 * lui donnerait deux historiques et deux facturations. Plutôt que de deviner à sa place, on lui
 * demande — une fois : le choix est retenu, et se change depuis le profil.
 */
export function SpaceSwitcherScreen({
  onChoose,
  /**
   * Le pilotage de société n'est proposé qu'à qui l'a. Une troisième carte offerte à tous mènerait
   * à un espace dont chaque écran répond 403 — le sélecteur promettrait ce que le serveur refuse.
   */
  peutPiloterLaSociete = false,
}: {
  onChoose: (space: ChosenSpace) => void;
  peutPiloterLaSociete?: boolean;
}) {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  return (
    <Screen>
      <View testID="space-switcher" style={styles.container}>
        <Text style={styles.title}>{tr('space_switcher.ou_souhaitez_vous_travailler')}</Text>
        <Text style={styles.subtitle}>
          Votre compte donne accès à plusieurs espaces. Vous pourrez en changer à tout moment depuis
          votre profil.
        </Text>

        <Choice
          icon="speedometer-outline"
          label="Administration"
          hint="Piloter la plateforme"
          onPress={() => onChoose('admin')}
        />
        {peutPiloterLaSociete ? (
          <Choice
            icon="business-outline"
            label="Ma société"
            hint="Répartir les missions et gérer mes équipes"
            onPress={() => onChoose('providerCompany')}
          />
        ) : null}
        <Choice
          icon="briefcase-outline"
          label="Terrain"
          hint="Mes missions et mes revenus"
          onPress={() => onChoose('provider')}
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

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
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
