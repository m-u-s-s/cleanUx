import React from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { Screen } from '@/ui';
import {spacing, typography } from '@/theme';
import { useThemeColors } from '@/theme/useThemeColors';
import type { ThemeTokens } from '@/theme/useThemeColors';
import { useTraduction } from '@/i18n';

/**
 * Filet de sécurité du lot A.
 *
 * L'annuaire ne rend navigables que les modules déclarés couverts dans le registre serveur, et
 * aucun ne l'est encore : cet écran n'est donc atteignable que si quelqu'un bascule un module sur
 * `descriptor` ou `screen` avant que le moteur de console (lot B) n'existe. Il dit alors la
 * vérité plutôt que de faire tomber l'application sur une route inconnue.
 */
export function AdminResourceScreen({ route }: { route: { params?: { title?: string } } }) {
  const { t: tr } = useTraduction();
  const styles = stylesFor(useThemeColors());

  const title = route.params?.title ?? tr('admin_resource.ce_module');

  return (
    <Screen>
      <View style={styles.container}>
        <Text style={styles.title}>{tr('admin_resource.titre_pas_encore_servi', { titre: title })}</Text>
        <Text style={styles.body}>{tr('admin_resource.le_module_est_declare_couvert_dans_le')}</Text>
      </View>
    </Screen>
  );
}

const stylesFor = (t: ThemeTokens) => StyleSheet.create({
  container: { flex: 1, justifyContent: 'center', paddingHorizontal: spacing.lg },
  title: {
    ...typography.preset.headline,
    color: t.text,
    marginBottom: spacing.sm,
    textAlign: 'center',
  },
  body: {
    fontSize: typography.fontSize.sm,
    color: t.textSecondary,
    textAlign: 'center',
    lineHeight: 20,
  },
});
